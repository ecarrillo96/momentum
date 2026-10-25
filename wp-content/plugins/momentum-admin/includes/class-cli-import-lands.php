<?php
/**
 * Comando WP-CLI: importa/actualiza Terrenos desde reference/terrenos.csv.
 * Reutiliza el CPT 'terrenos' y su esquema de meta ya existentes (ver
 * inc/cpt/terrenos.php) — mismos campos que administra el formulario nativo,
 * nada paralelo. Idempotente por 'codigo_propiedad'. No toca imágenes.
 *
 * Uso:
 *   wp momentum import-lands --dry-run
 *   wp momentum import-lands --execute
 *   wp momentum import-lands --code=MT-218 --execute
 *   wp momentum import-lands --limit=5 --execute
 *   wp momentum import-lands --resume --execute
 *
 * (no-op fuera de WP-CLI).
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

class Momentum_Admin_CLI_Import_Lands
{
	const SOURCE_META = '_momentum_import_source';
	const CODE_META    = '_momentum_import_code';
	const HASH_META    = '_momentum_import_hash';
	const DATE_META    = '_momentum_import_date';
	const ALLOWLIST_OPTION = 'momentum_import_published_land_codes';

	const EXPECTED_ACTIVE = 19;

	const CLOSED_ESTADOS = ['Vendida', 'Rentada'];

	/**
	 * Resolución de zona por código, determinada leyendo manualmente título +
	 * descripción de las 19 filas activas (auditoría 2026-08-22) — la
	 * columna 'zona' del CSV es un ID numérico legacy que NO corresponde 1:1
	 * a una sola ubicación (zona=1 mezcla Lomas de Cocoyoc, Valle de Bravo,
	 * Oaxtepec y Cuautla; zona=2 es La Hondonada excepto MT-254, que su
	 * propia descripción dice "LOMAS DE COCOYOC" explícitamente). Ver
	 * reports/terrain-import-summary.md para el texto exacto que sustenta
	 * cada asignación.
	 */
	const ZONA_BY_CODE = [
		'MT-218'  => 'La Hondonada',
		'MT-219'  => 'La Hondonada',
		'MT-221'  => 'La Hondonada',
		'MT-223'  => 'La Hondonada',
		'MT-224'  => 'La Hondonada',
		'MT-225'  => 'La Hondonada',
		'MT-254'  => 'Lomas de Cocoyoc',
		'MT-277'  => 'Valle de Bravo',
		'MT-303'  => 'Oaxtepec',
		'MT-328'  => 'Lomas de Cocoyoc',
		'MT-338'  => 'Lomas de Cocoyoc',
		'MT-345'  => 'Lomas de Cocoyoc',
		'MT-401'  => 'Lomas de Cocoyoc',
		'MT-402'  => 'Lomas de Cocoyoc',
		'MT-512'  => 'Lomas de Cocoyoc',
		'MT-764'  => 'Lomas de Cocoyoc',
		'MT-791'  => 'Lomas de Cocoyoc',
		'MT-800'  => 'Lomas de Cocoyoc',
		'MTC-397' => 'Cuautla',
	];

	private string $csv_basename = '';
	private array $zona_slug_cache = [];

	/**
	 * Importa terrenos activos (status=1) desde terrenos.csv.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : Ruta al CSV.
	 *
	 * [--dry-run]
	 * : Modo por defecto. No escribe nada en WordPress.
	 *
	 * [--execute]
	 * : Ejecuta los cambios reales.
	 *
	 * [--limit=<n>]
	 * : Procesa como máximo n terrenos en esta corrida.
	 *
	 * [--code=<codigo>]
	 * : Procesa solo este código (ej. MT-218).
	 *
	 * [--resume]
	 * : Omite terrenos ya marcados como importados por esta herramienta.
	 *
	 * [--verbose]
	 * : Log detallado.
	 *
	 * ## EXAMPLES
	 *
	 *     wp momentum import-lands terrenos.csv --dry-run
	 *     wp momentum import-lands terrenos.csv --execute
	 */
	public function __invoke($args, $assoc_args)
	{
		$csv_path = $args[0] ?? '/Users/eduardo/Local Sites/momentumcocoyoc/reference/terrenos.csv';
		$execute  = isset($assoc_args['execute']);
		$limit    = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 0;
		$only_code = $assoc_args['code'] ?? '';
		$resume   = isset($assoc_args['resume']);
		$verbose  = isset($assoc_args['verbose']);

		if (! is_readable($csv_path)) {
			WP_CLI::error("No se puede leer el CSV: {$csv_path}");

			return;
		}

		$this->csv_basename = basename($csv_path);

		$parsed = $this->parse_csv($csv_path);

		if ($parsed['fatal_error']) {
			WP_CLI::error($parsed['fatal_error']);

			return;
		}

		$result = $this->process_rows($parsed['rows']);

		$stop_reasons = $this->validate_stop_conditions($result);

		if (! empty($stop_reasons)) {
			WP_CLI::log('=== BLOQUEOS ===');
			foreach ($stop_reasons as $r) {
				WP_CLI::log("  ✗ {$r}");
			}
			WP_CLI::error('No se realiza ningún cambio real (ver bloqueos arriba).');

			return;
		}

		$active = $result['active'];

		if ($only_code !== '') {
			$active = array_intersect_key($active, [$only_code => true]);
		}

		if ($resume) {
			$active = array_filter($active, function ($rec) {
				$existing = $this->find_existing($rec['codigo']);

				return $existing === 0 || ! get_post_meta($existing, self::SOURCE_META, true);
			});
		}

		$codes = array_keys($active);
		sort($codes, SORT_STRING);
		if ($limit > 0) {
			$codes = array_slice($codes, 0, $limit);
		}

		$allowlist = $this->resolve_allowlist($result['active']);

		$this->print_dry_run_report($parsed, $result, $allowlist);

		if (! $execute) {
			WP_CLI::success('Dry run completado. Ejecuta con --execute para aplicar los cambios.');

			return;
		}

		if ($allowlist['source'] === 'computed') {
			update_option(self::ALLOWLIST_OPTION, array_values($allowlist['codes']), false);
		}

		$this->ensure_zonas_exist($result['active']);

		$exec_result = $this->execute_import($result['active'], $codes, $allowlist['codes'], $verbose);

		$this->write_reports($parsed, $result, $allowlist, $exec_result);
		$this->print_final_report($result, $allowlist, $exec_result);
		$this->verify_after_import($result['active'], $allowlist['codes']);

		WP_CLI::success('Importación de terrenos completada.');
	}

	// -----------------------------------------------------------------
	// CSV
	// -----------------------------------------------------------------

	private function parse_csv(string $path): array
	{
		$fh = fopen($path, 'rb');

		if (! $fh) {
			return ['fatal_error' => 'No se pudo abrir el archivo.'];
		}

		$header_raw = fgetcsv($fh, 0, ',', '"', '');

		if ($header_raw === false) {
			fclose($fh);

			return ['fatal_error' => 'Archivo vacío o encabezado ilegible.'];
		}

		$header = array_map(static fn ($h) => trim((string) $h), $header_raw);
		$expected_cols = count($header);
		$rows = [];
		$parse_errors = [];
		$line_no = 1;

		while (($raw = fgetcsv($fh, 0, ',', '"', '')) !== false) {
			$line_no++;

			if ($raw === [null]) {
				continue;
			}

			if (count($raw) !== $expected_cols) {
				$parse_errors[] = sprintf('Línea %d: se esperaban %d columnas, se encontraron %d', $line_no, $expected_cols, count($raw));

				continue;
			}

			$row = ['_line' => $line_no];
			foreach ($header as $i => $col) {
				$row[$col] = $raw[$i] ?? '';
			}
			$rows[] = $row;
		}

		fclose($fh);

		if (! empty($parse_errors)) {
			return ['fatal_error' => 'Errores de parsing: ' . implode(' | ', array_slice($parse_errors, 0, 10))];
		}

		return ['fatal_error' => null, 'header' => $header, 'rows' => $rows, 'total_rows' => count($rows)];
	}

	// -----------------------------------------------------------------
	// Normalización de campos
	// -----------------------------------------------------------------

	private function csv_text(string $raw): string
	{
		$v = trim($raw);
		$upper = strtoupper($v);

		if (in_array($upper, ['NULL', 'N/A', 'NAN'], true)) {
			return '';
		}

		return $v;
	}

	private function csv_number(string $raw): float
	{
		$v = $this->csv_text($raw);

		if ($v === '') {
			return 0.0;
		}

		$v = str_replace(['$', ',', ' '], '', $v);

		return is_numeric($v) ? (float) $v : 0.0;
	}

	private function maybe_fix_mojibake(string $value): array
	{
		if ($value === '' || (strpos($value, 'Ã') === false && strpos($value, 'Â') === false)) {
			return [$value, false];
		}

		$fixed_any = false;
		$result = preg_replace_callback(
			'/[ÃÂ][\x{0080}-\x{00FF}]{1,2}/u',
			static function (array $m) use (&$fixed_any): string {
				$recovered = @mb_convert_encoding($m[0], 'ISO-8859-1', 'UTF-8');

				if ($recovered === false || ! mb_check_encoding($recovered, 'UTF-8')) {
					return $m[0];
				}
				if (strpos($recovered, 'Ã') !== false || strpos($recovered, 'Â') !== false) {
					return $m[0];
				}
				$fixed_any = true;

				return $recovered;
			},
			$value
		);

		if ($result === null || ! $fixed_any) {
			return [$value, false];
		}

		return [$result, true];
	}

	private function normalize_br(string $value): string
	{
		return preg_replace('#<\s*/?\s*br\s*/?\s*>#i', "\n", $value);
	}

	/**
	 * Extrae líneas de característica (empiezan con *, - o •) de la
	 * descripción YA normalizada a saltos de línea reales. No elimina esas
	 * líneas de la descripción -solo las copia-.
	 */
	private function extract_caracteristicas(string $description_with_breaks): string
	{
		$lines = preg_split('/\r\n|\r|\n/', $description_with_breaks);
		$seen  = [];
		$out   = [];

		foreach ($lines as $line) {
			$trimmed = trim($line);

			if ($trimmed === '' || ! preg_match('/^[\*\-\x{2022}]\s*(.+)$/u', $trimmed, $m)) {
				continue;
			}

			$text = trim($m[1]);
			$text = rtrim($text, '.');
			$key  = mb_strtolower($text);

			if ($text === '' || isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$out[]      = $text;
		}

		return implode("\n", $out);
	}

	/**
	 * Rechaza URLs "genéricas" que apuntan al home de YouTube en vez de a un
	 * video específico (ej. "www.youtube.com", "https://www.youtube.com/").
	 */
	private function is_generic_video_url(string $url): bool
	{
		$host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
		$path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
		$query = (string) wp_parse_url($url, PHP_URL_QUERY);

		$is_youtube_host = $host === 'youtube.com' || $host === 'www.youtube.com' || $host === 'youtu.be';

		return $is_youtube_host && $path === '' && $query === '';
	}

	private function sanitize_video(string $raw): array
	{
		$raw = $this->csv_text($raw);

		if ($raw === '') {
			return ['', false];
		}

		// Acepta también URLs sin esquema (ej. "www.youtube.com") solo para
		// poder evaluarlas con is_generic_video_url() antes de descartarlas;
		// si no tienen esquema y no son el caso genérico, se rechazan igual
		// (momentum_sanitize_video_url exige http(s) explícito).
		$candidate = $raw;
		if (! preg_match('#^https?://#i', $candidate)) {
			$candidate = 'https://' . ltrim($candidate, '/');
		}

		if ($this->is_generic_video_url($candidate)) {
			return ['', true];
		}

		$clean = function_exists('momentum_sanitize_video_url') ? momentum_sanitize_video_url($raw) : '';

		if ($clean === '') {
			return ['', true];
		}

		return [$clean, false];
	}

	// -----------------------------------------------------------------
	// Procesamiento
	// -----------------------------------------------------------------

	private function process_rows(array $rows): array
	{
		$status_counts = ['1' => 0, '3' => 0, '4' => 0, 'other' => 0];
		$active = [];
		$duplicates = [];
		$warnings = [];

		foreach ($rows as $row) {
			$status = trim($row['status'] ?? '');

			if (isset($status_counts[$status])) {
				$status_counts[$status]++;
			} else {
				$status_counts['other']++;
			}

			if ($status !== '1') {
				continue;
			}

			$codigo = trim($row['id'] ?? '');

			if ($codigo === '') {
				$warnings[] = [$codigo, 'parsing_error', "Fila activa sin código (línea {$row['_line']})"];

				continue;
			}

			if (isset($active[$codigo])) {
				$duplicates[] = $codigo;
			}

			$active[$codigo] = $this->build_record($row, $codigo, $warnings);
		}

		return compact('status_counts', 'active', 'duplicates', 'warnings');
	}

	private function build_record(array $row, string $codigo, array &$warnings): array
	{
		// --- título ---
		$titulo_raw = $this->csv_text($row['titulo'] ?? '');
		[$titulo_raw, $tf] = $this->maybe_fix_mojibake($titulo_raw);
		if ($tf) {
			$warnings[] = [$codigo, 'encoding_fixed', 'titulo: mojibake corregido'];
		}
		$missing_title = $titulo_raw === '';
		$titulo = $missing_title ? "Propiedad {$codigo}" : $titulo_raw;
		if ($missing_title) {
			$warnings[] = [$codigo, 'missing_title', "Sin título; se usa 'Propiedad {$codigo}'"];
		}

		// --- descripción ---
		$desc_raw = $this->csv_text($row['descripcion'] ?? '');
		[$desc_raw, $df] = $this->maybe_fix_mojibake($desc_raw);
		if ($df) {
			$warnings[] = [$codigo, 'encoding_fixed', 'descripcion: mojibake corregido'];
		}
		$desc_raw = $this->normalize_br($desc_raw);
		$desc_raw = preg_replace('/[ \t]+/', ' ', $desc_raw);
		$desc_raw = preg_replace('/\n{3,}/', "\n\n", $desc_raw);
		$desc_raw = trim($desc_raw);

		$caracteristicas = $this->extract_caracteristicas($desc_raw);

		$descripcion = function_exists('wp_kses_post') ? wp_kses_post($desc_raw) : $desc_raw;
		if ($descripcion === '') {
			$warnings[] = [$codigo, 'missing_description', 'Descripción vacía'];
		}

		// --- precio / superficie / taxes ---
		$precio = $this->csv_number($row['costo'] ?? '');
		if ($precio <= 0) {
			$warnings[] = [$codigo, 'invalid_price', "costo no numérico o cero: '" . ($row['costo'] ?? '') . "'"];
		}

		$superficie = $this->csv_number($row['superficie'] ?? '');
		if ($superficie <= 0) {
			$warnings[] = [$codigo, 'zero_terreno', 'Superficie = 0'];
		}

		$taxes = $this->csv_number($row['tax_amount'] ?? ''); // 0 si NULL/vacío/inválido, por regla del spec

		// --- zona ---
		$zona_name = self::ZONA_BY_CODE[$codigo] ?? null;
		if ($zona_name === null) {
			$warnings[] = [$codigo, 'zone_mapping_error', "Sin resolución de zona conocida para {$codigo}"];
		}

		// --- videos ---
		[$video_url, $video_es_invalid] = $this->sanitize_video($row['video'] ?? '');
		if ($video_es_invalid) {
			$warnings[] = [$codigo, 'invalid_video_es', "video inválido/genérico: '" . ($row['video'] ?? '') . "'"];
		}

		[$video_url_en, $video_en_invalid] = $this->sanitize_video($row['video_en'] ?? '');
		if ($video_en_invalid) {
			$warnings[] = [$codigo, 'invalid_video_en', "video_en inválido/genérico: '" . ($row['video_en'] ?? '') . "'"];
		}

		return [
			'codigo' => $codigo,
			'titulo' => $titulo,
			'missing_title' => $missing_title,
			'descripcion' => $descripcion,
			'caracteristicas' => $caracteristicas,
			'precio' => $precio,
			'superficie' => $superficie,
			'taxes' => $taxes,
			'zona_name' => $zona_name,
			'video_url' => $video_url,
			'video_url_en' => $video_url_en,
			'has_video_es' => $video_url !== '',
			'has_video_en' => $video_url_en !== '',
		];
	}

	// -----------------------------------------------------------------
	// Zonas
	// -----------------------------------------------------------------

	/**
	 * Consulta directa al CPT 'zonas' (nunca momentum_get_zonas()): esa
	 * función cachea su resultado en un static interno que NO se invalida
	 * -si en este mismo proceso se crea una zona nueva después de que algo
	 * ya llamó momentum_get_zonas() (p. ej. momentum_register_propiedad_meta()
	 * en 'init', antes de que este comando corra), la zona recién creada
	 * queda invisible para el resto del proceso. Bug real detectado en
	 * MT-218 durante pruebas: "La Hondonada" se creaba correctamente en BD
	 * pero zona_slug() seguía sin encontrarla en ese mismo proceso.
	 */
	private function zona_slug(string $name): string
	{
		if (isset($this->zona_slug_cache[$name])) {
			return $this->zona_slug_cache[$name];
		}

		$posts = get_posts([
			'post_type'      => 'zonas',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'title'          => $name,
		]);

		if (empty($posts)) {
			// Búsqueda por título exacta 'title' de WP_Query es sensible a
			// coincidencia exacta pero puede fallar por diferencias de
			// mayúsculas; respaldo case-insensitive sobre todas las zonas.
			$all = get_posts(['post_type' => 'zonas', 'post_status' => 'publish', 'posts_per_page' => -1, 'no_found_rows' => true]);
			foreach ($all as $p) {
				if (mb_strtolower($p->post_title) === mb_strtolower($name)) {
					$this->zona_slug_cache[$name] = $p->post_name;

					return $p->post_name;
				}
			}

			return '';
		}

		$this->zona_slug_cache[$name] = $posts[0]->post_name;

		return $posts[0]->post_name;
	}

	private function ensure_zonas_exist(array $active): void
	{
		$needed = array_unique(array_filter(array_column($active, 'zona_name')));

		foreach ($needed as $name) {
			if ($this->zona_slug($name) !== '') {
				continue;
			}

			if (function_exists('momentum_find_zona_by_name') && momentum_find_zona_by_name($name)) {
				continue;
			}

			$new_id = wp_insert_post(['post_type' => 'zonas', 'post_title' => $name, 'post_status' => 'publish'], true);

			if (! is_wp_error($new_id)) {
				WP_CLI::log("Zona creada: {$name}");
			}
		}

		$this->zona_slug_cache = [];
	}

	// -----------------------------------------------------------------
	// Validaciones de bloqueo
	// -----------------------------------------------------------------

	private function validate_stop_conditions(array $result): array
	{
		$reasons = [];
		$active = $result['active'];
		$count = count($active);

		if ($count !== self::EXPECTED_ACTIVE) {
			$reasons[] = sprintf('Se esperaban %d terrenos activos, se encontraron %d.', self::EXPECTED_ACTIVE, $count);
		}

		if (! empty($result['duplicates'])) {
			$reasons[] = 'Códigos duplicados: ' . implode(', ', array_unique($result['duplicates']));
		}

		if (! function_exists('momentum_terreno_meta_schema') || ! post_type_exists('terrenos')) {
			$reasons[] = "No se puede identificar el CPT 'terrenos'.";
		}

		$unresolved = array_keys(array_filter($active, static fn ($r) => $r['zona_name'] === null));
		if (! empty($unresolved)) {
			$reasons[] = 'Zona sin resolver para: ' . implode(', ', $unresolved);
		}

		foreach ($result['warnings'] as [$codigo, $cat, $msg]) {
			if ($cat === 'parsing_error') {
				$reasons[] = "Error de datos en {$codigo}: {$msg}";
			}
		}

		return $reasons;
	}

	// -----------------------------------------------------------------
	// Allowlist de 5 publicados
	// -----------------------------------------------------------------

	private function resolve_allowlist(array $active): array
	{
		$stored = get_option(self::ALLOWLIST_OPTION);

		if (is_array($stored) && count($stored) === 5) {
			$still_valid = array_filter($stored, static fn ($c) => isset($active[$c]));

			if (count($still_valid) === 5) {
				return ['codes' => array_values($stored), 'source' => 'reused', 'reasons' => array_fill_keys($stored, 'Reutilizada de una ejecución anterior')];
			}
		}

		$codes = array_keys($active);
		sort($codes, SORT_STRING);

		$picked = [];
		$reasons = [];
		$used_zonas = [];

		// Recorre por zona distinta primero, para maximizar cobertura de
		// ubicaciones entre los 5 seleccionados.
		foreach ($codes as $c) {
			if (count($picked) >= 5) {
				break;
			}

			$zona = $active[$c]['zona_name'];

			if (isset($used_zonas[$zona])) {
				continue;
			}

			$used_zonas[$zona] = true;
			$picked[] = $c;
			$reasons[$c] = "Primera zona nueva en orden de código: {$zona}";
		}

		// Si quedan menos de 5 zonas distintas, completa con el resto en
		// orden de código, evitando repetir códigos ya elegidos.
		foreach ($codes as $c) {
			if (count($picked) >= 5) {
				break;
			}
			if (in_array($c, $picked, true)) {
				continue;
			}
			$picked[] = $c;
			$reasons[$c] = 'Relleno determinístico por código ascendente';
		}

		return ['codes' => $picked, 'source' => 'computed', 'reasons' => $reasons];
	}

	// -----------------------------------------------------------------
	// Identidad
	// -----------------------------------------------------------------

	private function find_existing(string $codigo): int
	{
		$q = new WP_Query([
			'post_type'      => 'terrenos',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				['key' => 'codigo_propiedad', 'value' => $codigo, 'compare' => '='],
			],
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
	// Ejecución real
	// -----------------------------------------------------------------

	private function execute_import(array $active, array $codes, array $publish_codes, bool $verbose): array
	{
		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = 0;
		$rows    = [];

		foreach ($codes as $codigo) {
			$rec = $active[$codigo];
			$existing_id = $this->find_existing($codigo);
			$is_new = $existing_id === 0;

			$has_media = $existing_id > 0 && (get_post_thumbnail_id($existing_id) || (string) get_post_meta($existing_id, 'galeria', true) !== '');

			if (class_exists('Momentum_Property_Translation_Service')) {
				Momentum_Property_Translation_Service::defer_auto_trigger();
			}

			$target_status = in_array($codigo, $publish_codes, true) ? 'publish' : 'draft';

			$postarr = [
				'post_type'    => 'terrenos',
				'post_title'   => $rec['titulo'],
				'post_content' => $rec['descripcion'],
				'post_status'  => $target_status,
			];

			if ($is_new) {
				$result = wp_insert_post($postarr, true);
			} else {
				$postarr['ID'] = $existing_id;
				$result = wp_update_post($postarr, true);
			}

			if (is_wp_error($result)) {
				$errors++;
				WP_CLI::warning("Error guardando {$codigo}: " . $result->get_error_message());
				if (class_exists('Momentum_Property_Translation_Service')) {
					Momentum_Property_Translation_Service::resume_auto_trigger();
				}
				$rows[] = ['codigo' => $codigo, 'accion' => 'error', 'error' => $result->get_error_message()];

				continue;
			}

			$post_id = (int) $result;
			$hash = hash('sha256', $rec['titulo'] . '|' . $rec['descripcion'] . '|' . $rec['precio'] . '|' . $rec['superficie']);

			$this->save_meta($post_id, $rec);

			update_post_meta($post_id, self::SOURCE_META, $this->csv_basename);
			update_post_meta($post_id, self::CODE_META, $codigo);
			update_post_meta($post_id, self::HASH_META, $hash);
			update_post_meta($post_id, self::DATE_META, current_time('mysql'));

			if (class_exists('Momentum_Property_Translation_Service')) {
				Momentum_Property_Translation_Service::handle_save($post_id, get_post($post_id), ! $is_new);
				Momentum_Property_Translation_Service::resume_auto_trigger();
			}

			$translation_id = function_exists('momentum_find_translation') ? momentum_find_translation($post_id, 'en') : 0;

			// El spec exige el string literal "Property {codigo}" para
			// títulos automáticos; DeepL traduce "Propiedad {codigo}" con
			// orden gramatical natural en inglés ("{codigo} Property"), no
			// el literal pedido -se fuerza aquí después de la traducción
			// automática, solo para el caso de título automático-.
			if ($rec['missing_title'] && $translation_id && $translation_id !== $post_id) {
				wp_update_post(['ID' => $translation_id, 'post_title' => "Property {$codigo}"]);
			}

			$is_new ? $created++ : $updated++;

			if ($verbose) {
				WP_CLI::log(sprintf('[%s] %s (WP #%d)%s', $codigo, $is_new ? 'creado' : 'actualizado', $post_id, $has_media ? ' — medios previos preservados' : ''));
			}

			$rows[] = [
				'codigo'          => $codigo,
				'wp_id_es'        => $post_id,
				'wp_id_en'        => $translation_id,
				'titulo_es'       => $rec['titulo'],
				'titulo_en'       => $translation_id ? get_the_title($translation_id) : '',
				'zona'            => $rec['zona_name'],
				'precio'          => $rec['precio'],
				'superficie'      => $rec['superficie'],
				'taxes'           => $rec['taxes'],
				'video_es'        => $rec['video_url'],
				'video_en'        => $rec['video_url_en'],
				'estado_comercial' => get_post_meta($post_id, 'estado', true),
				'visibilidad'     => $target_status,
				'traducciones_ok' => $translation_id > 0 ? 'sí' : 'no',
				'imagen_destacada' => $has_media ? 'preexistente' : 'No',
				'cantidad_imagenes' => $has_media ? count(array_filter(explode(',', (string) get_post_meta($post_id, 'galeria', true)))) : 0,
				'accion'          => $is_new ? 'creado' : ($has_media ? 'actualizado (medios preservados)' : 'actualizado'),
				'error'           => '',
			];
		}

		return compact('created', 'updated', 'skipped', 'errors', 'rows');
	}

	private function save_meta(int $post_id, array $rec): void
	{
		update_post_meta($post_id, 'codigo_propiedad', $rec['codigo']);
		update_post_meta($post_id, 'precio', $rec['precio']);
		update_post_meta($post_id, 'terreno_m2', $rec['superficie']);
		update_post_meta($post_id, 'taxes_usd', $rec['taxes']);

		if ($rec['caracteristicas'] !== '') {
			update_post_meta($post_id, 'caracteristicas', $rec['caracteristicas']);
		}

		if ($rec['zona_name'] !== null) {
			$slug = $this->zona_slug($rec['zona_name']);
			if ($slug !== '') {
				update_post_meta($post_id, 'zona', $slug);
			}
		}

		if ($rec['video_url'] !== '') {
			update_post_meta($post_id, 'video_url', $rec['video_url']);
		} else {
			delete_post_meta($post_id, 'video_url');
		}
		if ($rec['video_url_en'] !== '') {
			update_post_meta($post_id, 'video_url_en', $rec['video_url_en']);
		} else {
			delete_post_meta($post_id, 'video_url_en');
		}

		$current_estado = get_post_meta($post_id, 'estado', true);
		if (! in_array($current_estado, self::CLOSED_ESTADOS, true)) {
			update_post_meta($post_id, 'estado', 'Disponible');
		}
	}

	// -----------------------------------------------------------------
	// Verificación / reportes
	// -----------------------------------------------------------------

	private function verify_after_import(array $active, array $publish_codes): void
	{
		WP_CLI::log('');
		WP_CLI::log('=== Verificación posterior ===');

		$found = 0;
		$publish_ct = 0;
		$draft_ct = 0;
		$missing = [];
		$no_media = 0;

		foreach (array_keys($active) as $codigo) {
			$id = $this->find_existing($codigo);
			if ($id === 0) {
				$missing[] = $codigo;

				continue;
			}
			$found++;
			$status = get_post_status($id);
			if ($status === 'publish') {
				$publish_ct++;
			} elseif ($status === 'draft') {
				$draft_ct++;
			}
			if (! get_post_thumbnail_id($id) && (string) get_post_meta($id, 'galeria', true) === '') {
				$no_media++;
			}
		}

		WP_CLI::log("Terrenos activos encontrados en WP: {$found} / " . count($active));
		WP_CLI::log("publish: {$publish_ct} (esperado 5) | draft: {$draft_ct} (esperado 14)");
		WP_CLI::log("Sin imagen destacada ni galería: {$no_media} / {$found} (esperado {$found} en registros nuevos)");

		if (! empty($missing)) {
			WP_CLI::warning('No encontrados tras importar: ' . implode(', ', $missing));
		}
	}

	private function print_dry_run_report(array $parsed, array $result, array $allowlist): void
	{
		$active = $result['active'];
		$w = $result['warnings'];

		WP_CLI::log('=== CSV ===');
		WP_CLI::log("Archivo: {$this->csv_basename}");
		WP_CLI::log("Total filas: {$parsed['total_rows']}");
		WP_CLI::log('status 1: ' . $result['status_counts']['1'] . ' | status 3: ' . $result['status_counts']['3'] . ' | status 4: ' . $result['status_counts']['4']);
		WP_CLI::log('');
		WP_CLI::log('Códigos activos: ' . count($active) . ' — ' . implode(', ', array_keys($active)));
		WP_CLI::log('');
		WP_CLI::log('=== Zonas resueltas ===');
		foreach (self::ZONA_BY_CODE as $codigo => $zona) {
			if (isset($active[$codigo])) {
				WP_CLI::log("  {$codigo} -> {$zona}");
			}
		}
		WP_CLI::log('');
		WP_CLI::log('=== Publicación ===');
		WP_CLI::log('Publicados (' . $allowlist['source'] . '): ' . implode(', ', $allowlist['codes']));
		foreach ($allowlist['codes'] as $c) {
			WP_CLI::log('  ' . $c . ': ' . ($allowlist['reasons'][$c] ?? ''));
		}
		WP_CLI::log('');
		WP_CLI::log('=== Advertencias ===');
		foreach ($w as [$codigo, $cat, $msg]) {
			WP_CLI::log("  [{$cat}] {$codigo}: {$msg}");
		}
	}

	private function print_final_report(array $result, array $allowlist, array $exec_result): void
	{
		WP_CLI::log('');
		WP_CLI::log('=== REPORTE FINAL ===');
		WP_CLI::log('Creados: ' . $exec_result['created']);
		WP_CLI::log('Actualizados: ' . $exec_result['updated']);
		WP_CLI::log('Errores: ' . $exec_result['errors']);
		WP_CLI::log('Publicados: ' . implode(', ', $allowlist['codes']));
	}

	private function write_reports(array $parsed, array $result, array $allowlist, array $exec_result): void
	{
		$dir = '/Users/eduardo/Local Sites/momentumcocoyoc/reports';
		if (! is_dir($dir)) {
			wp_mkdir_p($dir);
		}

		$csv_path = $dir . '/terrain-import-report.csv';
		$fh = fopen($csv_path, 'w');
		fputcsv($fh, ['Codigo', 'ID español', 'ID inglés', 'Título español', 'Título inglés', 'Zona', 'Precio', 'Superficie', 'Taxes USD', 'Video español', 'Video inglés', 'Estado comercial', 'Visibilidad', 'Traducciones relacionadas', 'Imagen destacada', 'Cantidad de imágenes', 'Acción realizada', 'Advertencias', 'Errores']);

		$warnings_by_code = [];
		foreach ($result['warnings'] as [$codigo, $cat, $msg]) {
			$warnings_by_code[$codigo][] = "[{$cat}] {$msg}";
		}

		foreach ($exec_result['rows'] as $r) {
			$adv = implode(' | ', $warnings_by_code[$r['codigo']] ?? []);
			fputcsv($fh, [
				$r['codigo'], $r['wp_id_es'] ?? '', $r['wp_id_en'] ?? '', $r['titulo_es'] ?? '', $r['titulo_en'] ?? '',
				$r['zona'] ?? '', $r['precio'] ?? '', $r['superficie'] ?? '', $r['taxes'] ?? '',
				$r['video_es'] ?? '', $r['video_en'] ?? '', $r['estado_comercial'] ?? '', $r['visibilidad'] ?? '',
				$r['traducciones_ok'] ?? '', $r['imagen_destacada'] ?? '', $r['cantidad_imagenes'] ?? 0,
				$r['accion'] ?? '', $adv, $r['error'] ?? '',
			]);
		}
		fclose($fh);

		$zonas_used = array_unique(array_column($result['active'], 'zona_name'));
		$titulos_auto = count(array_filter($result['active'], static fn ($r) => $r['missing_title']));
		$videos_validos = count(array_filter($result['active'], static fn ($r) => $r['has_video_es']));
		$videos_invalidos = count(array_filter($result['warnings'], static fn ($w) => $w[1] === 'invalid_video_es'));
		$desc_reparadas = count(array_filter($result['warnings'], static fn ($w) => $w[1] === 'encoding_fixed'));

		$md = "# Reporte de importación de terrenos\n\n";
		$md .= "Archivo: {$this->csv_basename}\n\n";
		$md .= "Registros totales del CSV: {$parsed['total_rows']}\n";
		$md .= "Registros con status=1: " . count($result['active']) . "\n";
		$md .= "Registros descartados (status != 1): " . ($parsed['total_rows'] - count($result['active'])) . "\n";
		$md .= "Creados: {$exec_result['created']}\n";
		$md .= "Actualizados: {$exec_result['updated']}\n";
		$md .= "Errores: {$exec_result['errors']}\n\n";
		$md .= "Códigos publicados: " . implode(', ', $allowlist['codes']) . "\n";
		$md .= "Códigos en borrador: " . implode(', ', array_diff(array_keys($result['active']), $allowlist['codes'])) . "\n\n";
		$md .= "Zonas utilizadas: " . implode(', ', $zonas_used) . "\n";
		$md .= "Zonas creadas en esta corrida: La Hondonada, Valle de Bravo (si no existían)\n";
		$md .= "Títulos automáticos generados: {$titulos_auto}\n";
		$md .= "Videos válidos importados: {$videos_validos}\n";
		$md .= "Videos inválidos/genéricos ignorados: {$videos_invalidos}\n";
		$md .= "Descripciones con mojibake reparado: {$desc_reparadas}\n";
		$md .= "Confirmación: no se importó ninguna fotografía en esta corrida.\n\n";
		$md .= "## Mapeo de zona por código\n\n";
		foreach (self::ZONA_BY_CODE as $codigo => $zona) {
			if (isset($result['active'][$codigo])) {
				$md .= "- {$codigo} → {$zona}\n";
			}
		}
		$md .= "\n## Advertencias\n\n";
		foreach ($result['warnings'] as [$codigo, $cat, $msg]) {
			$md .= "- **{$codigo}** [{$cat}]: {$msg}\n";
		}

		file_put_contents($dir . '/terrain-import-summary.md', $md);

		WP_CLI::log("Reportes escritos en: {$csv_path}");
	}
}

WP_CLI::add_command('momentum import-lands', 'Momentum_Admin_CLI_Import_Lands');
