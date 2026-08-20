<?php
/**
 * Campos administrables de las páginas Inicio, Nosotros y Construcción.
 *
 * Mismo criterio que inc/cpt/propiedades.php: los valores se guardan como
 * post meta NATIVO (sin ACF) sobre el objeto Página real de cada idioma
 * (Nosotros ES = página "nosotros", Nosotros EN = su traducción de Polylang,
 * y así con Construcción), usando nombres de campo con el mismo prefijo que
 * tendría un grupo de ACF equivalente, para poder migrar más adelante sin
 * renombrar nada.
 *
 * Los campos de texto/WYSIWYG/CTA son independientes por idioma porque cada
 * idioma es una Página distinta en Polylang -se leen y guardan sobre
 * get_queried_object_id() tal cual, sin lógica especial-. Los campos de
 * imagen y video (schema con 'shared' => true) son la misma pieza visual en
 * ambos idiomas, así que se guardan una sola vez en la página en español (el
 * idioma por defecto del sitio) y momentum_get_shared_page_meta() los
 * resuelve desde cualquier idioma vía momentum_get_translation().
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Único campo administrable de Inicio, a propósito: el resto de la home
 * (hero, servicios, trust bar, FAQ, etc.) queda fuera de este alcance -solo
 * la foto de la sección "Sobre Momentum Cocoyoc" (template-parts/home/about.php)
 * se pidió editable-.
 *
 * @return array<string,array{type:string,label:string,shared?:bool,options?:array<string,string>}>
 */
function momentum_home_meta_schema(): array
{
	return [
		'home_about_image' => ['type' => 'image', 'label' => __('Imagen de la sección "Sobre Momentum Cocoyoc"', 'momentum-admin'), 'shared' => true],
	];
}

/**
 * @return array<string,array{type:string,label:string,shared?:bool,options?:array<string,string>}>
 */
function momentum_nosotros_meta_schema(): array
{
	return [
		'nosotros_hero_title'      => ['type' => 'text', 'label' => __('Título del hero', 'momentum-admin')],
		'nosotros_hero_subtitle'   => ['type' => 'text', 'label' => __('Subtítulo del hero', 'momentum-admin')],
		'nosotros_section_label'   => ['type' => 'text', 'label' => __('Encabezado pequeño (eyebrow)', 'momentum-admin')],
		'nosotros_section_content' => ['type' => 'html', 'label' => __('Contenido principal', 'momentum-admin')],
		'nosotros_main_image'      => ['type' => 'image', 'label' => __('Imagen principal', 'momentum-admin'), 'shared' => true],
		'nosotros_secondary_image' => ['type' => 'image', 'label' => __('Imagen secundaria', 'momentum-admin'), 'shared' => true],
	];
}

/**
 * @return array<string,array{type:string,label:string,shared?:bool,options?:array<string,string>}>
 */
function momentum_construccion_meta_schema(): array
{
	return [
		'construccion_hero_title'      => ['type' => 'text', 'label' => __('Título del hero', 'momentum-admin')],
		'construccion_hero_subtitle'   => ['type' => 'text', 'label' => __('Subtítulo del hero', 'momentum-admin')],
		'construccion_section_label'   => ['type' => 'text', 'label' => __('Encabezado pequeño (eyebrow)', 'momentum-admin')],
		'construccion_section_content' => ['type' => 'html', 'label' => __('Contenido principal', 'momentum-admin')],
		'construccion_cta_text'        => ['type' => 'text', 'label' => __('Texto del botón', 'momentum-admin')],
		'construccion_cta_url'         => ['type' => 'url', 'label' => __('URL del botón', 'momentum-admin')],
		'construccion_cta_new_tab'     => ['type' => 'bool', 'label' => __('Abrir enlace en una nueva pestaña', 'momentum-admin')],
		// Video y su portada: misma pieza en ambos idiomas -ver comentario de cabecera-.
		'construccion_video_source' => [
			'type'    => 'select',
			'label'   => __('Tipo de fuente del video', 'momentum-admin'),
			'shared'  => true,
			'options' => [
				'file' => __('Archivo de WordPress', 'momentum-admin'),
				'url'  => __('URL externa', 'momentum-admin'),
			],
		],
		'construccion_video_file'   => ['type' => 'video', 'label' => __('Archivo de video (biblioteca de medios)', 'momentum-admin'), 'shared' => true],
		'construccion_video_url'    => ['type' => 'url', 'label' => __('URL del video externo', 'momentum-admin'), 'shared' => true],
		'construccion_video_poster' => ['type' => 'image', 'label' => __('Imagen de portada (poster)', 'momentum-admin'), 'shared' => true],
		'construccion_video_alt'    => ['type' => 'text', 'label' => __('Texto alternativo / descripción accesible del video', 'momentum-admin'), 'shared' => true],
	];
}

