<?php
/**
 * Comando WP-CLI: importa/actualiza Propiedades y Terrenos desde el CSV legacy
 * (separador ';', posiblemente Windows-1252). Ver docs de la migración para
 * el mapping completo. Idempotente por 'codigo_propiedad'; nunca borra ni
 * publica nada fuera de la allowlist explícita.
 *
 * Uso:
 *   wp momentum import-properties "propiedades(2).csv" --dry-run
 *   wp momentum import-properties "propiedades(2).csv" --execute
 *   wp momentum import-properties "propiedades(2).csv" --execute --zona-map='{"1":"lomas-de-cocoyoc",...}'
 *
 * (no-op fuera de WP-CLI).
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

class Momentum_Admin_CLI_Import_Properties
{
	const SOURCE_META      = '_momentum_legacy_import_source';
	const IMPORTED_AT_META = '_momentum_legacy_imported_at';
	const ALLOWLIST_OPTION = 'momentum_import_published_test_codes';

	const EXPECTED_ACTIVE = 139;
	const EXPECTED_VENTA  = 131;
	const EXPECTED_RENTA  = 8;

	// Precio de MCR-807 (23,000,000) requiere validación humana antes de
	// publicarse: es órdenes de magnitud mayor que el resto de las rentas
	// activas. Nunca debe entrar en la allowlist de publicadas.
	const CRITICAL_PRICE_CODES = ['MCR-807'];

	// Estados de 'estado' que representan un cierre de negocio real (venta o
	// renta ya concretada). Un re-import con status=1 legacy NUNCA debe
	// resucitar automáticamente una propiedad desde uno de estos estados.
	const CLOSED_ESTADOS = ['Vendida', 'Rentada'];

	private string $csv_basename = '';

	/**
	 * Importa propiedades activas (status=1) desde el CSV legacy.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : Ruta al archivo CSV.
	 *
	 * [--dry-run]
	 * : Modo por defecto. No escribe nada en WordPress.
	 *
	 * [--execute]
	 * : Ejecuta los cambios reales (solo si el dry run no reporta bloqueos).
	 *
	 * [--zona-map=<json-o-ruta>]
	 * : Mapping explícito de zona legacy (numérica) a slug del CPT 'zonas',
	 * como JSON inline o ruta a un archivo .json. Ej:
	 * '{"1":"lomas-de-cocoyoc","2":"cuautla"}'. Sin esto, el mapping de
	 * zona queda sin resolver y bloquea la ejecución real.
	 *
	 * ## EXAMPLES
	 *
	 *     wp momentum import-properties "propiedades(2).csv" --dry-run
	 *     wp momentum import-properties "propiedades(2).csv" --execute
	 */
	public function __invoke($args, $assoc_args)
	{
		$csv_path = $args[0] ?? '';
		$execute  = isset($assoc_args['execute']);

		if ($csv_path === '' || ! is_readable($csv_path)) {
			WP_CLI::error("No se puede leer el archivo CSV: $csv_path");

			return;
		}

		$this->csv_basename = basename($csv_path);

		$parsed = $this->parse_csv($csv_path);

		if ($parsed['fatal_error']) {
			WP_CLI::error($parsed['fatal_error']);

			return;
		}

		$zona_map = $this->load_zona_map($assoc_args['zona-map'] ?? null);

		$result = $this->process_rows($parsed['rows'], $zona_map);

		$allowlist = $this->resolve_allowlist($result['active']);

		$stop_reasons = $this->validate_stop_conditions($result, $zona_map, $allowlist);

		$this->print_dry_run_report($parsed, $result, $zona_map, $allowlist, $stop_reasons);

		if (! empty($stop_reasons)) {
			WP_CLI::warning('Condiciones de bloqueo detectadas. No se realiza ningún cambio real (ver arriba).');

			return;
		}

		if (! $execute) {
			WP_CLI::success('Dry run sin bloqueos estructurales. Ejecuta con --execute para aplicar los cambios.');

			return;
		}

		if ($allowlist['source'] === 'computed') {
			update_option(self::ALLOWLIST_OPTION, array_values($allowlist['codes']), false);
		}

		$exec_result = $this->execute_import($result['active'], $allowlist['codes'], $zona_map);

		$this->print_final_report($parsed, $result, $zona_map, $allowlist, $exec_result);

		$this->verify_after_import($result['active'], $allowlist['codes']);

		WP_CLI::success('Importación completada.');
	}

	// -----------------------------------------------------------------
	// CSV parsing
	// -----------------------------------------------------------------

	private function parse_csv(string $path): array
	{
		$fh = fopen($path, 'rb');

		if (! $fh) {
			return ['fatal_error' => 'No se pudo abrir el archivo.'];
		}

		// Escape vacío ('') a propósito: desactiva el mecanismo de escape por
		// backslash de fgetcsv(), que corrompería HTML/texto con backslashes
		// literales en las descripciones. El separador real de campos es el
		// quoting con comillas dobles, no backslash.
		$header_raw = fgetcsv($fh, 0, ';', '"', '');

		if ($header_raw === false) {
			fclose($fh);

			return ['fatal_error' => 'Archivo vacío o encabezado ilegible.'];
		}

		// Conversión de encoding UNA sola vez, aquí, por campo. El resto del
		// código trabaja siempre en UTF-8.
		$header = array_map(
			static fn ($h) => trim(mb_convert_encoding((string) $h, 'UTF-8', 'Windows-1252')),
			$header_raw
		);

		$expected_cols = count($header);
		$rows          = [];
		$parse_errors  = [];
		$line_no       = 1;

		while (($raw = fgetcsv($fh, 0, ';', '"', '')) !== false) {
			$line_no++;

			if ($raw === [null]) {
				continue;
			}

			if (count($raw) !== $expected_cols) {
				$parse_errors[] = sprintf('Línea %d: se esperaban %d columnas, se encontraron %d', $line_no, $expected_cols, count($raw));

				continue;
			}

			$row = ['_line' => $line_no];

			foreach ($header as $i => $col_name) {
				$val         = $raw[$i];
				$row[$col_name] = $val === null ? '' : mb_convert_encoding((string) $val, 'UTF-8', 'Windows-1252');
			}

			$rows[] = $row;
		}

		fclose($fh);

		if (! empty($parse_errors)) {
			return [
				'fatal_error' => sprintf(
					'Errores de parsing detectados (%d) — se detiene sin tocar WordPress: %s',
					count($parse_errors),
					implode(' | ', array_slice($parse_errors, 0, 10))
				),
			];
		}

		return [
			'fatal_error'  => null,
			'header'       => $header,
			'rows'         => $rows,
			'total_rows'   => count($rows),
			'column_count' => $expected_cols,
		];
	}

	private function load_zona_map($arg): array
	{
		if (! $arg || ! is_string($arg)) {
			return [];
		}

		$arg = trim($arg);

		if ($arg === '') {
			return [];
		}

		if ($arg[0] === '{') {
			$decoded = json_decode($arg, true);
		} elseif (is_readable($arg)) {
			$decoded = json_decode((string) file_get_contents($arg), true);
		} else {
			$decoded = null;
		}

		if (! is_array($decoded)) {
			return [];
		}

		$valid_slugs = function_exists('momentum_get_zonas') ? array_keys(momentum_get_zonas()) : [];
		$map         = [];

		foreach ($decoded as $legacy => $slug) {
			if (in_array($slug, $valid_slugs, true)) {
				$map[(string) $legacy] = $slug;
			}
		}

		return $map;
	}

	// -----------------------------------------------------------------
	// Sanitización / normalización de valores CSV
	// -----------------------------------------------------------------

	private function csv_text(string $raw): string
	{
		$v = trim($raw);

		return strtoupper($v) === 'NULL' ? '' : $v;
	}

	private function csv_number(string $raw): ?float
	{
		$v = $this->csv_text($raw);

		if ($v === '') {
			return null;
		}

		$v = str_replace([',', ' ', '$'], '', $v);

		return is_numeric($v) ? (float) $v : null;
	}

	private function csv_money(string $raw): ?float
	{
		$v = $this->csv_text($raw);

		if ($v === '') {
			return null;
		}

		$v = preg_replace('/<[^>]*>/', '', $v);
		$v = str_replace(['$', ',', ' '], '', (string) $v);

		return is_numeric($v) ? (float) $v : null;
	}

	/**
	 * Revierte mojibake de doble codificación UTF-8 (p.ej. "aspersiÃ³n" ->
	 * "aspersión"), pero SOLO sobre los fragmentos locales que empiezan con
	 * 'Ã'/'Â' (marcador seguro: ninguna palabra española empieza así) —
	 * nunca reinterpretando el campo completo. Un campo puede mezclar un
	 * fragmento mal codificado con acentos YA correctos en otra parte del
	 * mismo texto (caso real: MC-453 tenía "jardÃ­n" mojibake junto a
	 * "distribución" ya correcto); revertir el string entero rompía esos
	 * acentos correctos. Cada fragmento se revierte de forma aislada y solo
	 * se acepta si el resultado es UTF-8 válido y ya no contiene el
	 * marcador — si no, ese fragmento en particular se deja sin tocar.
	 */
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

	// -----------------------------------------------------------------
	// Procesamiento de filas
	// -----------------------------------------------------------------

	private function process_rows(array $rows, array $zona_map): array
	{
		$status_counts = ['1' => 0, '3' => 0, '4' => 0, 'other' => 0];
		$active        = [];
		$duplicates    = [];
		$warnings      = [];

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
				$warnings[] = [$codigo, 'parsing_error', sprintf('Fila activa sin código (línea %d)', $row['_line'])];

				continue;
			}

			if (isset($active[$codigo])) {
				$duplicates[] = $codigo;
			}

			$active[$codigo] = $this->build_record($row, $codigo, $zona_map, $warnings);
		}

		return compact('status_counts', 'active', 'duplicates', 'warnings');
	}

	private function build_record(array $row, string $codigo, array $zona_map, array &$warnings): array
	{
		$post_type = str_starts_with($codigo, 'MT-') ? 'terrenos' : 'propiedades';

		// --- título ---
		$titulo_raw       = $this->csv_text($row['titulo'] ?? '');
		[$titulo_raw, $tf] = $this->maybe_fix_mojibake($titulo_raw);
		if ($tf) {
			$warnings[] = [$codigo, 'encoding_fixed', 'titulo: mojibake corregido'];
		}
		$missing_title = $titulo_raw === '';
		$titulo        = $missing_title ? "Propiedad {$codigo}" : $titulo_raw;
		if ($missing_title) {
			$warnings[] = [$codigo, 'missing_title', "Sin título; se usa 'Propiedad {$codigo}'"];
		}

		// --- descripción ---
		$desc_raw       = $this->csv_text($row['descripcion'] ?? '');
		[$desc_raw, $df] = $this->maybe_fix_mojibake($desc_raw);
		if ($df) {
			$warnings[] = [$codigo, 'encoding_fixed', 'descripcion: mojibake corregido'];
		}
		$desc_raw = str_ireplace('</br>', '<br>', $desc_raw);
		// El editor de WordPress representa saltos de línea con '\n' real, no
		// con la etiqueta <br> del CSV legacy -se convierte aquí para que el
		// contenido se vea/edite como texto normal, no como HTML crudo.
		$desc_raw = preg_replace('#<br\s*/?>#i', "\n", $desc_raw);
		if (function_exists('momentum_normalize_line_endings')) {
			$desc_raw = momentum_normalize_line_endings($desc_raw);
		}
		$descripcion = function_exists('wp_kses_post') ? wp_kses_post($desc_raw) : $desc_raw;
		if ($descripcion === '') {
			$warnings[] = [$codigo, 'missing_description', 'Descripción vacía'];
		}

		// --- precio ---
		$precio = $this->csv_money($row['costo'] ?? '');
		if ($precio === null) {
			$warnings[] = [$codigo, 'invalid_price', "costo no numérico: '" . ($row['costo'] ?? '') . "'"];
		}

		// --- tipo (venta/renta) ---
		$tipo_raw       = strtolower(trim($row['tipo'] ?? ''));
		$tipo_operacion = $tipo_raw === 'ven' ? 'Venta' : ($tipo_raw === 'ren' ? 'Renta' : null);
		if ($tipo_operacion === null) {
			$warnings[] = [$codigo, 'parsing_error', "tipo desconocido: '{$tipo_raw}'"];
		}

		// --- zona ---
		$zona_legacy = trim($row['zona'] ?? '');
		$zona_slug   = $zona_map[$zona_legacy] ?? null;
		if ($zona_slug === null) {
			$warnings[] = [$codigo, 'zone_mapping_error', "zona legacy '{$zona_legacy}' sin mapping conocido"];
		}

		// --- recámaras / baños (solo aplica a 'propiedades') ---
		$recamaras = $this->csv_number($row['recamaras'] ?? '');
		if ($recamaras === null && $post_type === 'propiedades') {
			$warnings[] = [$codigo, 'missing_bedrooms', 'Recámaras vacías'];
		}

		$banos = $this->csv_text($row['banos'] ?? '');
		if ($banos === '' && $post_type === 'propiedades') {
			$warnings[] = [$codigo, 'missing_bathrooms', 'Baños vacíos'];
		}

		// --- terreno / construcción ---
		$terreno_m2 = $this->csv_number($row['superficie'] ?? '');
		if ($terreno_m2 === 0.0) {
			$warnings[] = [$codigo, 'zero_terreno', 'Terreno (superficie) = 0'];
		}

		$construccion_m2 = $post_type === 'propiedades' ? $this->csv_number($row['construccion'] ?? '') : null;
		if ($post_type === 'propiedades' && $construccion_m2 === 0.0) {
			$warnings[] = [$codigo, 'zero_construccion', 'Construcción = 0'];
		}

		// --- taxes ---
		$taxes_usd = $this->csv_number($row['tax_amount'] ?? '');
		if ($taxes_usd !== null && $taxes_usd > 20000) {
			$warnings[] = [$codigo, 'tax_warning', "taxes_usd={$taxes_usd} > 20000"];
		}

		// --- video ES ---
		$video_raw = $this->csv_text($row['video'] ?? '');
		$video_url = '';
		if ($video_raw !== '') {
			$video_url = function_exists('momentum_sanitize_video_url') ? momentum_sanitize_video_url($video_raw) : '';
			if ($video_url === '') {
				$warnings[] = [$codigo, 'invalid_video_es', "video inválido: '{$video_raw}'"];
			}
		}

		// --- video EN ---
		$video_en_raw = $this->csv_text($row['video_en'] ?? '');
		$video_url_en = '';
		if ($video_en_raw !== '') {
			$video_url_en = function_exists('momentum_sanitize_video_url') ? momentum_sanitize_video_url($video_en_raw) : '';
			if ($video_url_en === '') {
				$warnings[] = [$codigo, 'invalid_video_en', "video EN inválido: '{$video_en_raw}'"];
			}
		}

		$critical_price = in_array($codigo, self::CRITICAL_PRICE_CODES, true);
		if ($critical_price) {
			$warnings[] = [$codigo, 'critical_price_warning', "precio={$precio} requiere validación manual antes de publicarse"];
		}

		return [
			'codigo'          => $codigo,
			'post_type'       => $post_type,
			'titulo'          => $titulo,
			'missing_title'   => $missing_title,
			'descripcion'     => $descripcion,
			'precio'          => $precio,
			'tipo_operacion'  => $tipo_operacion,
			'zona_legacy'     => $zona_legacy,
			'zona_slug'       => $zona_slug,
			'recamaras'       => $recamaras,
			'banos'           => $banos,
			'terreno_m2'      => $terreno_m2,
			'construccion_m2' => $construccion_m2,
			'taxes_usd'       => $taxes_usd,
			'video_url'       => $video_url,
			'video_url_en'    => $video_url_en,
			'critical_price'  => $critical_price,
			'has_video_es'    => $video_url !== '',
			'has_video_en'    => $video_url_en !== '',
		];
	}

	// -----------------------------------------------------------------
	// Validaciones de bloqueo (sección 32 del spec)
	// -----------------------------------------------------------------

	private function validate_stop_conditions(array $result, array $zona_map, array $allowlist): array
	{
		$reasons = [];
		$active  = $result['active'];
		$count   = count($active);

		if ($count !== self::EXPECTED_ACTIVE) {
			$reasons[] = sprintf('Se esperaban %d propiedades activas (status=1), se encontraron %d.', self::EXPECTED_ACTIVE, $count);
		}

		$venta = 0;
		$renta = 0;
		foreach ($active as $r) {
			if ($r['tipo_operacion'] === 'Venta') {
				$venta++;
			} elseif ($r['tipo_operacion'] === 'Renta') {
				$renta++;
			}
		}
		if ($venta !== self::EXPECTED_VENTA) {
			$reasons[] = sprintf('Ventas activas esperadas: %d, encontradas: %d.', self::EXPECTED_VENTA, $venta);
		}
		if ($renta !== self::EXPECTED_RENTA) {
			$reasons[] = sprintf('Rentas activas esperadas: %d, encontradas: %d.', self::EXPECTED_RENTA, $renta);
		}

		if (! empty($result['duplicates'])) {
			$reasons[] = 'Códigos activos duplicados: ' . implode(', ', array_unique($result['duplicates']));
		}

		if (! function_exists('momentum_propiedad_meta_schema') || ! post_type_exists('propiedades')) {
			$reasons[] = "No se puede identificar el CPT 'propiedades' (¿tema Momentum activo?).";
		}

		$unmapped = [];
		foreach ($active as $r) {
			if ($r['zona_slug'] === null) {
				$unmapped[$r['zona_legacy']] = true;
			}
		}
		if (! empty($unmapped)) {
			$reasons[] = 'Mapping de zona sin resolver para IDs legacy: ' . implode(', ', array_keys($unmapped))
				. '. Provee --zona-map=\'{"1":"slug-real",...}\' con el slug real del CPT zonas.';
		}

		foreach ($result['warnings'] as [$codigo, $cat, $msg]) {
			if ($cat === 'parsing_error') {
				$reasons[] = "Error de datos en {$codigo}: {$msg}";
			}
		}

		if (count($allowlist['codes']) < 5) {
			$reasons[] = sprintf('Solo se pudieron seleccionar %d propiedades para publicar (se requieren exactamente 5).', count($allowlist['codes']));
		}
		if (count($allowlist['codes']) > 5) {
			$reasons[] = 'La allowlist tiene más de 5 códigos.';
		}
		if (count(array_unique($allowlist['codes'])) !== count($allowlist['codes'])) {
			$reasons[] = 'La allowlist contiene códigos repetidos.';
		}
		foreach ($allowlist['codes'] as $c) {
			if (! isset($active[$c])) {
				$reasons[] = "Propiedad seleccionada para publicar ({$c}) no tiene status=1 en este CSV.";
			} elseif ($active[$c]['critical_price']) {
				$reasons[] = "Propiedad con advertencia crítica de precio ({$c}) fue seleccionada para publicarse.";
			}
		}
		if (in_array('MCR-807', $allowlist['codes'], true)) {
			$reasons[] = 'MCR-807 fue seleccionada para publicarse (prohibido explícitamente).';
		}

		return $reasons;
	}

	// -----------------------------------------------------------------
	// Selección determinística de las 5 propiedades de prueba
	// -----------------------------------------------------------------

	private function resolve_allowlist(array $active): array
	{
		$stored = get_option(self::ALLOWLIST_OPTION);

		if (is_array($stored) && count($stored) === 5) {
			$still_valid = array_filter($stored, static fn ($c) => isset($active[$c]) && ! $active[$c]['critical_price']);

			if (count($still_valid) === 5) {
				return [
					'codes'   => array_values($stored),
					'source'  => 'reused',
					'reasons' => array_fill_keys($stored, 'Reutilizada de una ejecución anterior (opción ' . self::ALLOWLIST_OPTION . ')'),
				];
			}
		}

		$codes = array_keys($active);
		sort($codes, SORT_STRING);

		$picked  = [];
		$reasons = [];

		$pick = function (callable $criteria, string $reason) use (&$picked, &$reasons, $codes, $active): void {
			foreach ($codes as $c) {
				if (in_array($c, $picked, true) || $active[$c]['critical_price']) {
					continue;
				}
				if ($criteria($active[$c])) {
					$picked[]         = $c;
					$reasons[$c]      = $reason;

					return;
				}
			}
		};

		$pick(
			static fn ($r) => $r['post_type'] === 'propiedades' && $r['tipo_operacion'] === 'Venta' && ! $r['missing_title']
				&& ($r['taxes_usd'] ?? 0) > 0 && $r['has_video_es'] && $r['has_video_en'],
			'Venta con título original, taxes>0, video ES y video EN'
		);

		$pick(
			static fn ($r) => $r['post_type'] === 'propiedades' && $r['tipo_operacion'] === 'Venta' && ! $r['missing_title']
				&& ! $r['has_video_es'] && ! $r['has_video_en']
				&& $r['precio'] !== null && $r['recamaras'] !== null && $r['banos'] !== '' && $r['terreno_m2'] !== null && $r['construccion_m2'] !== null,
			'Venta con título original, sin video, datos completos'
		);

		$pick(
			static fn ($r) => $r['missing_title'] && $r['post_type'] === 'propiedades' && $r['precio'] !== null,
			'Sin título original ("Propiedad {codigo}"), datos suficientes para validar el template'
		);

		$pick(
			static fn ($r) => $r['post_type'] === 'propiedades' && $r['tipo_operacion'] === 'Renta' && $r['codigo'] !== 'MCR-807' && $r['precio'] !== null,
			'Renta con precio dentro de rango razonable (no MCR-807)'
		);

		$pick(
			static fn ($r) => $r['codigo'] === 'MT-214',
			'Terreno de referencia MT-214 (recámaras/baños vacíos, valida robustez del template)'
		);

		if (count($picked) < 5) {
			$pick(static fn ($r) => $r['post_type'] === 'terrenos', 'Terreno alternativo (MT-214 no disponible)');
		}
		if (count($picked) < 5) {
			$pick(
				static fn ($r) => $r['recamaras'] === null || $r['banos'] === '',
				'Caso especial: campos vacíos, valida robustez del template'
			);
		}
		if (count($picked) < 5) {
			$pick(static fn ($r) => true, 'Relleno determinístico (código ascendente) — no se encontraron suficientes candidatos ideales');
		}

		return ['codes' => $picked, 'source' => 'computed', 'reasons' => $reasons];
	}

	// -----------------------------------------------------------------
	// Reportes
	// -----------------------------------------------------------------

	private function count_warnings(array $warnings, string $category): int
	{
		return count(array_filter($warnings, static fn ($w) => $w[1] === $category));
	}

	private function print_dry_run_report(array $parsed, array $result, array $zona_map, array $allowlist, array $stop_reasons): void
	{
		$active = $result['active'];
		$w      = $result['warnings'];

		WP_CLI::log('');
		WP_CLI::log('=== CSV ===');
		WP_CLI::log("Archivo: {$this->csv_basename}");
		WP_CLI::log("Total filas: {$parsed['total_rows']}");
		WP_CLI::log("Columnas: {$parsed['column_count']}");
		WP_CLI::log('Separador: ; | Encoding: Windows-1252 -> UTF-8');
		WP_CLI::log('Errores de parsing: 0');

		WP_CLI::log('');
		WP_CLI::log('=== Status ===');
		foreach (['1', '3', '4'] as $s) {
			WP_CLI::log("status {$s}: " . ($result['status_counts'][$s] ?? 0));
		}
		if (($result['status_counts']['other'] ?? 0) > 0) {
			WP_CLI::log('status otro/ilegible: ' . $result['status_counts']['other']);
		}

		$venta = count(array_filter($active, static fn ($r) => $r['tipo_operacion'] === 'Venta'));
		$renta = count(array_filter($active, static fn ($r) => $r['tipo_operacion'] === 'Renta'));

		WP_CLI::log('');
		WP_CLI::log('=== Operación (activas) ===');
		WP_CLI::log("Ventas activas: {$venta}");
		WP_CLI::log("Rentas activas: {$renta}");

		$existing = 0;
		$news     = 0;
		foreach (array_keys($active) as $codigo) {
			$this->find_existing($codigo) > 0 ? $existing++ : $news++;
		}

		WP_CLI::log('');
		WP_CLI::log('=== Identidad ===');
		WP_CLI::log('Códigos activos únicos: ' . count($active));
		WP_CLI::log('Códigos duplicados: ' . (empty($result['duplicates']) ? '0' : implode(', ', array_unique($result['duplicates']))));
		WP_CLI::log("Propiedades existentes (se actualizarían): {$existing}");
		WP_CLI::log("Propiedades nuevas (se crearían): {$news}");

		$mt = count(array_filter($active, static fn ($r) => $r['post_type'] === 'terrenos'));
		WP_CLI::log("Ruteo de CPT: " . (count($active) - $mt) . " -> 'propiedades', {$mt} -> 'terrenos' (prefijo de código MT-)");

		WP_CLI::log('');
		WP_CLI::log('=== Campos ===');
		WP_CLI::log('Títulos presentes: ' . count(array_filter($active, static fn ($r) => ! $r['missing_title'])));
		WP_CLI::log('Títulos provisionales ("Propiedad {codigo}"): ' . $this->count_warnings($w, 'missing_title'));
		WP_CLI::log('Descripciones vacías: ' . $this->count_warnings($w, 'missing_description'));
		WP_CLI::log('Recámaras vacías: ' . $this->count_warnings($w, 'missing_bedrooms'));
		WP_CLI::log('Baños vacíos: ' . $this->count_warnings($w, 'missing_bathrooms'));
		WP_CLI::log('Terreno = 0: ' . $this->count_warnings($w, 'zero_terreno'));
		WP_CLI::log('Construcción = 0: ' . $this->count_warnings($w, 'zero_construccion'));

		$zonas_found = array_unique(array_column($active, 'zona_legacy'));
		sort($zonas_found);
		WP_CLI::log('Zonas legacy encontradas: ' . implode(', ', $zonas_found));
		$zonas_unmapped = array_unique(array_map(static fn ($x) => $x[2], array_filter($w, static fn ($x) => $x[1] === 'zone_mapping_error')));
		WP_CLI::log('Filas con zona sin mapping: ' . $this->count_warnings($w, 'zone_mapping_error'));

		$taxes_pos   = count(array_filter($active, static fn ($r) => ($r['taxes_usd'] ?? null) !== null && $r['taxes_usd'] > 0));
		$taxes_zero  = count(array_filter($active, static fn ($r) => ($r['taxes_usd'] ?? null) === 0.0));
		$taxes_empty = count(array_filter($active, static fn ($r) => $r['taxes_usd'] === null));

		WP_CLI::log('');
		WP_CLI::log('=== Taxes ===');
		WP_CLI::log("Taxes > 0: {$taxes_pos}");
		WP_CLI::log("Taxes = 0: {$taxes_zero}");
		WP_CLI::log("Taxes vacíos: {$taxes_empty}");
		WP_CLI::log('Warnings taxes > 20,000: ' . $this->count_warnings($w, 'tax_warning'));

		$video_es_val = count(array_filter($active, static fn ($r) => $r['has_video_es']));
		$video_es_inv = $this->count_warnings($w, 'invalid_video_es');
		$video_en_val = count(array_filter($active, static fn ($r) => $r['has_video_en']));

		WP_CLI::log('');
		WP_CLI::log('=== Videos ===');
		WP_CLI::log('Video ES válido: ' . $video_es_val);
		WP_CLI::log('Video ES inválido: ' . $video_es_inv);
		WP_CLI::log('Video EN válido: ' . $video_en_val);
		WP_CLI::log('Video EN inválido: ' . $this->count_warnings($w, 'invalid_video_en'));

		WP_CLI::log('');
		WP_CLI::log('=== Publicación ===');
		WP_CLI::log('Quedarían publicadas: ' . count($allowlist['codes']) . ' (fuente: ' . $allowlist['source'] . ')');
		WP_CLI::log('Quedarían en borrador: ' . (count($active) - count($allowlist['codes'])));
		WP_CLI::log('Allowlist:');
		foreach ($allowlist['codes'] as $c) {
			$r = $active[$c] ?? null;
			$motivo = $allowlist['reasons'][$c] ?? '(sin motivo registrado)';
			if ($r) {
				WP_CLI::log(sprintf(
					'  - %s | %s | %s | precio=%s | zona=%s | rec=%s | banos=%s | terreno=%s | construccion=%s | taxes=%s | video_es=%s | video_en=%s',
					$c,
					$r['titulo'],
					$r['tipo_operacion'] ?? '—',
					$r['precio'] ?? '—',
					$r['zona_legacy'],
					$r['recamaras'] ?? '—',
					$r['banos'] !== '' ? $r['banos'] : '—',
					$r['terreno_m2'] ?? '—',
					$r['construccion_m2'] ?? '—',
					$r['taxes_usd'] ?? '—',
					$r['has_video_es'] ? 'sí' : 'no',
					$r['has_video_en'] ? 'sí' : 'no'
				));
				WP_CLI::log("    motivo: {$motivo}");
			}
		}

		WP_CLI::log('');
		WP_CLI::log('=== Advertencias detectadas ===');
		foreach (['critical_price_warning', 'invalid_video_es', 'missing_bedrooms', 'missing_bathrooms', 'zone_mapping_error', 'encoding_fixed'] as $cat) {
			$matches = array_filter($w, static fn ($x) => $x[1] === $cat);
			if (empty($matches)) {
				continue;
			}
			WP_CLI::log("[{$cat}]");
			foreach ($matches as [$codigo, , $msg]) {
				WP_CLI::log("  {$codigo}: {$msg}");
			}
		}

		if (! empty($stop_reasons)) {
			WP_CLI::log('');
			WP_CLI::log('=== BLOQUEOS (no se ejecutarán cambios reales) ===');
			foreach ($stop_reasons as $r) {
				WP_CLI::log("  ✗ {$r}");
			}
		}
	}

	private function print_final_report(array $parsed, array $result, array $zona_map, array $allowlist, array $exec_result): void
	{
		$active      = $result['active'];
		$venta       = count(array_filter($active, static fn ($r) => $r['tipo_operacion'] === 'Venta'));
		$renta       = count(array_filter($active, static fn ($r) => $r['tipo_operacion'] === 'Renta'));
		$taxes_pos   = count(array_filter($active, static fn ($r) => ($r['taxes_usd'] ?? null) !== null && $r['taxes_usd'] > 0));
		$taxes_zero  = count(array_filter($active, static fn ($r) => ($r['taxes_usd'] ?? null) === 0.0));
		$taxes_empty = count(array_filter($active, static fn ($r) => $r['taxes_usd'] === null));

		WP_CLI::log('');
		WP_CLI::log('=== REPORTE FINAL ===');
		WP_CLI::log("Archivo: {$this->csv_basename}");
		WP_CLI::log('');
		WP_CLI::log("Total CSV: {$parsed['total_rows']}");
		WP_CLI::log('Status 1: ' . $result['status_counts']['1']);
		WP_CLI::log('Status 3: ' . $result['status_counts']['3']);
		WP_CLI::log('Status 4: ' . $result['status_counts']['4']);
		WP_CLI::log('');
		WP_CLI::log("Ventas activas: {$venta}");
		WP_CLI::log("Rentas activas: {$renta}");
		WP_CLI::log('');
		WP_CLI::log('Creadas: ' . $exec_result['created']);
		WP_CLI::log('Actualizadas: ' . $exec_result['updated']);
		WP_CLI::log('Desactivadas por cambio de status: ' . count($exec_result['deactivated']));
		WP_CLI::log('Errores: ' . $exec_result['errors']);
		WP_CLI::log('');
		WP_CLI::log('Publicadas para pruebas: ' . count($allowlist['codes']));
		WP_CLI::log('Guardadas como borrador: ' . (count($active) - count($allowlist['codes'])));
		WP_CLI::log('');
		WP_CLI::log('Códigos publicados:');
		foreach ($allowlist['codes'] as $c) {
			WP_CLI::log("- {$c}");
		}
		WP_CLI::log('');
		WP_CLI::log('Títulos provisionales: ' . $this->count_warnings($result['warnings'], 'missing_title'));
		WP_CLI::log('Recámaras vacías: ' . $this->count_warnings($result['warnings'], 'missing_bedrooms'));
		WP_CLI::log('Baños vacíos: ' . $this->count_warnings($result['warnings'], 'missing_bathrooms'));
		WP_CLI::log('');
		WP_CLI::log("Taxes > 0: {$taxes_pos}");
		WP_CLI::log("Taxes = 0: {$taxes_zero}");
		WP_CLI::log("Taxes vacíos: {$taxes_empty}");
		WP_CLI::log('Warnings de taxes: ' . $this->count_warnings($result['warnings'], 'tax_warning'));
		WP_CLI::log('');
		WP_CLI::log('Video ES válido: ' . count(array_filter($active, static fn ($r) => $r['has_video_es'])));
		WP_CLI::log('Video ES inválido: ' . $this->count_warnings($result['warnings'], 'invalid_video_es'));
		WP_CLI::log('Video EN válido: ' . count(array_filter($active, static fn ($r) => $r['has_video_en'])));
		WP_CLI::log('');
		WP_CLI::log('Warnings de precio: ' . $this->count_warnings($result['warnings'], 'critical_price_warning'));
		WP_CLI::log('Warnings de encoding: ' . $this->count_warnings($result['warnings'], 'encoding_fixed'));
		WP_CLI::log('Warnings de zona: ' . $this->count_warnings($result['warnings'], 'zone_mapping_error'));

		WP_CLI::log('');
		WP_CLI::log('| Código | Título | Operación | Precio | Zona | Taxes | Video ES | Video EN | Motivo |');
		WP_CLI::log('| --- | --- | --- | ---: | --- | ---: | --- | --- | --- |');
		foreach ($allowlist['codes'] as $c) {
			$r      = $active[$c];
			$motivo = $allowlist['reasons'][$c] ?? '';
			WP_CLI::log(sprintf(
				'| %s | %s | %s | %s | %s | %s | %s | %s | %s |',
				$c,
				$r['titulo'],
				$r['tipo_operacion'] ?? '—',
				$r['precio'] ?? '—',
				$r['zona_legacy'],
				$r['taxes_usd'] ?? '—',
				$r['has_video_es'] ? 'sí' : 'no',
				$r['has_video_en'] ? 'sí' : 'no',
				$motivo
			));
		}
	}

	// -----------------------------------------------------------------
	// Identidad / búsqueda idempotente
	// -----------------------------------------------------------------

	private function find_existing(string $codigo): int
	{
		$q = new WP_Query([
			'post_type'      => ['propiedades', 'terrenos'],
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

	private function execute_import(array $active, array $publish_codes, array $zona_map): array
	{
		$created = 0;
		$updated = 0;
		$errors  = 0;

		foreach ($active as $codigo => $rec) {
			$existing_id = $this->find_existing($codigo);
			$is_new      = $existing_id === 0;

			if (class_exists('Momentum_Property_Translation_Service')) {
				Momentum_Property_Translation_Service::defer_auto_trigger();
			}

			$target_status = in_array($codigo, $publish_codes, true) ? 'publish' : 'draft';

			$postarr = [
				'post_type'    => $rec['post_type'],
				'post_title'   => $rec['titulo'],
				'post_content' => $rec['descripcion'],
				'post_status'  => $target_status,
			];

			if ($is_new) {
				$result = wp_insert_post($postarr, true);
			} else {
				$postarr['ID'] = $existing_id;
				$result        = wp_update_post($postarr, true);
			}

			if (is_wp_error($result)) {
				$errors++;
				WP_CLI::warning("Error guardando {$codigo}: " . $result->get_error_message());
				if (class_exists('Momentum_Property_Translation_Service')) {
					Momentum_Property_Translation_Service::resume_auto_trigger();
				}

				continue;
			}

			$post_id = (int) $result;

			$this->save_meta($post_id, $rec);

			update_post_meta($post_id, self::SOURCE_META, $this->csv_basename);
			update_post_meta($post_id, self::IMPORTED_AT_META, current_time('mysql'));

			if (class_exists('Momentum_Property_Translation_Service')) {
				Momentum_Property_Translation_Service::handle_save($post_id, get_post($post_id), ! $is_new);
				Momentum_Property_Translation_Service::resume_auto_trigger();
			}

			$is_new ? $created++ : $updated++;
		}

		$deactivated = $this->reconcile_inactive($active);

		return compact('created', 'updated', 'deactivated', 'errors');
	}

	private function save_meta(int $post_id, array $rec): void
	{
		update_post_meta($post_id, 'codigo_propiedad', $rec['codigo']);

		if ($rec['precio'] !== null) {
			update_post_meta($post_id, 'precio', $rec['precio']);
		}

		if ($rec['post_type'] === 'propiedades') {
			if ($rec['tipo_operacion'] !== null) {
				update_post_meta($post_id, 'tipo_operacion', $rec['tipo_operacion']);
			}
			if ($rec['recamaras'] !== null) {
				update_post_meta($post_id, 'recamaras', $rec['recamaras']);
			} else {
				delete_post_meta($post_id, 'recamaras');
			}
			if ($rec['banos'] !== '') {
				update_post_meta($post_id, 'banos', $rec['banos']);
			} else {
				delete_post_meta($post_id, 'banos');
			}
			if ($rec['construccion_m2'] !== null) {
				update_post_meta($post_id, 'construccion_m2', $rec['construccion_m2']);
			}
		}

		if ($rec['terreno_m2'] !== null) {
			update_post_meta($post_id, 'terreno_m2', $rec['terreno_m2']);
		}

		if ($rec['zona_slug']) {
			update_post_meta($post_id, 'zona', $rec['zona_slug']);
		}

		if ($rec['taxes_usd'] !== null) {
			update_post_meta($post_id, 'taxes_usd', $rec['taxes_usd']);
		} else {
			delete_post_meta($post_id, 'taxes_usd');
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

		// Disponibilidad comercial (independiente de post_status). Nunca
		// resucita una propiedad ya cerrada (Vendida/Rentada) solo porque el
		// legacy todavía la marca status=1.
		$current_estado = get_post_meta($post_id, 'estado', true);
		if (! in_array($current_estado, self::CLOSED_ESTADOS, true)) {
			update_post_meta($post_id, 'estado', 'Disponible');
		}
	}

	/**
	 * Propiedades/Terrenos importados por esta migración (marcados con
	 * self::SOURCE_META) cuyo código ya no aparece como activo en este CSV:
	 * pasan a borrador. No se toca 'estado' -se conserva el último valor
	 * real conocido- porque el proyecto no tiene un valor de 'estado' que
	 * signifique genéricamente "ya no disponible"; post_status=draft ya
	 * basta para sacarlas de listados públicos y sitemap.
	 */
	private function reconcile_inactive(array $active): array
	{
		$deactivated = [];

		$q = new WP_Query([
			'post_type'      => ['propiedades', 'terrenos'],
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				['key' => self::SOURCE_META, 'compare' => 'EXISTS'],
			],
		]);

		$default_lang = function_exists('momentum_default_language') ? momentum_default_language() : '';

		foreach ($q->posts as $id) {
			$id = (int) $id;

			if ($default_lang !== '' && function_exists('momentum_get_post_language') && momentum_get_post_language($id) !== $default_lang) {
				continue;
			}

			$codigo = (string) get_post_meta($id, 'codigo_propiedad', true);

			if ($codigo === '' || isset($active[$codigo])) {
				continue;
			}

			if (get_post_status($id) !== 'draft') {
				wp_update_post(['ID' => $id, 'post_status' => 'draft']);
				$deactivated[] = $codigo;
			}
		}

		return $deactivated;
	}

	// -----------------------------------------------------------------
	// Verificación posterior
	// -----------------------------------------------------------------

	private function verify_after_import(array $active, array $publish_codes): void
	{
		WP_CLI::log('');
		WP_CLI::log('=== Verificación posterior ===');

		$found      = 0;
		$publish_ct = 0;
		$draft_ct   = 0;
		$missing    = [];

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
		}

		WP_CLI::log("Propiedades activas encontradas en WP: {$found} / " . count($active));
		WP_CLI::log("post_status=publish: {$publish_ct} (esperado 5)");
		WP_CLI::log("post_status=draft: {$draft_ct} (esperado " . (count($active) - 5) . ')');

		if (! empty($missing)) {
			WP_CLI::warning('No se encontraron después de importar: ' . implode(', ', $missing));
		}

		foreach ($publish_codes as $c) {
			$id = $this->find_existing($c);
			if ($id && get_post_status($id) !== 'publish') {
				WP_CLI::warning("{$c} está en la allowlist pero no quedó publicada.");
			}
		}
	}
}

WP_CLI::add_command('momentum import-properties', 'Momentum_Admin_CLI_Import_Properties');
