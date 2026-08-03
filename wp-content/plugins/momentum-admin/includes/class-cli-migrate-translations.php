<?php
/**
 * Comando WP-CLI: migra Propiedades y Terrenos existentes al sistema de
 * traducción automática ES→EN.
 *
 * Uso: wp momentum migrate-translations [--post-type=propiedades|terrenos|all]
 *
 * Deliberadamente NO reimplementa ninguna lógica de traducción, sincronización
 * de campos, ni de enlace con Polylang: por cada ficha en el idioma por
 * defecto, llama a Momentum_Property_Translation_Service::handle_save()
 * -exactamente el mismo método que ya corre en cada guardado normal desde
 * wp-admin o /admin/propiedades- y solo agrega estadísticas escuchando la
 * acción 'momentum_translation_attempted' que ese servicio ya dispara.
 * Idempotente: correrlo de nuevo sobre fichas ya migradas no crea
 * duplicados ni vuelve a traducir lo que ya esté completo y sin cambios.
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

class Momentum_Admin_CLI_Migrate_Translations
{
	private array $stats;

	/**
	 * Migra Propiedades/Terrenos existentes al sistema de traducción
	 * automática, reutilizando por completo Momentum_Property_Translation_Service.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : propiedades, terrenos, o all (default: all).
	 *
	 * ## EXAMPLES
	 *
	 *     wp momentum migrate-translations
	 *     wp momentum migrate-translations --post-type=propiedades
	 */
	public function __invoke($args, $assoc_args)
	{
		if (! class_exists('Momentum_Property_Translation_Service')) {
			WP_CLI::error('Momentum_Property_Translation_Service no está disponible (¿el tema Momentum está activo?).');

			return;
		}

		if (! function_exists('momentum_is_multilingual') || ! momentum_is_multilingual()) {
			WP_CLI::error('Polylang no está activo o no tiene al menos 2 idiomas configurados.');

			return;
		}

		$post_type_arg = $assoc_args['post-type'] ?? 'all';
		$post_types    = $post_type_arg === 'all' ? ['propiedades', 'terrenos'] : [$post_type_arg];

		$provider_ready = function_exists('momentum_get_translation_provider') && momentum_get_translation_provider() !== null;
		WP_CLI::log($provider_ready
			? 'Proveedor de traducción: configurado (DeepL) — se traducirá el contenido de texto.'
			: 'Proveedor de traducción: NO configurado — las traducciones se crearán/actualizarán como borrador pendiente, sin texto (ver Admin > General).');

		$this->stats = [
			'processed' => 0,
			'created'   => 0,
			'reused'    => 0,
			'attempts'  => 0,
			'chars'     => 0,
			'errors'    => [],
		];

		add_action('momentum_translation_attempted', [$this, 'on_translation_attempted'], 10, 6);

		$started_at = microtime(true);

		foreach ($post_types as $post_type) {
			$this->migrate_post_type($post_type);
		}

		remove_action('momentum_translation_attempted', [$this, 'on_translation_attempted'], 10);

		$elapsed = round(microtime(true) - $started_at, 1);

		WP_CLI::log('');
		WP_CLI::log('=== Resumen de la migración ===');
		WP_CLI::log(sprintf('Fichas procesadas (idioma por defecto): %d', $this->stats['processed']));
		WP_CLI::log(sprintf('Traducciones creadas: %d', $this->stats['created']));
		WP_CLI::log(sprintf('Traducciones ya existentes (reutilizadas, no duplicadas): %d', $this->stats['reused']));
		WP_CLI::log(sprintf('Llamadas reales a DeepL: %d', $this->stats['attempts']));
		WP_CLI::log(sprintf('Caracteres consumidos: %d', $this->stats['chars']));
		WP_CLI::log(sprintf('Tiempo total: %ss', $elapsed));
		WP_CLI::log(sprintf('Errores: %d', count($this->stats['errors'])));

		if ($this->stats['errors']) {
			WP_CLI\Utils\format_items('table', $this->stats['errors'], ['ID', 'title', 'motivo']);
		}

		// Se guarda en un option temporal para que el reporte final (fuera de
		// este comando) pueda leer los números exactos sin volver a parsear
		// el log de WP-CLI.
		update_option('momentum_last_translation_migration_stats', [
			'stats'   => $this->stats,
			'elapsed' => $elapsed,
			'date'    => current_time('mysql'),
		], false);

		WP_CLI::success('Migración completada.');
	}

	/**
	 * Escucha 'momentum_translation_attempted' (disparada por
	 * Momentum_Property_Translation_Service::sync_textual_fields() en cada
	 * llamada real al proveedor) para acumular caracteres/errores sin
	 * duplicar ninguna lógica de traducción.
	 */
	public function on_translation_attempted(int $post_id, string $post_type, string $lang, int $chars, int $duration_ms, string $result): void
	{
		$this->stats['attempts']++;
		$this->stats['chars'] += $chars;

		if ($result === 'error') {
			$this->stats['errors'][] = [
				'ID'     => $post_id,
				'title'  => get_the_title($post_id),
				'motivo' => 'DeepL falló o devolvió una respuesta inválida para este idioma (' . $lang . '); ver debug.log para el detalle técnico.',
			];
		}
	}

	private function migrate_post_type(string $post_type): void
	{
		$ids = get_posts([
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		]);

		$default_lang        = momentum_default_language();
		$secondary_languages = momentum_get_secondary_languages();

		WP_CLI::log(sprintf('--- %s: %d fichas encontradas ---', $post_type, count($ids)));

		foreach ($ids as $id) {
			$lang = momentum_get_post_language($id);

			if ($lang === '') {
				momentum_set_post_language($id, $default_lang);
				$lang = $default_lang;
			}

			// Solo se migran fichas en el idioma por defecto: una traducción
			// nunca origina su propia sincronización "hacia adelante" -mismo
			// criterio que Momentum_Property_Translation_Service::handle_save()-.
			if ($lang !== $default_lang) {
				continue;
			}

			$this->stats['processed']++;

			$existed_before = [];
			foreach ($secondary_languages as $target_lang) {
				$existed_before[$target_lang] = momentum_find_translation($id, $target_lang) > 0;
			}

			try {
				Momentum_Property_Translation_Service::handle_save($id, get_post($id), true);
			} catch (\Throwable $e) {
				$this->stats['errors'][] = [
					'ID'     => $id,
					'title'  => get_the_title($id),
					'motivo' => 'Excepción no controlada: ' . $e->getMessage(),
				];
				WP_CLI::warning(sprintf('#%d "%s": excepción — %s (se continúa con las demás)', $id, get_the_title($id), $e->getMessage()));

				continue;
			}

			$created_here = 0;
			$reused_here  = 0;

			foreach ($secondary_languages as $target_lang) {
				if ($existed_before[$target_lang]) {
					$reused_here++;
				} elseif (momentum_find_translation($id, $target_lang) > 0) {
					$created_here++;
				}
			}

			$this->stats['created'] += $created_here;
			$this->stats['reused']  += $reused_here;

			WP_CLI::log(sprintf(
				'#%d "%s" — %s',
				$id,
				get_the_title($id),
				$created_here > 0 ? 'traducción creada' : 'ya existía, sincronizada'
			));
		}
	}
}

WP_CLI::add_command('momentum migrate-translations', 'Momentum_Admin_CLI_Migrate_Translations');
