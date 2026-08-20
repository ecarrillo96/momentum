<?php
/**
 * Shared helpers for rendering propiedad data across Home, Venta, Renta,
 * Terrenos y las próximas plantillas de listado/detalle.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * true si un título de adjunto es un rótulo genérico auto-generado (por el
 * nombre de archivo de la cámara, o por una convención de captura tipo
 * "Propiedad - foto 08") en vez de una descripción real del contenido de
 * la imagen. Usarlo tal cual como ALT sería tan inútil para SEO/accesibilidad
 * como dejarlo vacío -por eso momentum_attachment_alt() lo descarta y sigue
 * a su siguiente fallback en vez de devolverlo-.
 *
 * Un único lugar para esta detección (aquí) en vez de repetir el patrón en
 * cada template que arma un ALT.
 */
function momentum_is_generic_attachment_title(string $title): bool
{
	$title = trim($title);

	if ($title === '') {
		return true;
	}

	$patterns = [
		// "Propiedad - foto 08", "Propiedad – foto 08", "Terreno - foto 08"...
		// 'u' (PCRE_UTF8): el guion largo/en-dash "–"/"—" son multibyte en
		// UTF-8 -sin este modificador, la clase [-–—] compara byte a byte y
		// nunca hace match completo contra esos caracteres-.
		'/^(propiedad|terreno)\s*[-–—]\s*foto\s*\d+$/iu',
		// "foto 01", "photo 01", "image 01", "imagen 01" (con o sin separador)
		'/^(foto|photo|image|imagen)[\s_-]*\d+$/i',
		// Nombres de archivo típicos de cámara/celular: IMG_1234, DSC_1234,
		// DSCN1234, PXL_20240101, MVIMG_20240101, etc.
		'/^(img|dsc|dscn|dscf|pxl|mvimg)[\s_-]*\d+$/i',
	];

	foreach ($patterns as $pattern) {
		if (preg_match($pattern, $title)) {
			return true;
		}
	}

	return false;
}

/**
 * Texto ALT de un adjunto de la Media Library, con la misma cadena de
 * fallback en todo el tema (SEO Site Checkup marcaba imágenes sin ALT
 * útil): 1) ALT guardado en la biblioteca de medios, 2) título del propio
 * adjunto -salvo que sea un rótulo genérico, ver momentum_is_generic_attachment_title()-,
 * 3) $fallback que pasa cada llamador (normalmente el título de la
 * propiedad o del artículo en cuyo contexto se usa la imagen, o el
 * resultado de momentum_property_image_alt() para propiedades/terrenos).
 * Nunca devuelve un genérico tipo "imagen" -si ni siquiera hay $fallback,
 * el resultado queda en '' y el llamador decide qué hacer (alt="" solo es
 * correcto para imágenes puramente decorativas).
 */
function momentum_attachment_alt(int $attachment_id, string $fallback = ''): string
{
	$alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));

	if ($alt !== '') {
		return $alt;
	}

	// 'raw' a propósito, NUNCA get_the_title(): ese filtra por wptexturize()
	// vía el hook 'the_title', que convierte el guion simple de "Propiedad -
	// foto 19" en la entidad HTML "&#8211;" -distinto carácter que el que
	// buscan los patrones de momentum_is_generic_attachment_title(), así que
	// el título texturizado nunca hacía match y el genérico se colaba como
	// si fuera descriptivo-.
	$title = trim((string) get_post_field('post_title', $attachment_id, 'raw'));

	if ($title !== '' && ! momentum_is_generic_attachment_title($title)) {
		return $title;
	}

	return $fallback;
}

/**
 * ALT para una imagen de Propiedades/Terrenos (galería, tarjeta, imagen
 * destacada, lightbox): misma cadena de prioridad que momentum_attachment_alt(),
 * pero con un fallback contextual armado a partir de la propiedad/terreno
 * dueño de la imagen -"Imagen de {título}" o "Imagen de {título} en {zona}"
 * si tiene zona capturada- en vez de un fallback plano. No inventa
 * contenido de la imagen (nunca "cocina", "recámara", etc. si no está
 * guardado en alguna parte) — solo describe de qué ficha es la foto.
 */
function momentum_property_image_alt(int $attachment_id, int $property_id): string
{
	$title = get_the_title($property_id);
	// momentum_seo_zona_for_suffix() (inc/seo-listing-meta.php) evita repetir
	// la zona cuando el propio título de la ficha ya la menciona (ej. "Casa
	// con alberca y jardín en Lomas de Cocoyoc") -sin esto, este alt
	// terminaba en "... en Lomas de Cocoyoc en Lomas de Cocoyoc"-.
	$zona_label = momentum_seo_zona_for_suffix(
		$title,
		momentum_get_zona_label((string) get_post_meta($property_id, 'zona', true))
	);

	if ($zona_label !== '') {
		$fallback = sprintf(
			/* translators: 1: título de la propiedad o terreno, 2: nombre de la zona */
			__('Imagen de %1$s en %2$s', 'momentum'),
			$title,
			$zona_label
		);
	} else {
		$fallback = sprintf(
			/* translators: %s: título de la propiedad o terreno */
			__('Imagen de %s', 'momentum'),
			$title
		);
	}

	return momentum_attachment_alt($attachment_id, $fallback);
}