/**
 * Sanitiza un valor crudo según el 'type' declarado en el schema de arriba.
 * Reutilizada tanto por register_post_meta() (sanitize_callback) como por el
 * guardado explícito del panel /admin/ (Momentum_Admin_Content_Pages), igual
 * que momentum_save_meta_fields() en inc/cpt/propiedades.php hace para
 * Propiedades/Terrenos.
 */
function momentum_sanitize_page_field($raw, array $field): string
{
	switch ($field['type']) {
		case 'html':
			return wp_kses_post((string) $raw);

		case 'image':
		case 'video':
			$id = absint($raw);

			return $id > 0 ? (string) $id : '';

		case 'url':
			$raw = trim((string) $raw);

			if ($raw === '' || strpos($raw, '<') !== false) {
				return '';
			}

			$clean = esc_url_raw($raw, ['http', 'https']);

			return ($clean !== '' && filter_var($clean, FILTER_VALIDATE_URL)) ? $clean : '';

		case 'bool':
			return $raw ? '1' : '0';

		case 'select':
			$allowed = array_keys($field['options'] ?? []);

			return in_array($raw, $allowed, true) ? (string) $raw : '';

		case 'text':
		default:
			return sanitize_text_field((string) $raw);
	}
}

/**
 * Registra los campos de ambos schemas como post meta del post type 'page'
 * -show_in_rest en false a propósito: este panel no usa el editor de
 * bloques ni la REST API para estos campos, así que no hace falta
 * exponerlos ahí-.
 */
function momentum_register_page_content_meta(): void
{
	$all_fields = array_merge(momentum_home_meta_schema(), momentum_nosotros_meta_schema(), momentum_construccion_meta_schema());

	foreach ($all_fields as $key => $field) {
		register_post_meta('page', $key, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => static function ($value) use ($field) {
				return momentum_sanitize_page_field($value, $field);
			},
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		]);
	}
}
add_action('init', 'momentum_register_page_content_meta');

/**
 * Valor de un campo COMPARTIDO (imagen o video: misma pieza en ambos
 * idiomas) desde cualquier idioma en el que se esté viendo la página. Lee
 * siempre de la página en el idioma por defecto del sitio -donde el panel
 * /admin/ los guarda-, resuelta vía momentum_get_translation() en vez de un
 * segundo lookup por slug (misma función que ya usa el resto del tema para
 * cruzar idiomas, ver inc/language.php).
 */
function momentum_get_shared_page_meta(int $current_post_id, string $key)
{
	$canonical_id = function_exists('momentum_get_translation')
		? momentum_get_translation($current_post_id, momentum_default_language())
		: $current_post_id;

	return get_post_meta($canonical_id, $key, true);
}

/**
 * Helper genérico para el sembrado (ver más abajo): solo escribe un valor si
 * el campo todavía está vacío, para nunca sobrescribir contenido que un
 * administrador ya haya modificado.
 */
function momentum_seed_page_meta_if_empty(int $post_id, string $key, string $value): void
{
	if ($value === '') {
		return;
	}

	if (get_post_meta($post_id, $key, true) !== '') {
		return;
	}

	update_post_meta($post_id, $key, $value);
}

