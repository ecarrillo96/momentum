<?php
/**
 * Comando WP-CLI: migra 'ciudad'/'ubicacion' (deprecados) al nuevo campo
 * 'zona' cuando hay una coincidencia clara y segura con una de sus opciones.
 *
 * Uso: wp momentum migrate-zona [--dry-run]
 *
 * Reglas de seguridad (ver inc/cpt/propiedades.php para el esquema):
 * - Nunca sobreescribe una 'zona' que ya tenga valor.
 * - Nunca borra ni modifica 'ciudad' ni 'ubicacion'.
 * - Solo migra cuando 'ciudad' o 'ubicacion' apuntan sin ambigüedad a una
 *   sola zona permitida; si ambos campos sugieren zonas distintas, o si
 *   ninguno coincide, la propiedad se deja para revisión manual.
 * - Idempotente: se puede ejecutar varias veces sin duplicar cambios ni
 *   generar efectos distintos (las ya migradas se listan como "ya tenían zona").
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

class Momentum_Admin_CLI_Migrate_Zona
{
	/**
	 * Migra ciudad/ubicacion al nuevo campo zona cuando hay coincidencia segura.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Muestra qué se migraría sin escribir cambios.
	 *
	 * ## EXAMPLES
	 *
	 *     wp momentum migrate-zona
	 *     wp momentum migrate-zona --dry-run
	 */
	public function __invoke($args, $assoc_args)
	{
		if (! function_exists('momentum_propiedad_meta_schema')) {
			WP_CLI::error('El esquema de propiedades no está disponible (¿el tema Momentum está activo?).');

			return;
		}

		$dry_run     = isset($assoc_args['dry-run']);
		$zona_labels = momentum_propiedad_meta_schema()['zona']['options_map']; // slug => Label

		$posts = get_posts([
			'post_type'      => 'propiedades',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);

		$updated     = [];
		$already_set = 0;
		$unresolved  = [];

		foreach ($posts as $post_id) {
			$existing_zona = get_post_meta($post_id, 'zona', true);

			if ($existing_zona !== '') {
				$already_set++;
				continue;
			}

			$ciudad    = trim((string) get_post_meta($post_id, 'ciudad', true));
			$ubicacion = trim((string) get_post_meta($post_id, 'ubicacion', true));

			$match_ciudad    = $this->match_zona($ciudad, $zona_labels);
			$match_ubicacion = $this->match_zona($ubicacion, $zona_labels);

			$candidates = array_unique(array_filter([$match_ciudad, $match_ubicacion]));

			if (count($candidates) === 1) {
				$zona_slug = reset($candidates);

				if (! $dry_run) {
					update_post_meta($post_id, 'zona', $zona_slug);
				}

				$updated[] = [
					'ID'     => $post_id,
					'title'  => get_the_title($post_id),
					'zona'   => $zona_labels[$zona_slug],
				];

				continue;
			}

			// Sin match, o 'ciudad' y 'ubicacion' apuntan a zonas distintas: no se migra.
			$unresolved[] = [
				'ID'        => $post_id,
				'title'     => get_the_title($post_id),
				'ciudad'    => $ciudad,
				'ubicacion' => $ubicacion,
				'motivo'    => count($candidates) > 1 ? 'ciudad y ubicación sugieren zonas distintas' : 'sin coincidencia reconocida',
			];
		}

		WP_CLI::log('');
		WP_CLI::log(sprintf('Total de propiedades revisadas: %d', count($posts)));
		WP_CLI::log(sprintf('Ya tenían zona asignada (sin cambios): %d', $already_set));
		WP_CLI::log(sprintf('%s: %d', $dry_run ? 'Se migrarían' : 'Migradas', count($updated)));

		if ($updated) {
			WP_CLI\Utils\format_items('table', $updated, ['ID', 'title', 'zona']);
		}

		WP_CLI::log(sprintf('Requieren revisión manual (sin zona asignada): %d', count($unresolved)));

		if ($unresolved) {
			WP_CLI\Utils\format_items('table', $unresolved, ['ID', 'title', 'ciudad', 'ubicacion', 'motivo']);
		}

		if ($dry_run) {
			WP_CLI::success('Simulación completada (--dry-run): no se escribió ningún cambio.');
		} else {
			WP_CLI::success('Migración completada.');
		}
	}

	/**
	 * Busca una única zona cuya etiqueta aparezca claramente en $value
	 * (coincidencia exacta o como subcadena, sin distinguir mayúsculas/acentos
	 * simples). Devuelve el slug de la zona o '' si no hay match seguro.
	 */
	private function match_zona(string $value, array $zona_labels): string
	{
		if ($value === '') {
			return '';
		}

		$haystack = $this->normalize($value);
		$matches  = [];

		foreach ($zona_labels as $slug => $label) {
			if (strpos($haystack, $this->normalize($label)) !== false) {
				$matches[] = $slug;
			}
		}

		$matches = array_unique($matches);

		return count($matches) === 1 ? $matches[0] : '';
	}

	private function normalize(string $value): string
	{
		$value = function_exists('remove_accents') ? remove_accents($value) : $value;

		return strtolower(trim($value));
	}
}

WP_CLI::add_command('momentum migrate-zona', 'Momentum_Admin_CLI_Migrate_Zona');
