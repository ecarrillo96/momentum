<?php
/**
 * Multilingual support (Polylang) — thin, self-contained wrapper.
 *
 * Ningún template debe llamar pll_*() directamente. Todo pasa por los
 * helpers momentum_*() de este archivo, que siempre revisan function_exists()
 * antes de tocar Polylang. Si el plugin se desactiva, cada helper cae de
 * vuelta a un comportamiento "solo español" sin generar ningún fatal error
 * -es la garantía de que el sitio sigue funcionando 100% si Polylang deja
 * de estar instalado-.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * true si Polylang está activo y hay más de un idioma configurado.
 */
function momentum_is_multilingual(): bool
{
	return function_exists('pll_languages_list') && count((array) pll_languages_list()) > 1;
}

/**
 * Idioma por defecto del sitio ('es' si Polylang no está activo o aún no
 * tiene idiomas configurados).
 */
function momentum_default_language(): string
{
	if (function_exists('pll_default_language')) {
		$lang = pll_default_language();

		if ($lang) {
			return $lang;
		}
	}

	return 'es';
}

/**
 * Idioma de la vista actual. Cae a momentum_default_language() si Polylang
 * no está activo.
 */
function momentum_current_language(): string
{
	if (function_exists('pll_current_language')) {
		$lang = pll_current_language();

		if ($lang) {
			return $lang;
		}
	}

	return momentum_default_language();
}

/**
 * ID de la traducción de $post_id en $lang (o en el idioma actual si no se
 * especifica). Sin Polylang, o si no existe traducción, devuelve el mismo
 * $post_id -así un llamador puede usar el resultado siempre sin ramificar-.
 */
function momentum_get_translation(int $post_id, ?string $lang = null): int
{
	if (! function_exists('pll_get_post')) {
		return $post_id;
	}

	$lang          = $lang ?? momentum_current_language();
	$translated_id = pll_get_post($post_id, $lang);

	return $translated_id ? (int) $translated_id : $post_id;
}

/**
 * ID de la traducción de $post_id en $lang, o 0 si no existe -a diferencia
 * de momentum_get_translation(), que cae al mismo $post_id-. Para código que
 * necesita distinguir "no existe todavía" de "existe y es el mismo post"
 * (Momentum_Property_Translation_Service, para decidir si crear la traducción).
 */
function momentum_find_translation(int $post_id, string $lang): int
{
	if (! function_exists('pll_get_post')) {
		return 0;
	}

	return (int) pll_get_post($post_id, $lang);
}

/**
 * Todos los idiomas configurados en Polylang excepto el idioma por defecto.
 * [] si Polylang no está activo o solo hay un idioma. Único lugar del
 * proyecto -además de las demás funciones de este archivo- que toca
 * pll_languages_list() directamente, para que ningún otro código necesite
 * asumir 'en' como el único idioma secundario posible.
 */
function momentum_get_secondary_languages(): array
{
	if (! function_exists('pll_languages_list')) {
		return [];
	}

	$default = momentum_default_language();

	return array_values(array_filter(
		(array) pll_languages_list(),
		static fn (string $slug): bool => $slug !== $default
	));
}

/**
 * Idioma actual de $post_id ('' si no tiene, o si Polylang no está activo).
 */
function momentum_get_post_language(int $post_id): string
{
	if (! function_exists('pll_get_post_language')) {
		return '';
	}

	$lang = pll_get_post_language($post_id);

	return $lang ? (string) $lang : '';
}

/**
 * Asigna idioma a un post. No-op sin Polylang.
 */
function momentum_set_post_language(int $post_id, string $lang): void
{
	if (function_exists('pll_set_post_language')) {
		pll_set_post_language($post_id, $lang);
	}
}

/**
 * Enlaza un grupo de posts como traducciones entre sí, ej.
 * momentum_link_translations(['es' => 12, 'en' => 45]). No-op sin Polylang.
 */
function momentum_link_translations(array $translations): void
{
	if (function_exists('pll_save_post_translations')) {
		pll_save_post_translations($translations);
	}
}

/**
 * Home URL del idioma indicado (o del idioma actual). Sin Polylang, la home
 * normal del sitio.
 */