/**
 * Da de alta -si aún no existe- un adjunto de la biblioteca de medios que
 * apunta a un archivo YA PRESENTE en el tema (wp-content/themes/momentum/assets/...),
 * sin copiar ni duplicar el archivo físico: el adjunto solo referencia esa
 * misma ruta. Idempotente por 'guid' (la URL del archivo): si ya se importó
 * antes, devuelve ese mismo ID en vez de crear uno nuevo.
 *
 * @return int ID del adjunto, o 0 si el archivo no existe o la inserción falla.
 */
function momentum_maybe_import_theme_asset_as_attachment(string $relative_path, string $mime_type, string $title): int
{
	$file_path = get_stylesheet_directory() . $relative_path;
	$file_url  = get_stylesheet_directory_uri() . $relative_path;

	if (! file_exists($file_path)) {
		return 0;
	}

	global $wpdb;

	$existing_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1",
		$file_url
	));

	if ($existing_id) {
		return $existing_id;
	}

	$attachment_id = wp_insert_attachment(
		[
			'guid'           => $file_url,
			'post_mime_type' => $mime_type,
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		],
		$file_path
	);

	if (is_wp_error($attachment_id) || ! $attachment_id) {
		return 0;
	}

	// '_wp_attached_file' (vía update_attached_file()) es lo que
	// get_attached_file()/wp_attachment_is() necesitan para reconocer el
	// tipo de archivo -se guarda siempre, sea cual sea la ubicación real del
	// archivo, porque get_attached_file() maneja bien una ruta absoluta-.
	update_attached_file($attachment_id, $file_path);

	/*
	 * wp_generate_attachment_metadata() SOLO se llama cuando el archivo vive
	 * DENTRO de wp-content/uploads/. Para un asset del tema (fuera de
	 * uploads, como aquí), generar y guardar esa metadata no aporta nada -y
	 * confirmado en pruebas: NO usarla evita que otro código que sí confíe
	 * en '_wp_attachment_metadata' asuma una ruta relativa a uploads que
	 * nunca existió-. La URL del adjunto en las plantillas se resuelve con
	 * get_the_guid(), no con wp_get_attachment_url() -esa sí reconstruye una
	 * URL rota cuando '_wp_attached_file' no vive bajo uploads, mientras que
	 * get_the_guid() siempre devuelve tal cual la URL correcta fijada arriba-.
	 */
	$uploads = wp_upload_dir();

	if (empty($uploads['error']) && strpos($file_path, $uploads['basedir']) === 0) {
		if (! function_exists('wp_generate_attachment_metadata')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$metadata = wp_generate_attachment_metadata($attachment_id, $file_path);

		if ($metadata) {
			wp_update_attachment_metadata($attachment_id, $metadata);
		}
	}

	return (int) $attachment_id;
}

/**
 * Contenido principal de "Quiénes somos" como HTML con <p> explícitos (mismo
 * marcado que TinyMCE guarda al editar vía wp_editor()), para que el
 * resultado visual sea idéntico al bloque de <p> que existía hardcodeado en
 * template-parts/nosotros/content.php antes de esta migración.
 */
