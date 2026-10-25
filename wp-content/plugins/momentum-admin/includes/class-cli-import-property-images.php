<?php
/**
 * Comando WP-CLI: importa las fotos de Propiedades/Terrenos desde el ZIP de
 * referencia (una carpeta por código, ej. "MC-461/"), relacionándolas por
 * código exacto de propiedad -la señal más confiable disponible, ver
 * analyze_zip en el reporte-. Idempotente por hash SHA-256 + código; nunca
 * duplica, nunca toca título/precio/estado/publicación.
 *
 * Uso:
 *   wp momentum import-property-images --dry-run
 *   wp momentum import-property-images --execute
 *   wp momentum import-property-images --property-code=MC-473 --execute
 *   wp momentum import-property-images --limit=20 --execute --resume
 *
 * (no-op fuera de WP-CLI).
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

class Momentum_Admin_CLI_Import_Property_Images
{
	const SOURCE_META    = '_momentum_legacy_import_source';
	const HASH_META       = '_momentum_import_hash';
	const CODE_META       = '_momentum_property_code';
	const ORIG_NAME_META  = '_momentum_original_filename';
	const IMPORT_SRC_META = '_momentum_import_source';
	const MANAGED_META    = '_momentum_images_import_managed';
	const IMPORTED_AT_META = '_momentum_images_imported_at';

	const VALID_EXT = ['jpg', 'jpeg', 'png', 'webp'];

	/**
	 * Vacío a propósito: MT-214 incluía una lista de precios escaneada y un
	 * plano maestro del desarrollo (no fotos de la propiedad), pero el
	 * cliente pidió explícitamente (2026-08-22) importarlas de todas formas.
	 */
	const EXCLUDED_ENTRIES = [];

	private ZipArchive $zip;
	private string $zip_basename;
	private bool $verbose = false;

	/** @var array<string,array{index:int,name:string,mtime:int}[]> */
	private array $folder_index = [];

	private array $report_rows = [];
	private array $warnings    = [];

	/**
	 * Importa fotos de Propiedades/Terrenos desde el ZIP de referencia.
	 *
	 * ## OPTIONS
	 *
	 * [--zip=<ruta>]
	 * : Ruta al ZIP. Por defecto reference/propiedades.zip junto al proyecto.
	 *
	 * [--dry-run]
	 * : Modo por defecto. No escribe nada en WordPress ni en disco.
	 *
	 * [--execute]
	 * : Ejecuta los cambios reales.
	 *
	 * [--limit=<n>]
	 * : Procesa como máximo n propiedades en esta corrida.
	 *
	 * [--property-code=<codigo>]
	 * : Procesa solo esta propiedad (ej. MC-473).
	 *
	 * [--resume]
	 * : Omite propiedades ya marcadas como gestionadas por esta importación.
	 *
	 * [--verbose]
	 * : Log detallado por archivo.
	 *
	 * ## EXAMPLES
	 *
	 *     wp momentum import-property-images --dry-run
	 *     wp momentum import-property-images --execute --property-code=MC-473
	 */
	public function __invoke($args, $assoc_args)
	{
		$zip_path = $assoc_args['zip'] ?? '/Users/eduardo/Local Sites/momentumcocoyoc/reference/propiedades.zip';
		$execute  = isset($assoc_args['execute']);
		$limit    = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 0;
		$only_code = $assoc_args['property-code'] ?? '';
		$resume   = isset($assoc_args['resume']);
		$this->verbose = isset($assoc_args['verbose']);

		if (! is_readable($zip_path)) {
			WP_CLI::error("No se puede leer el ZIP: {$zip_path}");

			return;
		}

		$this->zip_basename = basename($zip_path);
		$this->zip           = new ZipArchive();

		if ($this->zip->open($zip_path) !== true) {
			WP_CLI::error("No se pudo abrir el ZIP: {$zip_path}");

			return;
		}

		$this->build_folder_index();

		if (! function_exists('media_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		// La resolución de carpeta de subida (momentum-property-media-organizer)
		// exige current_user_can('edit_post', ...); WP-CLI no tiene usuario
		// autenticado por defecto. Se usa el primer administrador real del
		// sitio -no se crea ningún usuario nuevo-.
		$this->ensure_admin_user();

		$properties = $this->get_target_properties($only_code, $resume, $limit);

		WP_CLI::log(sprintf('Propiedades a procesar: %d (modo: %s)', count($properties), $execute ? 'EJECUCIÓN REAL' : 'dry-run'));

		foreach ($properties as $prop) {
			$this->process_property($prop, $execute);
		}

		$this->write_reports();
		$this->print_summary($execute);

		if ($execute) {
			$this->verify_after_import($properties);
		}

		$this->zip->close();
	}

	// -----------------------------------------------------------------
	// Setup
	// -----------------------------------------------------------------

	private function ensure_admin_user(): void
	{
		if (get_current_user_id() > 0) {
			return;
		}

		$admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC']);

		if (! empty($admins)) {
			wp_set_current_user($admins[0]->ID);
		}
	}

	private function build_folder_index(): void
	{
		$total = $this->zip->numFiles;

		for ($i = 0; $i < $total; $i++) {
			$stat = $this->zip->statIndex($i);
			$name = $stat['name'];

			if (substr($name, -1) === '/') {
				continue;
			}

			$parts = explode('/', $name, 2);

			if (count($parts) !== 2) {
				continue;
			}

			[$folder, $filename] = $parts;
			$base = basename($filename);

			if ($base === '' || $base[0] === '.' || strtolower($base) === 'thumbs.db') {
				continue;
			}

			$ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));

			if (! in_array($ext, self::VALID_EXT, true)) {
				continue;
			}

			if (in_array($name, self::EXCLUDED_ENTRIES, true)) {
				continue;
			}

			$this->folder_index[$folder][] = [
				'index' => $i,
				'name'  => $filename,
				'mtime' => $stat['mtime'],
			];
		}

		foreach ($this->folder_index as $folder => $files) {
			usort($files, static function (array $a, array $b): int {
				if ($a['mtime'] !== $b['mtime']) {
					return $a['mtime'] <=> $b['mtime'];
				}

				return strnatcasecmp($a['name'], $b['name']);
			});
			$this->folder_index[$folder] = $files;
		}
	}

	// -----------------------------------------------------------------
	// Propiedades objetivo
	// -----------------------------------------------------------------

	private function get_target_properties(string $only_code, bool $resume, int $limit): array
	{
		$ids = get_posts([
			'post_type'      => ['propiedades', 'terrenos'],
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'meta_key'       => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery
		]);

		$default_lang = function_exists('momentum_default_language') ? momentum_default_language() : '';
		$out          = [];

		foreach ($ids as $id) {
			$id = (int) $id;

			if ($default_lang !== '' && function_exists('momentum_get_post_language') && momentum_get_post_language($id) !== $default_lang) {
				continue;
			}

			$codigo = (string) get_post_meta($id, 'codigo_propiedad', true);

			if ($codigo === '') {
				continue;
			}

			if ($only_code !== '' && $codigo !== $only_code) {
				continue;
			}

			if ($resume && get_post_meta($id, self::MANAGED_META, true)) {
				continue;
			}

			$out[] = ['id' => $id, 'codigo' => $codigo, 'post_type' => get_post_type($id)];
		}

		sort($out);
		usort($out, static fn ($a, $b) => strcmp($a['codigo'], $b['codigo']));

		if ($limit > 0) {
			$out = array_slice($out, 0, $limit);
		}

		return $out;
	}

	// -----------------------------------------------------------------
	// Procesamiento por propiedad
	// -----------------------------------------------------------------

	private function process_property(array $prop, bool $execute): void
	{
		$codigo  = $prop['codigo'];
		$post_id = $prop['id'];
		$row     = [
			'codigo'      => $codigo,
			'wp_id'       => $post_id,
			'encontradas' => 0,
			'importadas'  => 0,
			'reutilizadas' => 0,
			'ignoradas'   => 0,
			'destacada'   => '',
			'galeria'     => '',
			'estado_es'   => get_post_status($post_id),
			'estado_en'   => '',
			'errores'     => '',
			'advertencias' => '',
		];

		$files = $this->folder_index[$codigo] ?? null;

		if ($files === null) {
			$row['advertencias'] = 'sin_carpeta_en_zip';
			$this->warnings[] = [$codigo, 'sin_carpeta_en_zip', "No existe carpeta '{$codigo}' en el ZIP"];
			$this->report_rows[] = $row;
			$this->log_line($codigo, 'sin_carpeta_en_zip');

			return;
		}

		$row['encontradas'] = count($files);

		if (empty($files)) {
			$row['advertencias'] = 'sin_imagenes';
			$this->warnings[] = [$codigo, 'sin_imagenes', "Carpeta '{$codigo}' vacía o sin imágenes válidas"];
			$this->report_rows[] = $row;

			return;
		}

		$already_managed = (bool) get_post_meta($post_id, self::MANAGED_META, true);
		$existing_thumb   = (int) get_post_thumbnail_id($post_id);
		$preserve_thumb   = $existing_thumb > 0 && ! $this->is_our_attachment($existing_thumb, $codigo);

		$attachment_ids = [];
		$seen_hashes    = [];
		$errors         = [];
		$order_number   = 0;

		foreach ($files as $file) {
			$order_number++;
			$bytes = $this->zip->getFromIndex($file['index']);

			if ($bytes === false || $bytes === '') {
				$errors[] = "no se pudo leer '{$file['name']}' del ZIP";

				continue;
			}

			$hash = hash('sha256', $bytes);

			if (isset($seen_hashes[$hash])) {
				$row['ignoradas']++;
				$this->vlog($codigo, "duplicado exacto en ZIP, se omite: {$file['name']}");

				continue;
			}
			$seen_hashes[$hash] = true;

			$size_info = @getimagesizefromstring($bytes);

			if ($size_info === false) {
				$errors[] = "imagen corrupta, se omite: {$file['name']}";
				$row['ignoradas']++;

				continue;
			}

			$existing_id = $this->find_existing_attachment($codigo, $hash);

			if ($existing_id > 0) {
				$attachment_ids[] = $existing_id;
				$row['reutilizadas']++;
				$this->vlog($codigo, "reutilizada attachment #{$existing_id}: {$file['name']}");

				continue;
			}

			if (! $execute) {
				// En dry-run no se sube nada; se cuenta como "se crearía".
				$attachment_ids[] = 0;
				$row['importadas']++;

				continue;
			}

			$new_id = $this->sideload_one($file, $bytes, $codigo, $post_id, $order_number);

			if (is_wp_error($new_id)) {
				$errors[] = "{$file['name']}: " . $new_id->get_error_message();
				$row['ignoradas']++;

				continue;
			}

			$attachment_ids[] = $new_id;
			$row['importadas']++;
			$this->vlog($codigo, "creada attachment #{$new_id}: {$file['name']}");
		}

		$attachment_ids = array_values(array_filter($attachment_ids, static fn ($id) => $id !== 0 || ! $execute));

		if (! empty($errors)) {
			$row['errores'] = implode(' | ', $errors);
			foreach ($errors as $e) {
				$this->warnings[] = [$codigo, 'error_imagen', $e];
			}
		}

		if ($execute && ! empty($attachment_ids)) {
			$real_ids = array_values(array_filter($attachment_ids, static fn ($id) => is_int($id) && $id > 0));

			if (! $preserve_thumb && ! empty($real_ids)) {
				$featured_id = array_shift($real_ids);
				set_post_thumbnail($post_id, $featured_id);
				if (function_exists('momentum_strip_featured_image_from_gallery')) {
					update_post_meta($post_id, 'galeria', implode(',', $real_ids));
					momentum_strip_featured_image_from_gallery($post_id);
				} else {
					update_post_meta($post_id, 'galeria', implode(',', $real_ids));
				}
				$row['destacada'] = (string) $featured_id;
				$row['galeria']   = implode(',', $real_ids);
			} elseif ($preserve_thumb) {
				$row['advertencias'] = trim($row['advertencias'] . ' imagen_destacada_manual_preservada', ' ');
				update_post_meta($post_id, 'galeria', implode(',', $real_ids));
				if (function_exists('momentum_strip_featured_image_from_gallery')) {
					momentum_strip_featured_image_from_gallery($post_id);
				}
				$row['destacada'] = "(preservada #{$existing_thumb})";
				$row['galeria']   = implode(',', $real_ids);
			}

			update_post_meta($post_id, self::MANAGED_META, 1);
			update_post_meta($post_id, self::IMPORTED_AT_META, current_time('mysql'));

			if (class_exists('Momentum_Property_Translation_Service')) {
				Momentum_Property_Translation_Service::handle_save($post_id, get_post($post_id), true);
			}

			$translation_id = function_exists('momentum_find_translation') ? momentum_find_translation($post_id, 'en') : 0;
			$row['estado_en'] = $translation_id ? get_post_status($translation_id) : '(sin traducción)';
		} elseif (! $execute) {
			$row['destacada'] = $preserve_thumb ? "(se preservaría #{$existing_thumb})" : '(primera imagen nueva)';
			$row['galeria']   = 'se calcularía en ejecución real';
		}

		$this->report_rows[] = $row;
	}

	private function is_our_attachment(int $attachment_id, string $codigo): bool
	{
		$owner_code = (string) get_post_meta($attachment_id, self::CODE_META, true);

		return $owner_code === $codigo;
	}

	private function find_existing_attachment(string $codigo, string $hash): int
	{
		$q = new WP_Query([
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'AND',
				['key' => self::CODE_META, 'value' => $codigo, 'compare' => '='],
				['key' => self::HASH_META, 'value' => $hash, 'compare' => '='],
			],
		]);

		return $q->posts[0] ?? 0;
	}

	/**
	 * @return int|WP_Error
	 */
	private function sideload_one(array $file, string $bytes, string $codigo, int $post_id, int $order_number)
	{
		$ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		$index     = str_pad((string) $order_number, 2, '0', STR_PAD_LEFT);
		$safe_name = $codigo . '-' . $index . '.' . $ext;

		$tmp_dir = get_temp_dir();
		$tmp_path = $tmp_dir . 'momentum-img-' . wp_generate_password(8, false) . '-' . $safe_name;

		if (file_put_contents($tmp_path, $bytes) === false) {
			return new WP_Error('write_failed', "no se pudo escribir archivo temporal para {$safe_name}");
		}

		$_REQUEST['post_id'] = $post_id;

		$file_array = ['name' => $safe_name, 'tmp_name' => $tmp_path];
		$result     = media_handle_sideload($file_array, $post_id, null, ['post_status' => 'inherit']);

		unset($_REQUEST['post_id']);

		if (file_exists($tmp_path)) {
			@unlink($tmp_path);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		$attachment_id = (int) $result;
		$hash          = hash('sha256', $bytes);

		update_post_meta($attachment_id, self::HASH_META, $hash);
		update_post_meta($attachment_id, self::CODE_META, $codigo);
		update_post_meta($attachment_id, self::ORIG_NAME_META, basename($file['name']));
		update_post_meta($attachment_id, self::IMPORT_SRC_META, $this->zip_basename . '://' . $codigo . '/' . basename($file['name']));

		return $attachment_id;
	}

	// -----------------------------------------------------------------
	// Verificación posterior
	// -----------------------------------------------------------------

	private function verify_after_import(array $properties): void
	{
		WP_CLI::log('');
		WP_CLI::log('=== Verificación posterior ===');

		$ok_thumb = 0;
		$missing_files = [];
		$no_thumb  = [];

		foreach ($properties as $prop) {
			$post_id = $prop['id'];
			$thumb_id = get_post_thumbnail_id($post_id);

			if ($thumb_id) {
				$path = get_attached_file($thumb_id);
				if ($path && file_exists($path)) {
					$ok_thumb++;
				} else {
					$missing_files[] = $prop['codigo'] . ' (thumb #' . $thumb_id . ')';
				}
			} else {
				$gallery = (string) get_post_meta($post_id, 'galeria', true);
				if ($gallery === '' && ($this->folder_index[$prop['codigo']] ?? null)) {
					$no_thumb[] = $prop['codigo'];
				}
			}

			$gallery_ids = array_filter(array_map('absint', explode(',', (string) get_post_meta($post_id, 'galeria', true))));
			foreach ($gallery_ids as $gid) {
				$path = get_attached_file($gid);
				if (! $path || ! file_exists($path)) {
					$missing_files[] = $prop['codigo'] . ' (galería #' . $gid . ')';
				}
			}
		}

		WP_CLI::log("Propiedades con imagen destacada y archivo físico válido: {$ok_thumb} / " . count($properties));

		if (! empty($no_thumb)) {
			WP_CLI::warning('Propiedades con imágenes en el ZIP pero sin destacada asignada: ' . implode(', ', $no_thumb));
		}
		if (! empty($missing_files)) {
			WP_CLI::warning('Adjuntos sin archivo físico en disco: ' . implode(', ', array_slice($missing_files, 0, 20)));
		}
	}

	// -----------------------------------------------------------------
	// Reportes
	// -----------------------------------------------------------------

	private function write_reports(): void
	{
		$dir = '/Users/eduardo/Local Sites/momentumcocoyoc/reports';

		if (! is_dir($dir)) {
			wp_mkdir_p($dir);
		}

		$csv_path = $dir . '/property-image-import-report.csv';
		$fh       = fopen($csv_path, 'w');

		fputcsv($fh, ['codigo', 'wp_id', 'encontradas', 'importadas', 'reutilizadas', 'ignoradas', 'destacada', 'galeria', 'estado_es', 'estado_en', 'errores', 'advertencias']);

		foreach ($this->report_rows as $r) {
			fputcsv($fh, [
				$r['codigo'], $r['wp_id'], $r['encontradas'], $r['importadas'], $r['reutilizadas'], $r['ignoradas'],
				$r['destacada'], $r['galeria'], $r['estado_es'], $r['estado_en'], $r['errores'], $r['advertencias'],
			]);
		}
		fclose($fh);

		$total_found      = array_sum(array_column($this->report_rows, 'encontradas'));
		$total_created    = array_sum(array_column($this->report_rows, 'importadas'));
		$total_reused     = array_sum(array_column($this->report_rows, 'reutilizadas'));
		$total_ignored    = array_sum(array_column($this->report_rows, 'ignoradas'));
		$without_folder   = count(array_filter($this->warnings, static fn ($w) => $w[1] === 'sin_carpeta_en_zip'));

		$md = "# Reporte de importación de imágenes de propiedades\n\n";
		$md .= "Archivo ZIP: {$this->zip_basename}\n\n";
		$md .= "Propiedades procesadas: " . count($this->report_rows) . "\n";
		$md .= "Sin carpeta en el ZIP: {$without_folder}\n";
		$md .= "Total imágenes encontradas: {$total_found}\n";
		$md .= "Total creadas: {$total_created}\n";
		$md .= "Total reutilizadas: {$total_reused}\n";
		$md .= "Total ignoradas (duplicadas/corruptas): {$total_ignored}\n\n";
		$md .= "## Advertencias\n\n";
		foreach ($this->warnings as [$codigo, $cat, $msg]) {
			$md .= "- **{$codigo}** [{$cat}]: {$msg}\n";
		}

		file_put_contents($dir . '/property-image-import-summary.md', $md);

		WP_CLI::log('');
		WP_CLI::log("Reportes escritos en: {$csv_path}");
	}

	private function print_summary(bool $execute): void
	{
		$total_found   = array_sum(array_column($this->report_rows, 'encontradas'));
		$total_created = array_sum(array_column($this->report_rows, 'importadas'));
		$total_reused  = array_sum(array_column($this->report_rows, 'reutilizadas'));
		$total_ignored = array_sum(array_column($this->report_rows, 'ignoradas'));

		WP_CLI::log('');
		WP_CLI::log('=== Resumen ===');
		WP_CLI::log('Propiedades procesadas: ' . count($this->report_rows));
		WP_CLI::log("Imágenes encontradas: {$total_found}");
		WP_CLI::log(($execute ? 'Creadas' : 'Se crearían') . ": {$total_created}");
		WP_CLI::log('Reutilizadas: ' . $total_reused);
		WP_CLI::log('Ignoradas (duplicadas/corruptas): ' . $total_ignored);
		WP_CLI::log('Advertencias: ' . count($this->warnings));

		WP_CLI::success($execute ? 'Importación de imágenes completada.' : 'Dry run completado. Ejecuta con --execute para aplicar los cambios.');
	}

	private function vlog(string $codigo, string $msg): void
	{
		if ($this->verbose) {
			WP_CLI::log("  [{$codigo}] {$msg}");
		}
	}

	private function log_line(string $codigo, string $msg): void
	{
		WP_CLI::log("[{$codigo}] {$msg}");
	}
}

WP_CLI::add_command('momentum import-property-images', 'Momentum_Admin_CLI_Import_Property_Images');