function momentum_home_url(?string $lang = null): string
{
	if (function_exists('pll_home_url')) {
		return $lang ? pll_home_url($lang) : pll_home_url();
	}

	return home_url('/');
}

/**
 * Emoji de bandera para el botón/menú del selector de idioma, por slug de
 * idioma de Polylang. Fijo a propósito -MX para 'es' y US para 'en'- en vez
 * de derivarlo del locale (es-MX ya apunta a México, pero en-US podría
 * confundirse con "bandera del idioma" en vez de "bandera del público al
 * que se dirige cada versión", que es lo que se pidió: la version en inglés
 * de este sitio mexicano se dirige a compradores de EE. UU.-. Un idioma sin
 * entrada aquí simplemente no muestra bandera (el nombre solo sigue
 * funcionando), así que agregar un tercer idioma en Polylang no rompe nada.
 */
function momentum_language_switcher_flag(string $slug): string
{
	$flags = [
		'es' => '🇲🇽',
		'en' => '🇺🇸',
	];

	return $flags[$slug] ?? '';
}

/**
 * HTML del selector de idioma para el header: un botón con la bandera y el
 * nombre del idioma actual que despliega un menú con la(s) otra(s)
 * opción(es) (bandera + nombre completo, ej. "English"). Devuelve '' si
 * Polylang no está activo o solo hay un idioma -el llamador simplemente no
 * imprime nada, no necesita revisar nada él mismo-.
 *
 * El toggle de apertura/cierre vive en assets/js/header.js (data-momentum-lang-*);
 * sin JS, el menú desplegable queda siempre visible (ver .momentum-lang-switcher
 * en momentum.css) en vez de inaccesible detrás de un botón que no reacciona.
 */
function momentum_language_switcher(): string
{
	if (! function_exists('pll_the_languages') || ! momentum_is_multilingual()) {
		return '';
	}

	$languages = pll_the_languages([
		'raw'           => 1,
		'hide_if_empty' => 0,
		'show_flags'    => 0,
		'show_names'    => 1,
	]);

	if (empty($languages) || ! is_array($languages)) {
		return '';
	}

	$current = null;
	$others  = '';

	foreach ($languages as $language) {
		$locale = isset($language['locale']) ? str_replace('_', '-', $language['locale']) : $language['slug'];
		$flag   = momentum_language_switcher_flag($language['slug']);

		if (! empty($language['current_lang'])) {
			$current = $language;
			continue;
		}

		$others .= sprintf(
			'<li class="momentum-lang-switcher__item">
				<a href="%1$s" class="momentum-lang-switcher__option" lang="%2$s" hreflang="%2$s">
					<span class="momentum-lang-switcher__flag" aria-hidden="true">%3$s</span>
					<span>%4$s</span>
				</a>
			</li>',
			esc_url($language['url']),
			esc_attr($locale),
			esc_html($flag),
			esc_html($language['name'])
		);
	}

	// Sin idioma actual detectado (no debería pasar con Polylang activo, pero
	// evita imprimir un botón vacío si alguna vez ocurre).
	if ($current === null) {
		return '';
	}

	$current_flag = momentum_language_switcher_flag($current['slug']);

	return sprintf(
		'<div class="momentum-lang-switcher__inner" data-momentum-lang-switcher>
			<button type="button" class="momentum-lang-switcher__toggle" data-momentum-lang-toggle aria-haspopup="true" aria-expanded="false">
				<span class="momentum-lang-switcher__flag" aria-hidden="true">%1$s</span>
				<span class="momentum-lang-switcher__label">%2$s</span>
				<svg class="momentum-lang-switcher__chevron" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<ul class="momentum-lang-switcher__menu" data-momentum-lang-menu>%3$s</ul>
		</div>',
		esc_html($current_flag),
		esc_html($current['name']),
		$others
	);
}

/**
 * is_page() que también reconoce la traducción en inglés de las páginas
 * indicadas -pasadas siempre por su slug en español, el idioma canónico del
 * sitio-. Sin esto, comprobaciones como is_page(['venta', 'renta']) dejan de
 * dar match en /en/sale/, /en/rent/ (slugs distintos en inglés) y esas
 * páginas perderían su CSS/JS específico o dejarían de procesar su formulario.
 * Sin Polylang activo, momentum_get_translation() devuelve el mismo ID de la
 * página en español, así que esto se comporta exactamente como is_page().
 */