function momentum_nosotros_default_content_html(string $lang): string
{
	$paragraphs = $lang === 'en'
		? [
			"Momentum Cocoyoc S.A. de C.V. is a real estate agency in Lomas de Cocoyoc with over 15 years of experience in the Morelos real estate market. Since our founding in 2008, we've specialized in the sale and rental of houses, land, and luxury residences in Lomas de Cocoyoc, offering every client a professional, approachable, and trustworthy service.",
			"Our commitment is to help you find the ideal property, whether it's a house for sale in Lomas de Cocoyoc, land to invest in, or a residence with strong value and a prime location near Mexico City.",
			"Thanks to our steady growth and the trust of our clients, we've become a reliable choice for those looking to buy a home in Lomas de Cocoyoc with the peace of mind of being backed by expert advisors.",
			'At Momentum Cocoyoc, our top priority is every client\'s satisfaction, supporting you through the entire process so your buying experience is clear, secure, and successful.',
		]
		: [
			'En Momentum Cocoyoc S.A. de C.V. somos una inmobiliaria en Lomas de Cocoyoc con más de 15 años de experiencia en el mercado de bienes raíces en Morelos. Desde nuestra fundación en 2008, nos especializamos en la venta y renta de casas, terrenos y residencias de lujo en Lomas de Cocoyoc, ofreciendo a cada cliente un servicio profesional, cercano y confiable.',
			'Nuestro compromiso es ayudarte a encontrar la propiedad ideal, ya sea una casa en venta en Lomas de Cocoyoc, un terreno para invertir o una residencia con plusvalía y ubicación privilegiada cerca de la CDMX.',
			'Gracias a nuestro crecimiento constante y la confianza de nuestros clientes, hoy nos consolidamos como una opción segura para quienes buscan comprar casa en Lomas de Cocoyoc con la tranquilidad de estar respaldados por asesores expertos.',
			'En Momentum Cocoyoc, nuestro mayor objetivo es la satisfacción de cada cliente, acompañándote en todo el proceso para que tu experiencia de compra sea clara, segura y exitosa.',
		];

	return implode("\n\n", array_map(
		static fn (string $p): string => '<p>' . esc_html($p) . '</p>',
		$paragraphs
	));
}

/**
 * Igual que momentum_nosotros_default_content_html(), para "Nuestra experiencia".
 */
function momentum_construccion_default_content_html(string $lang): string
{
	$paragraphs = $lang === 'en'
		? [
			"At Momentum Cocoyoc, we bring your project to life backed by a multidisciplinary team of architects, engineers, urban planners, and home-construction specialists in Lomas de Cocoyoc. We're committed to delivering innovative, functional, high-quality solutions on every build.",
			"With over 15 years of real estate experience in Lomas de Cocoyoc, we specialize in designing and building modern residences and luxury homes tailored to each client's needs.",
			"Whether you're looking for a new home for sale in Lomas de Cocoyoc, Morelos, building the house of your dreams, or investing in land to build your legacy, we support you at every stage: from planning and architectural design to final delivery.",
			'At Momentum Cocoyoc, we turn your ideas into exclusive properties in Lomas de Cocoyoc, guaranteeing quality, style, and long-term value.',
		]
		: [
			'En Momentum Cocoyoc hacemos realidad tu proyecto con el respaldo de un equipo multidisciplinario de arquitectos, ingenieros, urbanistas y especialistas en construcción de casas en Lomas de Cocoyoc. Nuestro compromiso es ofrecer soluciones innovadoras, funcionales y de alta calidad en cada obra.',
			'Con más de 15 años de experiencia en bienes raíces en Lomas de Cocoyoc, nos especializamos en el diseño y construcción de residencias modernas y casas de lujo adaptadas a las necesidades de cada cliente.',
			'Ya sea que busques una casa nueva en venta en Lomas de Cocoyoc Morelos, construir la casa de tus sueños o invertir en un terreno para edificar tu patrimonio, te acompañamos en cada etapa: desde la planeación y diseño arquitectónico hasta la entrega final.',
			'En Momentum Cocoyoc transformamos tus ideas en propiedades exclusivas en Lomas de Cocoyoc, garantizando calidad, estilo y plusvalía.',
		];

	return implode("\n\n", array_map(
		static fn (string $p): string => '<p>' . esc_html($p) . '</p>',
		$paragraphs
	));
}

/**
 * Migración idempotente: siembra en post meta el contenido que hoy vive
 * hardcodeado en las plantillas de Nosotros y Construcción, en español e
 * inglés, solo la primera vez (guardia por opción, mismo criterio que
 * momentum_maybe_seed_default_zonas() en inc/cpt/zonas.php) y solo en los
 * campos que estén vacíos (momentum_seed_page_meta_if_empty() nunca
 * sobrescribe un valor ya capturado por un administrador). Si se borra la
 * opción guardia y se vuelve a ejecutar, es inofensivo: cada campo ya
 * lleno se salta igual.
 */
