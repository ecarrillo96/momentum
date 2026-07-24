<?php
/**
 * Ajustes de Schema (JSON-LD) de Rank Math para los CPT Propiedades y
 * Terrenos.
 *
 * Contexto: Rank Math Free solo agrega el grafo base (Organization +
 * WebSite + WebPage + BreadcrumbList + ImageObject) a un post type
 * personalizado cuando su "Rich Snippet" por defecto está configurado como
 * Article/BlogPosting/NewsArticle o WooCommerce/EDD Product (ver
 * includes/helpers/class-schema.php del plugin). Un CPT sin ese tipo
 * simplemente no dispara el grafo, sin alternativa nativa para un tipo
 * inmobiliario. Por eso "pt_propiedades_default_rich_snippet" y
 * "pt_terrenos_default_rich_snippet" están en "article" en Rank Math (es lo
 * único que activa el grafo sin instalar WooCommerce ni Pro).
 *
 * Como una ficha de propiedad/terreno no es un artículo, aquí se elimina
 * del JSON-LD final el nodo "Article" (Rank Math lo guarda bajo la key
 * richSnippet) y el nodo "Person" del autor (key ProfilePage) SOLO para
 * esos dos CPT. Organization, WebSite, WebPage, BreadcrumbList e
 * ImageObject no se tocan. Las entradas del blog (post) no pasan por este
 * filtro y conservan su Article + Person normalmente.
 *
 * Ningún dato se inventa: solo se resta del grafo que Rank Math ya generaba,
 * no se agrega nada nuevo.
 *
 * Mejora futura (no implementada): sustituir el nodo eliminado por un
 * schema.org/RealEstateListing propio, construido a mano con los custom
 * fields reales de la ficha (codigo_propiedad, precio, tipo_operacion,
 * recamaras, terreno_m2, etc.) e inyectado en este mismo filtro
 * ('rank_math/json_ld'). Rank Math no ofrece ese tipo en su selector de
 * Rich Snippet, pero el filtro admite cualquier entidad JSON-LD válida.
 */

if (! defined('ABSPATH')) {
	exit;
}

add_filter('rank_math/json_ld', function (array $data) {
	if (! is_singular(['propiedades', 'terrenos'])) {
		return $data;
	}

	unset($data['richSnippet'], $data['ProfilePage']);

	return $data;
}, 999);
