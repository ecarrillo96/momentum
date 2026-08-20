<?php
/**
 * Construcción — intro/about copy section.
 *
 * Video, portada, eyebrow, contenido y botón son editables desde
 * Admin > General > Construcción (Momentum_Admin_Content_Pages, plugin
 * momentum-admin); ver inc/page-content-fields.php. Video y portada son
 * compartidos entre idiomas (momentum_get_shared_page_meta()); el resto es
 * independiente por idioma. Si algún campo estuviera vacío -instalación
 * antes de que corra la migración, o un administrador lo borró sin
 * querer-, se cae al mismo comportamiento que tenía esta sección antes de
 * ser editable, para que la página nunca se quede sin video ni botón.
 */

if (! defined('ABSPATH')) {
	exit;
}

$construccion_page_id = get_queried_object_id();

$video_source = momentum_get_shared_page_meta($construccion_page_id, 'construccion_video_source');
$video_file   = (int) momentum_get_shared_page_meta($construccion_page_id, 'construccion_video_file');
$video_url    = (string) momentum_get_shared_page_meta($construccion_page_id, 'construccion_video_url');
$video_poster = (int) momentum_get_shared_page_meta($construccion_page_id, 'construccion_video_poster');
$video_alt    = (string) momentum_get_shared_page_meta($construccion_page_id, 'construccion_video_alt');

$video_src = '';

if ($video_source === 'file' && $video_file > 0 && wp_attachment_is('video', $video_file)) {
	// get_the_guid(), no wp_get_attachment_url(): un video importado desde un
	// archivo del tema (fuera de wp-content/uploads/, ver
	// momentum_maybe_import_theme_asset_as_attachment()) no tiene
	// '_wp_attached_file' apuntando dentro de uploads, y wp_get_attachment_url()
	// reconstruye una URL rota en ese caso. El guid siempre es la URL
	// correcta, tanto para ese archivo importado como para cualquier video
	// subido normalmente por un administrador desde la Biblioteca de medios.
	$video_src = get_the_guid($video_file);
} elseif ($video_source === 'url' && $video_url !== '') {
	$video_src = $video_url;
}

// Ningún campo de video configurado (o configurado de forma inválida): cae
// al video original del tema, exactamente el mismo comportamiento que tenía
// esta sección antes de ser editable.
if ($video_src === '') {
	$video_src = momentum_get_intro_video_url();
}

$video_poster_url = $video_poster > 0 ? wp_get_attachment_image_url($video_poster, 'large') : '';

$section_label   = get_post_meta($construccion_page_id, 'construccion_section_label', true);
$section_content = get_post_meta($construccion_page_id, 'construccion_section_content', true);

$cta_text    = get_post_meta($construccion_page_id, 'construccion_cta_text', true);
$cta_url     = get_post_meta($construccion_page_id, 'construccion_cta_url', true);
$cta_new_tab = get_post_meta($construccion_page_id, 'construccion_cta_new_tab', true);

// CTA sin configurar: mismo cálculo dinámico de siempre (WhatsApp con el
// teléfono y mensaje configurados en Admin > General), para que el botón
// nunca desaparezca en una instalación donde este campo aún esté vacío.
if ($cta_url === '') {
	$whatsapp_message = __('Hola, me interesa cotizar mi proyecto de construcción, vengo desde su sitio web.', 'momentum');
	$whatsapp_phone   = momentum_get_phone_1() ?: momentum_get_phone_2();
	$cta_url          = momentum_phone_to_whatsapp_url($whatsapp_phone, $whatsapp_message);
}

if ($cta_text === '') {
	$cta_text = __('Cotizar mi proyecto', 'momentum');
}

// '' (campo nunca guardado, instalación pre-migración) se trata igual que
// '1' -mismo comportamiento que el target="_blank" fijo de siempre-; solo
// '0' explícito (el administrador desmarcó la casilla) lo desactiva.
$cta_opens_new_tab = $cta_new_tab !== '0';
?>
<div class="momentum-container">
	<div class="momentum-construccion-video">
		<video
			class="momentum-construccion-video__player"
			src="<?php echo esc_url($video_src); ?>"
			controls
			playsinline
			preload="metadata"
			<?php if ($video_poster_url !== '') : ?>poster="<?php echo esc_url($video_poster_url); ?>"<?php endif; ?>
			<?php if ($video_alt !== '') : ?>aria-label="<?php echo esc_attr($video_alt); ?>"<?php endif; ?>
		></video>
	</div>
</div>

<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-prose momentum-prose--wide">
			<span class="momentum-eyebrow"><?php echo esc_html($section_label ?: __('Nuestra experiencia', 'momentum')); ?></span>

			<?php if ($section_content !== '') : ?>
				<?php
			// wpautop(): mismo motivo que en template-parts/nosotros/content.php
			// -el editor WYSIWYG del panel admin no siempre conserva <p>
			// explícitos-, ver el comentario allá.
			echo wp_kses_post(wpautop($section_content));
			?>
			<?php else : ?>
				<p>
					<?php esc_html_e('En Momentum Cocoyoc hacemos realidad tu proyecto con el respaldo de un equipo multidisciplinario de arquitectos, ingenieros, urbanistas y especialistas en construcción de casas en Lomas de Cocoyoc. Nuestro compromiso es ofrecer soluciones innovadoras, funcionales y de alta calidad en cada obra.', 'momentum'); ?>
				</p>

				<p>
					<?php esc_html_e('Con más de 15 años de experiencia en bienes raíces en Lomas de Cocoyoc, nos especializamos en el diseño y construcción de residencias modernas y casas de lujo adaptadas a las necesidades de cada cliente.', 'momentum'); ?>
				</p>

				<p>
					<?php esc_html_e('Ya sea que busques una casa nueva en venta en Lomas de Cocoyoc Morelos, construir la casa de tus sueños o invertir en un terreno para edificar tu patrimonio, te acompañamos en cada etapa: desde la planeación y diseño arquitectónico hasta la entrega final.', 'momentum'); ?>
				</p>

				<p>
					<?php esc_html_e('En Momentum Cocoyoc transformamos tus ideas en propiedades exclusivas en Lomas de Cocoyoc, garantizando calidad, estilo y plusvalía.', 'momentum'); ?>
				</p>
			<?php endif; ?>

			<?php if ($cta_url !== '') : ?>
				<div class="momentum-construccion-cta">
					<a
						class="momentum-btn momentum-btn--accent"
						href="<?php echo esc_url($cta_url); ?>"
						<?php if ($cta_opens_new_tab) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
					>
						<?php echo esc_html($cta_text); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