function momentum_is_page_variant(array $es_slugs): bool
{
	$current_id = get_queried_object_id();

	if (! $current_id) {
		return false;
	}

	foreach ($es_slugs as $slug) {
		$page = get_page_by_path($slug);

		if (! $page) {
			continue;
		}

		if ($current_id === $page->ID || $current_id === momentum_get_translation($page->ID, 'en')) {
			return true;
		}
	}

	return false;
}

/**
 * URL, en el idioma actual, de la página cuyo slug en español (el idioma
 * canónico del sitio) es $es_slug -ej. momentum_url_for_page('contacto')
 * devuelve /contacto/ en español o /en/contact/ en inglés-. Para plantillas
 * compartidas entre idiomas (home, sidebar, footer) que antes enlazaban una
 * ruta en español fija con home_url('/contacto/') y necesitan seguir el
 * idioma de la vista actual en vez de siempre mandar a la versión en
 * español. Sin Polylang, o si la página no existe, cae a la misma ruta en
 * español de siempre.
 */
function momentum_url_for_page(string $es_slug): string
{
	$page = get_page_by_path($es_slug);

	if (! $page) {
		return home_url('/' . trim($es_slug, '/') . '/');
	}

	$translated_id = momentum_get_translation($page->ID);
	$permalink      = get_permalink($translated_id);

	return $permalink ?: home_url('/' . trim($es_slug, '/') . '/');
}

/**
 * wp_nav_menu('menu_1') con red de seguridad: si por lo que sea el idioma
 * actual no tiene un menú asignado a esa ubicación -paso manual de Polylang
 * olvidado, o Polylang desactivado sin que el menú por defecto del theme
 * location sobreviva-, se cae al menú "Menú Principal" por ID en vez de
 * mostrar un header sin navegación.
 */
function momentum_render_primary_menu(array $args = []): void
{
	$defaults = [
		'theme_location' => 'menu_1',
		'container'      => false,
		'fallback_cb'    => false,
	];

	if (! has_nav_menu('menu_1')) {
		$menu = get_term_by('slug', 'menu-principal', 'nav_menu');

		if ($menu) {
			unset($defaults['theme_location']);
			$defaults['menu'] = $menu->term_id;
		}
	}

	wp_nav_menu(array_merge($defaults, $args));
}

/**
 * Carga languages/momentum-{locale}.mo para que __()/_e() con el text
 * domain 'momentum' sirvan la cadena traducida cuando Polylang cambia el
 * locale activo. Sin archivo .mo para el locale actual, WordPress
 * simplemente no encuentra traducciones y __() devuelve el string fuente
 * (español) tal cual -o sea, jamás rompe nada, con o sin Polylang activo-.
 */
add_action('after_setup_theme', function () {
	load_theme_textdomain('momentum', get_stylesheet_directory() . '/languages');
});

/**
 * the_custom_logo() (núcleo de WordPress) siempre enlaza a home_url('/')
 * -no expone ningún filtro sobre esa URL en particular, solo sobre el HTML
 * completo del logo-. Este filtro reescribe ese único href para que el
 * logo siga el idioma de la vista actual (momentum_home_url()) en vez de
 * mandar siempre a la portada en español cuando se navega en inglés.
 */
add_filter('get_custom_logo', function (string $html): string {
	if (! momentum_is_multilingual()) {
		return $html;
	}

	$default_home = esc_url(home_url('/'));
	$current_home = esc_url(momentum_home_url());

	if ($default_home === $current_home) {
		return $html;
	}

	return str_replace('href="' . $default_home . '"', 'href="' . $current_home . '"', $html);
});

/**
 * Registra 'propiedades' y 'terrenos' como post types traducibles en
 * Polylang. Vive en código -no solo como casilla marcada en Settings ->
 * Languages- para que la configuración quede en control de versiones y sea
 * reproducible. Si Polylang no está instalado, el hook 'pll_get_post_types'
 * nunca se dispara: este add_filter() no hace nada por sí solo, no requiere
 * function_exists().
 */
add_filter('pll_get_post_types', function (array $post_types): array {
	$post_types['propiedades'] = 'propiedades';
	$post_types['terrenos']    = 'terrenos';

	return $post_types;
});
