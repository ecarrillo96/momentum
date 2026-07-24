<?php
/**
 * Property detail — main image + thumbnail strip + lightbox.
 *
 * Todos los triggers clicables (imagen principal, miniaturas, overlay
 * "+N fotos") comparten la clase .momentum-property-gallery-open y un
 * data-gallery-index; assets/js/property.js delega un único listener sobre
 * esa clase para abrir el lightbox en el índice correspondiente.
 *
 * El array de imágenes (IDs de attachments, nunca URLs sueltas) se calcula
 * aquí una sola vez y se pasa completo a JS vía data-gallery-images (JSON).
 */

if (! defined('ABSPATH')) {
	exit;
}

$property_id = get_the_ID();
$gallery_ids = momentum_get_galeria_ids($property_id);
$featured_id = has_post_thumbnail($property_id) ? get_post_thumbnail_id($property_id) : 0;

// La imagen destacada va primero; el resto de la galería se agrega sin
// duplicarla si su ID ya está incluido ahí.
$image_ids = [];
if ($featured_id) {
	$image_ids[] = $featured_id;
}
foreach ($gallery_ids as $id) {
	if (! in_array($id, $image_ids, true)) {
		$image_ids[] = $id;
	}
}

$images = [];
foreach ($image_ids as $id) {
	// wp_get_attachment_image_src() (unlike _url()) also returns the
	// registered width/height, so the main image and thumbs can carry real
	// width/height attributes and avoid layout shift while loading.
	$large_src = wp_get_attachment_image_src($id, 'large');

	if (! $large_src) {
		continue; // adjunto inválido o borrado: se omite sin romper el layout
	}

	$thumb_src = wp_get_attachment_image_src($id, 'thumbnail');

	$images[] = [
		'id'           => $id,
		'large'        => $large_src[0],
		'large_width'  => $large_src[1],
		'large_height' => $large_src[2],
		'thumb'        => $thumb_src ? $thumb_src[0] : $large_src[0],
		'thumb_width'  => $thumb_src ? $thumb_src[1] : $large_src[1],
		'thumb_height' => $thumb_src ? $thumb_src[2] : $large_src[2],
		'alt'          => get_post_meta($id, '_wp_attachment_image_alt', true) ?: get_the_title(),
		'title'        => get_the_title($id),
	];
}

if (! $images) {
	return;
}

$main       = $images[0];
$total      = count($images);
// Miniaturas visibles: hasta 4 imágenes DESPUÉS de la principal (que ya se
// muestra grande), para no repetirla también en la tira.
$thumbs     = array_slice($images, 1, 4);
$remaining  = max(0, $total - 1 - 4);
?>
<div class="momentum-property-gallery" data-momentum-gallery data-gallery-images="<?php echo esc_attr(wp_json_encode($images)); ?>">
	<div class="momentum-property-gallery__main">
		<button
			type="button"
			class="momentum-property-gallery-open momentum-property-gallery__main-trigger"
			data-gallery-index="0"
			aria-label="Abrir galería, imagen 1 de <?php echo esc_attr((string) $total); ?>"
		>
			<img
				src="<?php echo esc_url($main['large']); ?>"
				width="<?php echo esc_attr((string) $main['large_width']); ?>"
				height="<?php echo esc_attr((string) $main['large_height']); ?>"
				alt="<?php echo esc_attr($main['alt']); ?>"
				loading="eager"
				fetchpriority="high"
				decoding="async"
			>
		</button>
	</div>

	<?php if ($thumbs) : ?>
		<div class="momentum-property-gallery__thumbs">
			<?php foreach ($thumbs as $i => $image) : ?>
				<?php
				$real_index = $i + 1; // +1 porque la principal ya ocupa el índice 0
				$is_last    = $i === count($thumbs) - 1;
				$has_more   = $is_last && $remaining > 0;
				?>
				<button
					type="button"
					class="momentum-property-gallery-open momentum-property-gallery__thumb"
					data-gallery-index="<?php echo esc_attr((string) $real_index); ?>"
					aria-label="<?php echo $has_more
						? esc_attr('Ver las ' . $remaining . ' fotos restantes')
						: esc_attr('Abrir galería, imagen ' . ($real_index + 1) . ' de ' . $total); ?>"
				>
					<img
						src="<?php echo esc_url($image['thumb']); ?>"
						width="<?php echo esc_attr((string) $image['thumb_width']); ?>"
						height="<?php echo esc_attr((string) $image['thumb_height']); ?>"
						alt="<?php echo esc_attr($image['alt']); ?>"
						loading="lazy"
						decoding="async"
					>
					<?php if ($has_more) : ?>
						<span class="momentum-property-gallery__more" aria-hidden="true">+<?php echo esc_html((string) $remaining); ?> fotos</span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<div class="momentum-lightbox" data-momentum-lightbox hidden role="dialog" aria-modal="true" aria-label="Galería de imágenes de la propiedad">
	<div class="momentum-lightbox__backdrop" data-momentum-lightbox-close></div>

	<div class="momentum-lightbox__content">
		<button type="button" class="momentum-lightbox__close" data-momentum-lightbox-close aria-label="Cerrar galería">&times;</button>

		<button type="button" class="momentum-lightbox__nav momentum-lightbox__nav--prev" data-momentum-lightbox-prev aria-label="Imagen anterior">&#8249;</button>

		<div class="momentum-lightbox__stage">
			<img src="" alt="" data-momentum-lightbox-image>
		</div>

		<button type="button" class="momentum-lightbox__nav momentum-lightbox__nav--next" data-momentum-lightbox-next aria-label="Imagen siguiente">&#8250;</button>

		<div class="momentum-lightbox__counter" data-momentum-lightbox-counter aria-live="polite"></div>
	</div>

	<?php if ($total > 1) : ?>
		<div class="momentum-lightbox__thumbs" data-momentum-lightbox-thumbs>
			<?php foreach ($images as $index => $image) : ?>
				<button type="button" class="momentum-lightbox__thumb" data-momentum-lightbox-goto="<?php echo esc_attr((string) $index); ?>" aria-label="Ir a la imagen <?php echo esc_attr((string) ($index + 1)); ?>">
					<img
						src="<?php echo esc_url($image['thumb']); ?>"
						width="<?php echo esc_attr((string) $image['thumb_width']); ?>"
						height="<?php echo esc_attr((string) $image['thumb_height']); ?>"
						alt="<?php echo esc_attr($image['alt']); ?>"
						loading="lazy"
						decoding="async"
					>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