function momentum_maybe_seed_nosotros_construccion_content(): void
{
	if (get_option('momentum_nosotros_construccion_content_seeded')) {
		return;
	}

	$nosotros_es     = get_page_by_path('nosotros');
	$construccion_es = get_page_by_path('construccion');

	// Ninguna de las dos páginas existe todavía (instalación sin el
	// contenido base) -no hay nada que sembrar-. No se marca la opción, para
	// reintentar en la siguiente carga por si las páginas se crean después.
	if (! $nosotros_es && ! $construccion_es) {
		return;
	}

	if ($nosotros_es) {
		momentum_seed_page_meta_if_empty($nosotros_es->ID, 'nosotros_hero_title', __('Nosotros', 'momentum'));
		momentum_seed_page_meta_if_empty($nosotros_es->ID, 'nosotros_hero_subtitle', __('Más de 15 años acompañando a familias en Lomas de Cocoyoc.', 'momentum'));
		momentum_seed_page_meta_if_empty($nosotros_es->ID, 'nosotros_section_label', __('Quiénes somos', 'momentum'));
		momentum_seed_page_meta_if_empty($nosotros_es->ID, 'nosotros_section_content', momentum_nosotros_default_content_html('es'));
		// Imágenes actuales de la página (recepción y sala de juntas), ya
		// presentes en la biblioteca de medios -sin duplicar nada-.
		momentum_seed_page_meta_if_empty($nosotros_es->ID, 'nosotros_main_image', '87');
		momentum_seed_page_meta_if_empty($nosotros_es->ID, 'nosotros_secondary_image', '88');

		$nosotros_en = function_exists('momentum_find_translation') ? momentum_find_translation($nosotros_es->ID, 'en') : 0;

		if ($nosotros_en) {
			momentum_seed_page_meta_if_empty($nosotros_en, 'nosotros_hero_title', 'About Us');
			momentum_seed_page_meta_if_empty($nosotros_en, 'nosotros_hero_subtitle', 'Over 15 years supporting families in Lomas de Cocoyoc.');
			momentum_seed_page_meta_if_empty($nosotros_en, 'nosotros_section_label', 'Who We Are');
			momentum_seed_page_meta_if_empty($nosotros_en, 'nosotros_section_content', momentum_nosotros_default_content_html('en'));
		}
	}

	if ($construccion_es) {
		momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_hero_title', __('Construcción a la medida', 'momentum'));
		momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_hero_subtitle', __('Diseño y construcción de vivienda en Lomas de Cocoyoc, de principio a fin, con acompañamiento en cada etapa del proyecto.', 'momentum'));
		momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_section_label', __('Nuestra experiencia', 'momentum'));
		momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_section_content', momentum_construccion_default_content_html('es'));
		momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_cta_new_tab', '1');

		// Video: se importa el archivo YA usado hoy por la página
		// (assets/video/intro-optimized.mp4) como adjunto real de la
		// biblioteca de medios -sin copiar el archivo, ver
		// momentum_maybe_import_theme_asset_as_attachment()-. Si por
		// cualquier motivo la importación fallara, se cae a 'url' apuntando
		// al mismo archivo, para que la página nunca se quede sin video.
		$video_attachment_id = momentum_maybe_import_theme_asset_as_attachment(
			'/assets/video/intro-optimized.mp4',
			'video/mp4',
			__('Video introducción — Construcción', 'momentum')
		);

		if ($video_attachment_id) {
			momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_video_source', 'file');
			momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_video_file', (string) $video_attachment_id);
		} else {
			momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_video_source', 'url');
			momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_video_url', get_stylesheet_directory_uri() . '/assets/video/intro-optimized.mp4');
		}

		// CTA: reutiliza el mismo helper y el mismo teléfono configurado que
		// ya arma este enlace hoy (template-parts/construccion/intro.php),
		// para sembrar EXACTAMENTE el enlace de WhatsApp que el botón tiene
		// en este momento -no un valor inventado-.
		if (function_exists('momentum_phone_to_whatsapp_url') && function_exists('momentum_get_phone_1')) {
			$whatsapp_phone = momentum_get_phone_1() ?: momentum_get_phone_2();

			momentum_seed_page_meta_if_empty($construccion_es->ID, 'construccion_cta_text', __('Cotizar mi proyecto', 'momentum'));
			momentum_seed_page_meta_if_empty(
				$construccion_es->ID,
				'construccion_cta_url',
				momentum_phone_to_whatsapp_url($whatsapp_phone, __('Hola, me interesa cotizar mi proyecto de construcción, vengo desde su sitio web.', 'momentum'))
			);
		}

		$construccion_en = function_exists('momentum_find_translation') ? momentum_find_translation($construccion_es->ID, 'en') : 0;

		if ($construccion_en) {
			momentum_seed_page_meta_if_empty($construccion_en, 'construccion_hero_title', 'Custom Construction');
			momentum_seed_page_meta_if_empty($construccion_en, 'construccion_hero_subtitle', 'Home design and construction in Lomas de Cocoyoc, from start to finish, with support at every stage of the project.');
			momentum_seed_page_meta_if_empty($construccion_en, 'construccion_section_label', 'Our Experience');
			momentum_seed_page_meta_if_empty($construccion_en, 'construccion_section_content', momentum_construccion_default_content_html('en'));
			momentum_seed_page_meta_if_empty($construccion_en, 'construccion_cta_new_tab', '1');

			if (function_exists('momentum_phone_to_whatsapp_url') && function_exists('momentum_get_phone_1')) {
				$whatsapp_phone = momentum_get_phone_1() ?: momentum_get_phone_2();

				// El mensaje de WhatsApp en inglés viene del mismo catálogo de
				// traducción (.po) que ya usa la página hoy -se activa el
				// locale en inglés solo para resolver este __() y se
				// restaura de inmediato, sin afectar nada más de este hook-.
				switch_to_locale('en_US');
				$whatsapp_message_en = __('Hola, me interesa cotizar mi proyecto de construcción, vengo desde su sitio web.', 'momentum');
				restore_previous_locale();

				momentum_seed_page_meta_if_empty($construccion_en, 'construccion_cta_text', 'Get a Quote');
				momentum_seed_page_meta_if_empty(
					$construccion_en,
					'construccion_cta_url',
					momentum_phone_to_whatsapp_url($whatsapp_phone, $whatsapp_message_en)
				);
			}
		}
	}

	update_option('momentum_nosotros_construccion_content_seeded', 1);
}
add_action('init', 'momentum_maybe_seed_nosotros_construccion_content', 7);

/**
 * Migración idempotente equivalente para Inicio: siembra la imagen que hoy
 * usa la sección "Sobre Momentum Cocoyoc" (template-parts/home/about.php),
 * solo si el campo aún está vacío. Guardia y criterio idénticos a
 * momentum_maybe_seed_nosotros_construccion_content(), en una función propia
 * porque Inicio tiene un solo campo administrable (a propósito: es lo único
 * que se pidió hacer editable de esa página).
 */
function momentum_maybe_seed_home_content(): void
{
	if (get_option('momentum_home_content_seeded')) {
		return;
	}

	$home_es = get_page_by_path('inicio');

	if (! $home_es) {
		return;
	}

	// Mismo adjunto que about.php trae hardcodeado como fallback -la foto
	// nocturna de la fachada ya en uso hoy en esa sección-.
	momentum_seed_page_meta_if_empty($home_es->ID, 'home_about_image', '1062');

	update_option('momentum_home_content_seeded', 1);
}
add_action('init', 'momentum_maybe_seed_home_content', 7);