function momentum_get_propiedad(int $post_id): array
{
	$schema = momentum_propiedad_meta_schema();
	$data   = ['id' => $post_id];

	foreach ($schema as $key => $field) {
		$value = get_post_meta($post_id, $key, true);

		$data[$key] = $field['type'] === 'number' ? (float) $value : (string) $value;
	}

	return $data;
}

function momentum_format_price(float $price, string $tipo_operacion = ''): string
{
	$formatted = '$' . number_format($price, 0, '.', ',') . ' ' . __('MXN', 'momentum');

	if ($tipo_operacion === 'Renta') {
		/* translators: appended right after a rental price, e.g. "$12,000 MXN/mes". */
		$formatted .= __('/mes', 'momentum');
	}

	return $formatted;
}

/**
 * Precio para mostrar, en la moneda del idioma actual: MXN en el idioma por
 * defecto (igual que siempre, vía momentum_format_price()), USD en
 * cualquier otro idioma -no hardcodeado a 'en': la comparación es contra
 * momentum_default_language(), así que un tercer idioma futuro también
 * mostraría USD sin tocar este archivo, a menos que se agregue su propia
 * moneda a MONEDA_POR_IDIOMA-. La conversión es al vuelo desde el mismo
 * 'precio' de siempre: nunca se guarda un segundo precio en ningún lado.
 * get_momentum_exchange_rate() ya existe (inc/site-settings.php) y su
 * dirección es "1 USD = X MXN" -confirmado leyendo el hint del campo en
 * Admin > General-, por eso aquí se divide, no se multiplica.
 */
function momentum_get_property_price_display(array $data): string
{
	if (momentum_current_language() === momentum_default_language()) {
		return momentum_format_price($data['precio'], $data['tipo_operacion']);
	}

	$rate = get_momentum_exchange_rate();
	$usd  = $rate > 0 ? $data['precio'] / $rate : 0.0;

	$formatted = '$' . number_format($usd, 0, '.', ',') . ' USD';

	if (($data['tipo_operacion'] ?? '') === 'Renta') {
		$formatted .= __('/mes', 'momentum');
	}

	return $formatted;
}

/**
 * Aviso mostrado junto a cualquier monto en USD de una ficha (precio
 * convertido en momentum_get_property_price_display(), o taxes_usd en
 * property/header.php) -ambos son aproximados frente al tipo de cambio real
 * al momento de una operación, de ahí el aviso-. Imprimir solo cuando ya se
 * decidió mostrar un monto en USD; este helper no repite esa condición
 * (momentum_current_language() !== momentum_default_language()).
 *
 * Versión singular: usada donde solo se muestra UN monto en USD (el precio),
 * ej. property-card.php, o property/header.php cuando esa ficha no tiene
 * taxes_usd capturado. Ver momentum_usd_estimate_note_plural() para cuando
 * precio Y Annual Property Tax se muestran juntos.
 */
function momentum_usd_estimate_note(): string
{
	return __('Estimated amount in USD.', 'momentum');
}

/**
 * Misma nota que momentum_usd_estimate_note(), en plural: usada en
 * property/header.php cuando el precio Y el Annual Property Tax se muestran
 * juntos arriba de esta leyenda, para dejar claro que "estimado" aplica a
 * ambos montos y no solo al primero.
 */
function momentum_usd_estimate_note_plural(): string
{
	return __('Estimated amounts in USD.', 'momentum');
}

/**
 * URL del video de introducción para el idioma actual. Usado tanto por el
 * overlay de Home (template-parts/home/intro-video.php) como por la sección
 * de Construcción (template-parts/construccion/intro.php) -mismo archivo
 * reutilizado en ambos lugares-. Sin un video en inglés guardado en Admin >
 * General (momentum_get_intro_video_url_en(), inc/site-settings.php), cae
 * al video en español de siempre, así que un visitante en inglés nunca ve
 * una URL vacía o rota mientras nadie haya subido la versión en inglés.
 *
 * Sirve 'intro-optimized.mp4' (960x540, ~3.5 MB) en vez del archivo original
 * 'intro.mp4' (1280x720, ~31 MB, conservado en el tema sin usarse en ningún
 * template): mismo contenido, mucho más liviano tanto aquí como en el
 * overlay del Home. Si algún día se sube un video en inglés desde el panel,
 * ese archivo se sirve tal cual se subió -no hay una versión optimizada
 * generada para él-.
 */
