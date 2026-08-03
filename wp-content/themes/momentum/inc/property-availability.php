<?php
/**
 * Fuente única de verdad de "¿esta propiedad/terreno sigue disponible?".
 *
 * momentum_public_estado_values() (inc/property-query.php) ya era la lista
 * blanca que usan los listados (Venta/Renta/Terrenos), el archivo nativo y
 * "propiedades similares" para decidir qué mostrar. Estas dos funciones
 * envuelven esa misma lista para que el resto del sitio (aviso visible,
 * meta robots, sitemap, CTA) consulte una sola vez si un estado es público,
 * en vez de repetir `in_array($estado, ['Disponible', 'Preventa', 'Remate'])`
 * (o peor, `$estado === 'Vendida' || $estado === 'Rentada'`) en cada archivo.
 *
 * No reemplaza los meta_query existentes (esos siguen siendo la forma
 * correcta de filtrar en SQL) — es la versión "post individual, boolean"
 * de la misma regla, para código que ya tiene un $post_id en mano.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * @param string $estado Valor crudo del meta 'estado' (p. ej. 'Disponible', 'Vendida').
 */
function momentum_is_public_property_status(string $estado): bool
{
	return in_array($estado, momentum_public_estado_values(), true);
}

/**
 * Disponibilidad de una propiedad o terreno puntual. Ambos CPT comparten el
 * mismo meta 'estado' (ver momentum_propiedad_meta_schema() / momentum_terreno_meta_schema()),
 * así que esta función sirve para los dos sin distinguir post_type.
 */
function momentum_is_property_available(int $post_id): bool
{
	return momentum_is_public_property_status((string) get_post_meta($post_id, 'estado', true));
}
