<?php
/**
 * Comando WP-CLI: crea propiedades de PRUEBA (post_status=publish) hasta
 * alcanzar un total de propiedades publicadas objetivo, para poder verificar
 * la paginación del listado público sin depender de datos reales.
 *
 * Uso:
 *   wp momentum seed-test-properties [--target=25] [--dry-run]
 *   wp momentum delete-test-properties [--yes]   (preparado, no se ejecuta solo)
 *
 * Reglas de seguridad:
 * - Nunca toca propiedades reales: solo crea posts nuevos.
 * - Cada propiedad de prueba se identifica por post meta 'is_test_property' = 1
 *   (no por título) más un slug determinístico 'propiedad-de-prueba-NN'.
 * - Idempotente: cuenta publicadas actuales (reales + prueba) y solo crea
 *   las que falten para llegar al objetivo. Si ya se alcanzó, no crea nada.
 * - No descarga ni duplica imágenes: reutiliza attachments ya existentes en
 *   la biblioteca de medios ("Propiedad - foto NN").
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

class Momentum_Admin_CLI_Seed_Test_Properties
{
	const IS_TEST_META  = 'is_test_property';
	const SEQ_META      = 'test_property_seq';
	const SLUG_PREFIX   = 'propiedad-de-prueba-';

	private $zonas    = ['cdmx', 'cuautla', 'huitzilac', 'lomas-de-cocoyoc', 'oaxtepec', 'sitio-del-sol', 'tequesquitengo', 'tlayacapan', 'yautepec'];
	private $estados   = ['Disponible', 'Disponible', 'Disponible', 'Preventa', 'Apartada', 'Remate', 'Vendida', 'Rentada'];
	private $precios    = [1650000, 2100000, 2400000, 2950000, 3300000, 3850000, 4200000, 4750000, 5200000, 5900000, 6300000, 6850000, 7400000, 8100000, 8900000, 9500000, 10200000, 11500000, 12900000, 14300000];
	private $recamaras  = [1, 2, 2, 3, 3, 3, 4, 4, 5, 6];
	private $banos      = [1, 1.5, 2, 2, 2.5, 3, 3.5, 4, 4.5, 5];
	private $terrenos    = [180, 220, 300, 350, 400, 450, 500, 600, 700, 800, 900, 1000];
	private $descripciones = [
		'Propiedad de prueba generada para verificar la paginación del listado. Cuenta con acabados de calidad y buena ubicación dentro de la zona.',
		'Espacio pensado para familias, con áreas comunes cercanas y fácil acceso. Propiedad de prueba, no representa un inmueble real en venta.',
		'Diseño moderno con espacios amplios y bien iluminados. Este registro es de prueba y se usa solo para validar el sistema de paginación.',
		'Excelente opción de inversión dentro de la región. Propiedad de prueba creada por el seeder de Momentum Admin.',
	];

	/**
	 * Crea propiedades de prueba hasta alcanzar el total de publicadas objetivo.
	 *
	 * ## OPTIONS
	 *
	 * [--target=<n>]
	 * : Total de propiedades PUBLICADAS (reales + prueba) a alcanzar. Por defecto 25.
	 *
	 * [--dry-run]
	 * : Muestra qué se crearía sin escribir cambios.
	 *
	 * ## EXAMPLES
	 *
	 *     wp momentum seed-test-properties
	 *     wp momentum seed-test-properties --target=25 --dry-run
	 */
	public function seed_test_properties($args, $assoc_args)
	{
		if (! post_type_exists('propiedades') || ! function_exists('momentum_propiedad_meta_schema')) {
			WP_CLI::error('El CPT "propiedades" no está disponible (¿el tema Momentum está activo?).');

			return;
		}

		$target  = isset($assoc_args['target']) ? max(0, (int) $assoc_args['target']) : 25;
		$dry_run = isset($assoc_args['dry-run']);

		$published_now = (int) (new WP_Query([
			'post_type'      => 'propiedades',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		]))->found_posts;

		$needed = max(0, $target - $published_now);

		WP_CLI::log(sprintf('Propiedades publicadas actuales: %d', $published_now));
		WP_CLI::log(sprintf('Objetivo: %d', $target));

		if ($needed === 0) {
			WP_CLI::success('Ya se alcanzó (o se superó) el objetivo. No se crea ninguna propiedad.');

			return;
		}

		WP_CLI::log(sprintf('%s: %d', $dry_run ? 'Se crearían' : 'Se crearán', $needed));

		$next_seq = $this->next_sequence_number();
		$images   = $this->test_images();
		$created  = [];
		$skipped  = [];

		for ($i = 0; $i < $needed; $i++) {
			$seq   = $next_seq + $i;
			$slug  = self::SLUG_PREFIX . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

			if (get_page_by_path($slug, OBJECT, 'propiedades')) {
				$skipped[] = $slug;
				continue;
			}

			if ($dry_run) {
				$created[] = ['seq' => $seq, 'slug' => $slug];
				continue;
			}

			$post_id = $this->create_property($seq, $slug, $images);

			if (is_wp_error($post_id)) {
				WP_CLI::warning(sprintf('No se pudo crear %s: %s', $slug, $post_id->get_error_message()));
				continue;
			}

			$created[] = ['seq' => $seq, 'slug' => $slug, 'ID' => $post_id];
		}

		if ($created) {
			WP_CLI\Utils\format_items('table', $created, array_keys($created[0]));
		}

		if ($skipped) {
			WP_CLI::log(sprintf('Ya existían (se omitieron, sin duplicar): %s', implode(', ', $skipped)));
		}

		if ($dry_run) {
			WP_CLI::success('Simulación completada (--dry-run): no se escribió ningún cambio.');

			return;
		}

		WP_CLI::success(sprintf('Creadas %d propiedades de prueba.', count($created)));
	}

	/**
	 * Elimina EXCLUSIVAMENTE las propiedades marcadas is_test_property=1.
	 * Preparado para uso futuro; no se invoca desde ningún otro lugar del
	 * código y nunca se ejecuta automáticamente.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Confirma el borrado sin preguntar. Sin esta bandera solo lista qué se borraría.
	 *
	 * ## EXAMPLES
	 *
	 *     wp momentum delete-test-properties
	 *     wp momentum delete-test-properties --yes
	 */
	public function delete_test_properties($args, $assoc_args)
	{
		$posts = get_posts([
			'post_type'      => 'propiedades',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				['key' => self::IS_TEST_META, 'value' => '1', 'compare' => '='],
			],
		]);

		if (! $posts) {
			WP_CLI::success('No hay propiedades de prueba que eliminar.');

			return;
		}

		WP_CLI::log(sprintf('Propiedades de prueba encontradas: %d', count($posts)));

		if (! isset($assoc_args['yes'])) {
			WP_CLI::log('Ejecuta con --yes para eliminarlas permanentemente (wp_delete_post, sin pasar por la papelera).');

			return;
		}

		foreach ($posts as $post_id) {
			wp_delete_post($post_id, true);
		}

		WP_CLI::success(sprintf('Eliminadas %d propiedades de prueba.', count($posts)));
	}

	private function next_sequence_number(): int
	{
		$existing = get_posts([
			'post_type'      => 'propiedades',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => self::SEQ_META, // phpcs:ignore WordPress.DB.SlowDBQuery
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				['key' => self::IS_TEST_META, 'value' => '1', 'compare' => '='],
			],
		]);

		if (! $existing) {
			return 1;
		}

		return ((int) get_post_meta($existing[0], self::SEQ_META, true)) + 1;
	}

	private function test_images(): array
	{
		$attachments = get_posts([
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			's'              => 'Propiedad - foto',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);

		return $attachments ?: [];
	}

	private function create_property(int $seq, string $slug, array $images)
	{
		$title = sprintf('Propiedad de prueba %02d', $seq);
		$idx   = $seq - 1;

		$content = $this->descripciones[$idx % count($this->descripciones)];

		$post_id = wp_insert_post([
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'propiedades',
		], true);

		if (is_wp_error($post_id)) {
			return $post_id;
		}

		$terreno       = $this->terrenos[$idx % count($this->terrenos)];
		$construccion  = (int) round($terreno * 0.55);

		update_post_meta($post_id, 'precio', (float) $this->precios[$idx % count($this->precios)]);
		update_post_meta($post_id, 'tipo_operacion', 'Venta');
		update_post_meta($post_id, 'recamaras', (float) $this->recamaras[$idx % count($this->recamaras)]);
		update_post_meta($post_id, 'banos', (float) $this->banos[$idx % count($this->banos)]);
		update_post_meta($post_id, 'terreno_m2', (float) $terreno);
		update_post_meta($post_id, 'construccion_m2', (float) $construccion);
		update_post_meta($post_id, 'zona', $this->zonas[$idx % count($this->zonas)]);
		update_post_meta($post_id, 'estado', $this->estados[$idx % count($this->estados)]);
		update_post_meta($post_id, 'codigo_propiedad', sprintf('MC-TEST-%02d', $seq));

		update_post_meta($post_id, self::IS_TEST_META, 1);
		update_post_meta($post_id, self::SEQ_META, $seq);

		if ($images) {
			set_post_thumbnail($post_id, $images[$idx % count($images)]);
		}

		return $post_id;
	}
}

WP_CLI::add_command('momentum seed-test-properties', [new Momentum_Admin_CLI_Seed_Test_Properties(), 'seed_test_properties']);
WP_CLI::add_command('momentum delete-test-properties', [new Momentum_Admin_CLI_Seed_Test_Properties(), 'delete_test_properties']);