function momentum_get_intro_video_url(): string
{
	if (momentum_current_language() !== momentum_default_language()) {
		$en_url = momentum_get_intro_video_url_en();

		if ($en_url !== '') {
			return $en_url;
		}
	}

	return get_stylesheet_directory_uri() . '/assets/video/intro-optimized.mp4';
}

/**
 * Versión WebM del mismo video (más liviana que el MP4 para los navegadores
 * que la soportan). Solo existe para el archivo propio del tema: un video en
 * inglés subido desde el panel (momentum_get_intro_video_url_en()) es un
 * único archivo MP4 tal cual lo sube el administrador, sin un WebM generado
 * para él, así que en ese caso se devuelve '' y el <video> se queda solo con
 * la fuente MP4 -nunca se referencia un archivo WebM que no existe-.
 */
function momentum_get_intro_video_webm_url(): string
{
	if (momentum_current_language() !== momentum_default_language() && momentum_get_intro_video_url_en() !== '') {
		return '';
	}

	return get_stylesheet_directory_uri() . '/assets/video/intro-optimized.webm';
}

/**
 * Poster del video de introducción: primer fotograma visible antes de que
 * el navegador tenga que descargar un solo byte del video en sí (con
 * preload="none"/"metadata"). Igual que el WebM, solo aplica al video propio
 * del tema -un video en inglés subido por el administrador no tiene un
 * poster generado para él-.
 */
function momentum_get_intro_video_poster_url(): string
{
	if (momentum_current_language() !== momentum_default_language() && momentum_get_intro_video_url_en() !== '') {
		return '';
	}

	return get_stylesheet_directory_uri() . '/assets/images/intro-poster.webp';
}

/**
 * URL de TikTok para el idioma actual. Usado por el footer
 * (template-parts/site-footer.php). Sin una cuenta en inglés guardada en
 * Admin > General (momentum_get_tiktok_url_en(), inc/site-settings.php), cae
 * a la cuenta en español de siempre -mismo criterio que
 * momentum_get_intro_video_url()-.
 */
function momentum_get_tiktok_url_for_current_language(): string
{
	if (momentum_current_language() !== momentum_default_language()) {
		$en_url = momentum_get_tiktok_url_en();

		if ($en_url !== '') {
			return $en_url;
		}
	}

	return momentum_get_tiktok_url();
}

/**
 * Label visible para el valor crudo de 'estado' guardado en post meta
 * (Disponible/Apartada/Preventa/Vendida/Rentada/Remate). El valor guardado
 * en BD NUNCA cambia -solo cambia cómo se muestra según el idioma actual-,
 * igual que momentum_get_zona_label() para 'zona'. Valores no reconocidos
 * se devuelven tal cual, sin romper nada.
 */
function momentum_get_estado_label(string $estado): string
{
	if ($estado === '') {
		return '';
	}

	$labels = [
		'Disponible' => __('Disponible', 'momentum'),
		'Apartada'   => __('Apartada', 'momentum'),
		'Preventa'   => __('En preventa', 'momentum'),
		'Vendida'    => __('Vendida', 'momentum'),
		'Rentada'    => __('Rentada', 'momentum'),
		'Remate'     => __('Remate', 'momentum'),
	];

	return $labels[$estado] ?? $estado;
}

/**
 * Label visible para el valor crudo de 'tipo_operacion' (Venta/Renta).
 * Mismo criterio que momentum_get_estado_label(): el valor en BD no cambia.
 */
function momentum_get_tipo_operacion_label(string $tipo): string
{
	if ($tipo === '') {
		return '';
	}

	$labels = [
		'Venta' => __('Venta', 'momentum'),
		'Renta' => __('Renta', 'momentum'),
	];

	return $labels[$tipo] ?? $tipo;
}

/**
 * Human-readable label for a stored 'zona' slug (e.g. 'lomas-de-cocoyoc' -> 'Lomas de Cocoyoc').
 * Returns '' for empty/unrecognized values so callers can safely skip rendering.
 */
function momentum_get_zona_label(string $zona): string
{
	if ($zona === '') {
		return '';
	}

	$schema = momentum_propiedad_meta_schema();

	return $schema['zona']['options_map'][$zona] ?? '';
}

/**
 * Evita repetir la zona si un texto (título de ficha, etc.) ya la
 * menciona (ej. "Casa con alberca y jardín en Lomas de Cocoyoc", un
 * título que el administrador escribió incluyendo la ubicación) -sin
 * esto, titles, descriptions y alt text terminaban con el nombre de la
 * zona dos veces-. Reutilizada por inc/seo-listing-meta.php (title/
 * description SEO) y momentum_property_image_alt() más abajo (alt de
 * imágenes), para no repetir esta regla en cada lugar que arma texto a
 * partir del título + la zona de un inmueble.
 */
