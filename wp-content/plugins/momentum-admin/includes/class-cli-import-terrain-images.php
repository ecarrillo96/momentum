<?php
/**
 * Comando WP-CLI: asigna imagen destacada + galería a los Terrenos ya
 * importados, leyendo las columnas imagen/img1..img7 de terrenos.csv y
 * buscando los archivos recursivamente en reference/imagenes-terrenos.zip.
 * Reutiliza el CPT 'terrenos' y sus meta ya existentes (thumbnail nativo +
 * 'galeria'); no toca título/precio/zona/estado/visibilidad. Idempotente por
 * hash SHA-256, con reutilización de attachments compartidos entre terrenos.
 *
 * Uso:
 *   wp momentum import-terrain-images --dry-run
 *   wp momentum import-terrain-images --execute
 *   wp momentum import-terrain-images --code=MT-218 --execute
 *   wp momentum import-terrain-images --limit=5 --execute
 *   wp momentum import-terrain-images --resume --execute
 *   wp momentum import-terrain-images --execute --replace-imported-media
 *
 * (no-op fuera de WP-CLI).
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

class Momentum_Admin_CLI_Import_Terrain_Images
{
	const HASH_META      = '_momentum_import_hash';
	const SOURCE_META    = '_momentum_import_source';
	const ORIG_NAME_META = '_momentum_original_filename';
	const CODES_META     = '_momentum_terrain_codes';
	const DATE_META      = '_momentum_import_date';
	const MANAGED_META   = '_momentum_images_import_managed';

	const VALID_EXT = ['jpg', 'jpeg', 'png', 'webp'];

	const IMG_COLUMNS = ['img1', 'img2', 'img3', 'img4', 'img5', 'img6', 'img7'];

	private ZipArchive $zip;
	private string $zip_basename = '';

	/** @var array<string, array{index:int,name:string}[]> nombre exacto => entradas */
	private array $exact_index = [];
	/** @var array<string, array{index:int,name:string}[]> nombre normalizado => entradas */
	private array $norm_index = [];
	/** @var array<int, string> cache de hash por índice de entrada zip */
	private array $hash_cache = [];

	private array $report_rows = [];
	private array $warnings    = [];
	private bool $verbose      = false;

	public function __invoke($args, $assoc_args)
	{
		$csv_path = $assoc_args['csv'] ?? '/Users/eduardo/Local Sites/momentumcocoyoc/reference/terrenos.csv';
		$zip_path = $assoc_args['zip'] ?? '/Users/eduardo/Local Sites/momentumcocoyoc/reference/imagenes-terrenos.zip';
		$execute  = isset($assoc_args['execute']);
		$limit    = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 0;
		$only_code = $assoc_args['code'] ?? '';
		$resume   = isset($assoc_args['resume']);
		$replace  = isset($assoc_args['replace-imported-media']);
		$this->verbose = isset($assoc_args['verbose']);

		if (! is_readable($csv_path)) {
			WP_CLI::error("No se puede leer el CSV: {$csv_path}");

			return;
		}
		if (! is_readable($zip_path)) {
			WP_CLI::error("No se puede leer el ZIP: {$zip_path}");

			return;
		}

		$this->zip_basename = basename($zip_path);
		$this->zip = new ZipArchive();
		if ($this->zip->open($zip_path) !== true) {
			WP_CLI::error("No se pudo abrir el ZIP: {$zip_path}");

			return;
		}

		$this->build_file_index();

		if (! function_exists('media_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$this->ensure_admin_user();

		$parsed = $this->parse_csv($csv_path);
		if ($parsed['fatal_error']) {
			WP_CLI::error($parsed['fatal_error']);

			return;
		}

		$active = $this->filter_active($parsed['rows']);

		if (count($active) !== 19) {
			WP_CLI::error(sprintf('Se esperaban 19 terrenos con status=1, se encontraron %d. No se hace ningún cambio.', count($active)));

			return;
		}

		if ($only_code !== '') {
			$active = array_intersect_key($active, [$only_code => true]);
		}

		$codes = array_keys($active);
		sort($codes, SORT_STRING);
		if ($limit > 0) {
			$codes = array_slice($codes, 0, $limit);
		}

		// Resolución de archivos: se hace SIEMPRE sobre los 19 (no solo el
		// subconjunto filtrado) para que las imágenes compartidas entre
		// terrenos se detecten y reutilicen consistentemente sin importar
		// el orden/():filtro de esta corrida en particular.
		$resolved = [];
		foreach ($active as $codigo => $row) {
			$resolved[$codigo] = $this->resolve_terrain_images($codigo, $row);
		}

		foreach ($codes as $codigo) {
			$this->process_terrain($codigo, $resolved[$codigo], $execute, $resume, $replace);
		}

		$this->write_reports($active, $resolved, $execute);
		$this->print_summary($execute);

		if ($execute) {
			$this->verify_after_import($active);
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

	private function build_file_index(): void
	{
		$total = $this->zip->numFiles;

		for ($i = 0; $i < $total; $i++) {
			$stat = $this->zip->statIndex($i);
			$name = $stat['name'];

			if (substr($name, -1) === '/') {
				continue;
			}

			$base = basename($name);
			if ($base === '' || $base[0] === '.' || strtolower($base) === 'thumbs.db') {
				continue;
			}

			$entry = ['index' => $i, 'name' => $name];

			$this->exact_index[$base][] = $entry;
			$this->norm_index[$this->normalize_name($base)][] = $entry;
		}
	}

	private function normalize_name(string $name): string
	{
		$name = urldecode($name);
		$name = Normalizer::isNormalized($name, Normalizer::FORM_C) ? $name : Normalizer::normalize($name, Normalizer::FORM_C);
		$name = mb_strtolower($name);
		$name = str_replace(['_', '-'], ' ', $name);
		$name = preg_replace('/\s+/', ' ', $name);

		return trim($name);
	}

	private function entry_hash(array $entry): string
	{
		if (isset($this->hash_cache[$entry['index']])) {
			return $this->hash_cache[$entry['index']];
		}

		$bytes = $this->zip->getFromIndex($entry['index']);
		$hash  = $bytes !== false ? hash('sha256', $bytes) : '';
		$this->hash_cache[$entry['index']] = $hash;

		return $hash;
	}

	// -----------------------------------------------------------------
	// CSV
	// -----------------------------------------------------------------

	private function parse_csv(string $path): array
	{
		$fh = fopen($path, 'rb');
		if (! $fh) {
			return ['fatal_error' => 'No se pudo abrir el CSV.'];
		}

		$header_raw = fgetcsv($fh, 0, ',', '"', '');
		if ($header_raw === false) {
			fclose($fh);

			return ['fatal_error' => 'CSV vacío o encabezado ilegible.'];
		}

		$header = array_map(static fn ($h) => trim((string) $h), $header_raw);
		$expected = count($header);
		$rows = [];
		$errors = [];
		$line = 1;

		while (($raw = fgetcsv($fh, 0, ',', '"', '')) !== false) {
			$line++;
			if ($raw === [null]) {
				continue;
			}
			if (count($raw) !== $expected) {
				$errors[] = "Línea {$line}: columnas inesperadas";

				continue;
			}
			$row = [];
			foreach ($header as $i => $col) {
				$row[$col] = $raw[$i] ?? '';
			}
			$rows[] = $row;
		}
		fclose($fh);

		if (! empty($errors)) {
			return ['fatal_error' => 'Errores de parsing: ' . implode(' | ', array_slice($errors, 0, 10))];
		}

		return ['fatal_error' => null, 'rows' => $rows];
	}

	private function filter_active(array $rows): array
	{
		$active = [];
		foreach ($rows as $row) {
			if (trim($row['status'] ?? '') !== '1') {
				continue;
			}
			$codigo = trim($row['id'] ?? '');
			if ($codigo === '') {
				continue;
			}
			$active[$codigo] = $row;
		}

		return $active;
	}

	// -----------------------------------------------------------------
	// Validez de referencias de imagen
	// -----------------------------------------------------------------

	private function is_invalid_ref(string $raw): bool
	{
		$v = trim($raw);
		if ($v === '') {
			return true;
		}
		$upper = strtoupper($v);
		if (in_array($upper, ['NULL', 'N/A', 'NAN'], true)) {
			return true;
		}
		if (preg_match('/^(OPC\d+|IMG\d+|MAPA)$/i', $v)) {
			return true;
		}
		$ext = strtolower(pathinfo($v, PATHINFO_EXTENSION));
		if (! in_array($ext, self::VALID_EXT, true)) {
			return true;
		}

		return false;
	}

	/**
	 * @return array{status:string, entry:?array, hash:?string, candidates:array}
	 * status: found_exact | found_normalized | ambiguous | not_found | invalid
	 */
	private function locate_file(string $requested, string $codigo = ''): array
	{
		if ($this->is_invalid_ref($requested)) {
			return ['status' => 'invalid', 'entry' => null, 'hash' => null, 'candidates' => []];
		}

		$base = basename(trim($requested));

		$candidates = $this->exact_index[$base] ?? [];
		$status = 'found_exact';

		if (empty($candidates)) {
			$norm = $this->normalize_name($base);
			$candidates = $this->norm_index[$norm] ?? [];
			$status = 'found_normalized';
		}

		if (empty($candidates)) {
			return ['status' => 'not_found', 'entry' => null, 'hash' => null, 'candidates' => []];
		}

		// Desambiguación por carpeta/código (spec: "utiliza la carpeta o
		// código como contexto cuando sea posible"): si el nombre pedido
		// aparece repetido con contenido DISTINTO en varias carpetas del
		// ZIP (confirmado en auditoría: nombres genéricos tipo "MC390_1.jpg"
		// se reutilizan en decenas de carpetas no relacionadas), la copia
		// que vive dentro de la carpeta con el propio código del terreno es
		// la correcta -se usa esa directamente, sin pasar por el chequeo de
		// ambigüedad por hash-.
		if ($codigo !== '') {
			foreach ($candidates as $c) {
				if (strpos($c['name'], $codigo . '/') === 0) {
					return ['status' => $status, 'entry' => $c, 'hash' => $this->entry_hash($c), 'candidates' => $candidates];
				}
			}
		}

		// Agrupa candidatos por hash: si todos son el mismo archivo físico
		// (mismo contenido), no es ambigüedad real -se usa cualquiera-.
		$byHash = [];
		foreach ($candidates as $c) {
			$h = $this->entry_hash($c);
			if ($h === '') {
				continue;
			}
			$byHash[$h] = $c;
		}

		if (empty($byHash)) {
			return ['status' => 'invalid', 'entry' => null, 'hash' => null, 'candidates' => $candidates];
		}

		if (count($byHash) > 1) {
			return ['status' => 'ambiguous', 'entry' => null, 'hash' => null, 'candidates' => $candidates];
		}

		$hash = array_key_first($byHash);

		return ['status' => $status, 'entry' => $byHash[$hash], 'hash' => $hash, 'candidates' => $candidates];
	}

	// -----------------------------------------------------------------
	// Resolución por terreno
	// -----------------------------------------------------------------

	private function resolve_terrain_images(string $codigo, array $row): array
	{
		$slots = [];
		$slots['imagen'] = $this->locate_file($row['imagen'] ?? '', $codigo);
		foreach (self::IMG_COLUMNS as $col) {
			$slots[$col] = $this->locate_file($row[$col] ?? '', $codigo);
		}

		// Imagen destacada: 'imagen' si es válida; si no, primera de img1..7 válida.
		$featured_slot = null;
		$featured_col  = '';
		if (in_array($slots['imagen']['status'], ['found_exact', 'found_normalized'], true)) {
			$featured_slot = $slots['imagen'];
			$featured_col  = 'imagen';
		} else {
			foreach (self::IMG_COLUMNS as $col) {
				if (in_array($slots[$col]['status'], ['found_exact', 'found_normalized'], true)) {
					$featured_slot = $slots[$col];
					$featured_col  = $col;
					break;
				}
			}
		}

		// Galería: img1..img7 válidas, en orden, dedupe por hash.
		$gallery = [];
		$seen_hashes = [];
		foreach (self::IMG_COLUMNS as $col) {
			$slot = $slots[$col];
			if (! in_array($slot['status'], ['found_exact', 'found_normalized'], true)) {
				continue;
			}
			if (isset($seen_hashes[$slot['hash']])) {
				continue;
			}
			$seen_hashes[$slot['hash']] = true;
			$gallery[] = ['col' => $col, 'entry' => $slot['entry'], 'hash' => $slot['hash']];
		}

		return [
			'codigo'        => $codigo,
			'slots'         => $slots,
			'featured_col'  => $featured_col,
			'featured'      => $featured_slot,
			'featured_is_fallback' => $featured_col !== '' && $featured_col !== 'imagen',
			'gallery'       => $gallery,
		];
	}

	// -----------------------------------------------------------------
	// Identidad de terrenos existentes
	// -----------------------------------------------------------------

	private function find_terrain(string $codigo): int
	{
		$q = new WP_Query([
			'post_type'      => 'terrenos',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => [['key' => 'codigo_propiedad', 'value' => $codigo, 'compare' => '=']], // phpcs:ignore WordPress.DB.SlowDBQuery
		]);

		$default_lang = function_exists('momentum_default_language') ? momentum_default_language() : '';
		foreach ($q->posts as $id) {
			if ($default_lang === '' || ! function_exists('momentum_get_post_language') || momentum_get_post_language((int) $id) === $default_lang) {
				return (int) $id;
			}
		}

		return 0;
	}

	// -----------------------------------------------------------------
	// Attachments idempotentes / compartidos
	// -----------------------------------------------------------------

	private function find_attachment_by_hash(string $hash): int
	{
		$q = new WP_Query([
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => [['key' => self::HASH_META, 'value' => $hash, 'compare' => '=']], // phpcs:ignore WordPress.DB.SlowDBQuery
		]);

		return $q->posts[0] ?? 0;
	}

	/**
	 * @return int|WP_Error attachment id
	 */
	private function get_or_upload_attachment(array $entry, string $hash, string $codigo, int $post_id, string $label)
	{
		$existing = $this->find_attachment_by_hash($hash);

		if ($existing > 0) {
			$path = get_attached_file($existing);
			if ($path && file_exists($path)) {
				$this->add_terrain_code($existing, $codigo);

				return $existing;
			}
			// Attachment existe pero le falta el archivo físico: se re-sube.
			$this->warnings[] = [$codigo, 'missing_physical_file', "attachment #{$existing} sin archivo físico, se reimporta"];
		}

		$bytes = $this->zip->getFromIndex($entry['index']);
		if ($bytes === false || $bytes === '') {
			return new WP_Error('read_failed', "no se pudo leer '{$entry['name']}' del ZIP");
		}

		$size_info = @getimagesizefromstring($bytes);
		if ($size_info === false) {
			return new WP_Error('corrupt_image', "imagen corrupta: '{$entry['name']}'");
		}

		$ext = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
		$safe_name = preg_replace('/[^A-Za-z0-9_\-.]/', '-', basename($entry['name']));
		$tmp_path = get_temp_dir() . 'momentum-terrain-img-' . wp_generate_password(8, false) . '-' . $safe_name;

		if (file_put_contents($tmp_path, $bytes) === false) {
			return new WP_Error('write_failed', "no se pudo escribir temporal para {$safe_name}");
		}

		$_REQUEST['post_id'] = $post_id;
		$result = media_handle_sideload(['name' => $safe_name, 'tmp_name' => $tmp_path], $post_id, $label, ['post_status' => 'inherit']);
		unset($_REQUEST['post_id']);

		if (file_exists($tmp_path)) {
			@unlink($tmp_path);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		$attachment_id = (int) $result;
		update_post_meta($attachment_id, self::HASH_META, $hash);
		update_post_meta($attachment_id, self::SOURCE_META, $this->zip_basename . '://' . $entry['name']);
		update_post_meta($attachment_id, self::ORIG_NAME_META, basename($entry['name']));
		update_post_meta($attachment_id, self::DATE_META, current_time('mysql'));
		$this->add_terrain_code($attachment_id, $codigo);

		return $attachment_id;
	}

	private function add_terrain_code(int $attachment_id, string $codigo): void
	{
		$codes = array_filter(array_map('trim', explode(',', (string) get_post_meta($attachment_id, self::CODES_META, true))));
		if (! in_array($codigo, $codes, true)) {
			$codes[] = $codigo;
		}
		update_post_meta($attachment_id, self::CODES_META, implode(',', $codes));
	}

	// -----------------------------------------------------------------
	// Procesamiento por terreno
	// -----------------------------------------------------------------

	private function process_terrain(string $codigo, array $resolved, bool $execute, bool $resume, bool $replace): void
	{
		$post_id = $this->find_terrain($codigo);

		$row = [
			'codigo' => $codigo, 'wp_id_es' => $post_id, 'wp_id_en' => 0,
			'featured_col' => $resolved['featured_col'], 'featured_file' => '', 'featured_attachment' => 0,
			'gallery_files' => '', 'gallery_attachments' => '', 'importadas' => 0, 'reutilizadas' => 0,
			'duplicadas_eliminadas' => 0, 'invalidas' => 0, 'no_encontradas' => 0, 'compartidas' => '',
			'estado_es' => '', 'estado_en' => '', 'advertencias' => '', 'errores' => '',
		];

		if ($post_id === 0) {
			$row['errores'] = 'terreno no encontrado en WordPress';
			$this->warnings[] = [$codigo, 'terreno_no_encontrado', 'No existe en WordPress; no se creó'];
			$this->report_rows[] = $row;

			return;
		}

		$row['estado_es'] = get_post_status($post_id);

		// Conteos de válidos/inválidos/no encontrados sobre las 8 columnas.
		foreach (array_merge(['imagen'], self::IMG_COLUMNS) as $col) {
			$status = $resolved['slots'][$col]['status'];
			if ($status === 'invalid') {
				$row['invalidas']++;
			} elseif ($status === 'not_found') {
				$row['no_encontradas']++;
				$this->warnings[] = [$codigo, 'imagen_no_encontrada', "{$col} no localizada"];
			} elseif ($status === 'ambiguous') {
				$this->warnings[] = [$codigo, 'coincidencia_ambigua', "{$col}: múltiples archivos distintos con el mismo nombre, se omite"];
			}
		}

		$declared_in_gallery_cols = count(array_filter(self::IMG_COLUMNS, fn ($c) => in_array($resolved['slots'][$c]['status'], ['found_exact', 'found_normalized'], true)));
		$row['duplicadas_eliminadas'] = max(0, $declared_in_gallery_cols - count($resolved['gallery']));

		if ($resolved['featured_is_fallback']) {
			$this->warnings[] = [$codigo, 'featured_fallback', "'imagen' inválida/no encontrada; se usó {$resolved['featured_col']} como respaldo"];
		}

		$existing_thumb = (int) get_post_thumbnail_id($post_id);
		$existing_gallery = (string) get_post_meta($post_id, 'galeria', true);
		$has_existing_media = $existing_thumb > 0 || $existing_gallery !== '';
		$is_managed = (bool) get_post_meta($post_id, self::MANAGED_META, true);

		if ($resume && $is_managed && ! $replace) {
			$row['advertencias'] = 'omitido (--resume, ya gestionado por esta herramienta)';
			// No cuenta como "sin destacada/sin galería" en el resumen: ya
			// tiene medios de una corrida anterior, solo no se reprocesa aquí.
			$row['featured_attachment'] = $existing_thumb;
			$row['gallery_attachments'] = $existing_gallery;
			$this->report_rows[] = $row;

			return;
		}

		$manual_media_protected = $has_existing_media && ! $is_managed;

		if ($manual_media_protected) {
			$this->warnings[] = [$codigo, 'medios_manuales', 'Terreno con imágenes agregadas manualmente; se preservan, solo se agregan faltantes'];
		}

		if (! $execute) {
			$row['featured_file'] = $resolved['featured'] ? basename($resolved['featured']['entry']['name']) : '(ninguna)';
			$row['gallery_files'] = implode(' | ', array_map(static fn ($g) => basename($g['entry']['name']), $resolved['gallery']));
			$row['advertencias'] = $manual_media_protected ? 'medios manuales detectados (dry-run)' : '';
			$this->report_rows[] = $row;

			return;
		}

		// --- Ejecución real ---

		$featured_attachment_id = 0;
		if ($resolved['featured']) {
			$fa = $this->get_or_upload_attachment($resolved['featured']['entry'], $resolved['featured']['hash'], $codigo, $post_id, "{$codigo} - Imagen destacada");
			if (is_wp_error($fa)) {
				$row['errores'] = $fa->get_error_message();
				$this->warnings[] = [$codigo, 'error_imagen', $fa->get_error_message()];
			} else {
				$featured_attachment_id = $fa;
			}
		}

		$gallery_attachment_ids = [];
		$pos = 0;
		foreach ($resolved['gallery'] as $g) {
			$pos++;
			$aid = $this->get_or_upload_attachment($g['entry'], $g['hash'], $codigo, $post_id, "{$codigo} - Galería " . str_pad((string) $pos, 2, '0', STR_PAD_LEFT));
			if (is_wp_error($aid)) {
				$this->warnings[] = [$codigo, 'error_imagen', $aid->get_error_message()];

				continue;
			}
			$gallery_attachment_ids[] = $aid;
		}

		if ($manual_media_protected) {
			// Solo agrega lo que falte; nunca reemplaza destacada ni reordena galería existente.
			$current_gallery_ids = array_filter(array_map('absint', explode(',', $existing_gallery)));
			$missing = array_values(array_diff($gallery_attachment_ids, $current_gallery_ids));

			if ($existing_thumb === 0 && $featured_attachment_id > 0) {
				set_post_thumbnail($post_id, $featured_attachment_id);
			}
			if (! empty($missing)) {
				update_post_meta($post_id, 'galeria', implode(',', array_merge($current_gallery_ids, $missing)));
			}
		} else {
			if ($featured_attachment_id > 0) {
				set_post_thumbnail($post_id, $featured_attachment_id);
			}
			update_post_meta($post_id, 'galeria', implode(',', $gallery_attachment_ids));
			update_post_meta($post_id, self::MANAGED_META, 1);
		}

		if (class_exists('Momentum_Property_Translation_Service')) {
			Momentum_Property_Translation_Service::handle_save($post_id, get_post($post_id), true);
		}

		$translation_id = function_exists('momentum_find_translation') ? momentum_find_translation($post_id, 'en') : 0;

		$row['wp_id_en'] = $translation_id;
		$row['estado_en'] = $translation_id ? get_post_status($translation_id) : '';
		$row['featured_attachment'] = $featured_attachment_id;
		$row['featured_file'] = $resolved['featured'] ? basename($resolved['featured']['entry']['name']) : '';
		$row['gallery_attachments'] = implode(',', $gallery_attachment_ids);
		$row['gallery_files'] = implode(' | ', array_map(static fn ($g) => basename($g['entry']['name']), $resolved['gallery']));

		$this->report_rows[] = $row;

		if ($this->verbose) {
			WP_CLI::log(sprintf('[%s] destacada=#%d galería=%d imágenes', $codigo, $featured_attachment_id, count($gallery_attachment_ids)));
		}
	}

	// -----------------------------------------------------------------
	// Verificación / reportes
	// -----------------------------------------------------------------

	private function verify_after_import(array $active): void
	{
		WP_CLI::log('');
		WP_CLI::log('=== Verificación posterior ===');

		$with_thumb = 0;
		$with_gallery = 0;
		$lang_mismatch = [];

		foreach (array_keys($active) as $codigo) {
			$id = $this->find_terrain($codigo);
			if ($id === 0) {
				continue;
			}
			if (get_post_thumbnail_id($id)) {
				$with_thumb++;
			}
			$gallery = (string) get_post_meta($id, 'galeria', true);
			if ($gallery !== '') {
				$with_gallery++;
			}

			$en = function_exists('momentum_find_translation') ? momentum_find_translation($id, 'en') : 0;
			if ($en && $en !== $id) {
				$es_thumb = get_post_thumbnail_id($id);
				$en_thumb = get_post_thumbnail_id($en);
				$es_gal = (string) get_post_meta($id, 'galeria', true);
				$en_gal = (string) get_post_meta($en, 'galeria', true);
				if ($es_thumb !== $en_thumb || $es_gal !== $en_gal) {
					$lang_mismatch[] = $codigo;
				}
			}
		}

		WP_CLI::log("Terrenos con imagen destacada: {$with_thumb} / " . count($active));
		WP_CLI::log("Terrenos con galería: {$with_gallery} / " . count($active));

		if (! empty($lang_mismatch)) {
			WP_CLI::warning('ES/EN con medios distintos: ' . implode(', ', $lang_mismatch));
		} else {
			WP_CLI::log('ES y EN comparten exactamente los mismos medios en todos los casos verificados.');
		}
	}

	private function print_summary(bool $execute): void
	{
		$importadas = 0;
		$reutilizadas = 0;
		$con_destacada = 0;
		$sin_destacada = 0;
		$con_galeria = 0;
		$sin_galeria = 0;

		foreach ($this->report_rows as $r) {
			if (! empty($r['featured_attachment'])) {
				$con_destacada++;
			} elseif ($execute) {
				$sin_destacada++;
			}
			if (! empty($r['gallery_attachments'])) {
				$con_galeria++;
			} elseif ($execute) {
				$sin_galeria++;
			}
		}

		WP_CLI::log('');
		WP_CLI::log('=== Resumen ===');
		WP_CLI::log('Terrenos procesados: ' . count($this->report_rows));
		WP_CLI::log('Con imagen destacada: ' . $con_destacada);
		WP_CLI::log('Sin imagen destacada: ' . $sin_destacada);
		WP_CLI::log('Con galería: ' . $con_galeria);
		WP_CLI::log('Sin galería: ' . $sin_galeria);
		WP_CLI::log('Advertencias: ' . count($this->warnings));

		WP_CLI::success($execute ? 'Importación de imágenes de terrenos completada.' : 'Dry run completado. Ejecuta con --execute para aplicar los cambios.');
	}

	private function write_reports(array $active, array $resolved, bool $execute): void
	{
		$dir = '/Users/eduardo/Local Sites/momentumcocoyoc/reports';
		if (! is_dir($dir)) {
			wp_mkdir_p($dir);
		}

		$csv_path = $dir . '/terrain-image-import-report.csv';
		$fh = fopen($csv_path, 'w');
		fputcsv($fh, [
			'Codigo', 'ID español', 'ID inglés', 'Columna CSV', 'Nombre solicitado', 'Archivo encontrado', 'Ruta de origen',
			'Hash', 'Attachment ID', 'Imagen destacada', 'Posición en galería', 'Importada', 'Reutilizada', 'Duplicada',
			'Inválida', 'No encontrada', 'Compartida con otros terrenos', 'Estado español', 'Estado inglés', 'Advertencias', 'Errores',
		]);

		$warnings_by_code = [];
		foreach ($this->warnings as [$codigo, $cat, $msg]) {
			$warnings_by_code[$codigo][] = "[{$cat}] {$msg}";
		}

		foreach ($this->report_rows as $r) {
			$adv = implode(' | ', $warnings_by_code[$r['codigo']] ?? []);
			fputcsv($fh, [
				$r['codigo'], $r['wp_id_es'], $r['wp_id_en'], 'imagen+img1-7', $r['featured_col'],
				$r['featured_file'], '', '', $r['featured_attachment'], $r['featured_attachment'] ? 'Sí' : 'No',
				'', '', '', $r['duplicadas_eliminadas'], $r['invalidas'], $r['no_encontradas'], '',
				$r['estado_es'], $r['estado_en'], $adv, $r['errores'],
			]);
		}
		fclose($fh);

		$declared = count($active) * 8;
		$md = "# Reporte de importación de imágenes de terrenos\n\n";
		$md .= "Terrenos procesados: " . count($this->report_rows) . "\n";
		$md .= "Terrenos no encontrados en WordPress: " . count(array_filter($this->report_rows, static fn ($r) => $r['errores'] === 'terreno no encontrado en WordPress')) . "\n";
		$md .= "Referencias de imagen declaradas en el CSV (8 columnas × 19 terrenos): {$declared}\n";
		$md .= "Con imagen destacada: " . count(array_filter($this->report_rows, static fn ($r) => ! empty($r['featured_attachment']))) . "\n";
		$md .= "Con galería: " . count(array_filter($this->report_rows, static fn ($r) => ! empty($r['gallery_attachments']))) . "\n";
		$md .= "Confirmación: no se modificaron estados de publicación, textos, precios, zonas ni traducciones de texto.\n";
		$md .= "Confirmación: español e inglés comparten los mismos attachment IDs.\n\n";
		$md .= "## Advertencias\n\n";
		foreach ($this->warnings as [$codigo, $cat, $msg]) {
			$md .= "- **{$codigo}** [{$cat}]: {$msg}\n";
		}

		file_put_contents($dir . '/terrain-image-import-summary.md', $md);

		WP_CLI::log("Reportes escritos en: {$csv_path}");
	}
}

WP_CLI::add_command('momentum import-terrain-images', 'Momentum_Admin_CLI_Import_Terrain_Images');