function momentum_seo_zona_for_suffix(string $name, string $zona): string
{
	if ($zona === '' || $name === '') {
		return $zona;
	}

	return mb_stripos($name, $zona) !== false ? '' : $zona;
}

/**
 * @return string[] Non-empty, trimmed lines from the 'caracteristicas' textarea meta.
 */
function momentum_get_caracteristicas(int $post_id): array
{
	$raw = (string) get_post_meta($post_id, 'caracteristicas', true);

	if ($raw === '') {
		return [];
	}

	$lines = array_map('trim', explode("\n", $raw));

	return array_values(array_filter($lines));
}

/**
 * @return int[] Attachment IDs from the 'galeria' CSV meta.
 */
function momentum_get_galeria_ids(int $post_id): array
{
	$raw = (string) get_post_meta($post_id, 'galeria', true);

	if ($raw === '') {
		return [];
	}

	return array_values(array_filter(array_map('absint', explode(',', $raw))));
}


/**
 * Minimal inline SVG icon set (line style) so the child theme has no
 * dependency on Blocksy's icon manager.
 */
function momentum_icon(string $name): string
{
	$icons = [
		'bed' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7"/><path d="M3 18h18"/><path d="M5 11V7a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2"/><path d="M12 11V9a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2"/></svg>',
		'bath' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M4 12V6a2 2 0 0 1 2-2c1 0 2 .8 2 2"/><path d="M2 19h20"/></svg>',
		'ruler' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="8" rx="1.5"/><path d="M7 8v3M11 8v4M15 8v3M19 8v4"/></svg>',
		'land' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20 10 4l4 8 3-4 4 12z"/></svg>',
		'pin' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.3"/></svg>',
		'phone' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .3 2 .6 3a2 2 0 0 1-.5 2L8 10a16 16 0 0 0 6 6l1.3-1.1a2 2 0 0 1 2-.5c1 .3 2 .5 3 .6a2 2 0 0 1 1.7 2z"/></svg>',
		'check' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
		'shield' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.7 8 11 4.6-2.3 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>',
		'compass' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-2 6-6 2 2-6z"/></svg>',
		'map' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/></svg>',
		'headset' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13a9 9 0 0 1 18 0v5a2 2 0 0 1-2 2h-1v-6h3"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19v1a2 2 0 0 1-2 2h-3"/></svg>',
		'home'    => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/></svg>',
		'key'     => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.2-8.2 2 2-1.8 1.8h-2.2v2.2l-2 2"/></svg>',
		'tool'    => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.1L3 18l3 3 6.6-6.3a4 4 0 0 0 5.1-5.4l-2.6 2.6-2-2z"/></svg>',
		'whatsapp' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm0 18.1a8.1 8.1 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.1 8.1 0 1 1 12 20.1zm4.4-6.1c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.5.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.2-.1-1-.4-2-1.2-.7-.7-1.2-1.5-1.4-1.7-.1-.2 0-.4.1-.5l.4-.4c.1-.1.2-.3.2-.4.1-.2 0-.3 0-.5l-.7-1.7c-.2-.4-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 1.9 0 1.1.8 2.2 1 2.4.1.2 1.6 2.5 3.9 3.4.5.2.9.4 1.3.5.6.2 1 .1 1.4-.1.4-.2 1.3-.5 1.5-1.1.2-.5.2-1 .1-1.1z"/></svg>',
		'facebook' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.7h2.6l.4-3h-3v-1.9c0-.9.2-1.5 1.5-1.5h1.6V4.2c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9v2.2H7.5v3H10V21z"/></svg>',
		'instagram' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>',
		'tiktok' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6c0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64c0 3.33 2.76 5.7 5.69 5.7c3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 0 0 4.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>',
		'mail' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
		'clock' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
		'heart' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.5s-7.5-4.6-10-9.3C.4 8 1.8 4.5 5.2 3.6c2-.5 4 .3 5.3 2 .3.4.9.4 1.2 0 1.3-1.7 3.3-2.5 5.3-2 3.4.9 4.8 4.4 3.2 7.6-2.5 4.7-10 9.3-10 9.3z"/></svg>',
		'handshake' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m2 12 4-4 5 3 3-3 4 4"/><path d="m20 12-3 3-2-2-3 3-3-3-3 3-4-4"/></svg>',
		'document' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 2h7l5 5v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v5h5"/><path d="M9 13h6M9 17h6"/></svg>',
		'star' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.9-6.2-3.3-6.2 3.3 1.2-6.9-5-4.9 6.9-1z"/></svg>',
		'info' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>',
	];

	if (! isset($icons[$name])) {
		return '';
	}

	return $icons[$name];
}
