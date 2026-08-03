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
	//
	// El contenedor principal de la galería renderiza hasta ~1232px de ancho,
	// más que los 1024px de 'large' -eso se veía borroso al estirarse-, así
	// que se pide primero '1536x1536' (tamaño core de WP, sin necesidad de
	// regenerar miniaturas; mismo patrón que template-parts/hero-image.php).
	$large_src = wp_get_attachment_image_src($id, '1536x1536') ?: wp_get_attachment_image_src($id, 'large');

	if (! $large_src) {
		continue; // adjunto inválido o borrado: se omite sin romper el layout
	}

	// 'thumbnail' (150x150) alcanza para la tira pequeña del lightbox (52px),
	// pero se ve borrosa estirada en la tira grande de la galería (~300px de
	// ancho); esa usa 'medium_large' para mantenerse nítida.
	$thumb_src        = wp_get_attachment_image_src($id, 'thumbnail');
	$gallery_thumb_src = wp_get_attachment_image_src($id, 'medium_large');

	$images[] = [
		'id'                   => $id,
		'large'                => $large_src[0],
		'large_width'          => $large_src[1],
		'large_height'         => $large_src[2],
		'thumb'                => $thumb_src ? $thumb_src[0] : $large_src[0],
		'thumb_width'          => $thumb_src ? $thumb_src[1] : $large_src[1],
		'thumb_height'         => $thumb_src ? $thumb_src[2] : $large_src[2],
		'gallery_thumb'        => $gallery_thumb_src ? $gallery_thumb_src[0] : $large_src[0],
		'gallery_thumb_width'  => $gallery_thumb_src ? $gallery_thumb_src[1] : $large_src[1],
		'gallery_thumb_height' => $gallery_thumb_src ? $gallery_thumb_src[2] : $large_src[2],
		'alt'                  => momentum_property_image_alt($id, $property_id),
		'title'                => get_the_title($id),
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
			aria-label="<?php echo esc_attr(sprintf(
				/* translators: %s: número total de fotos de la galería */
				__('Abrir galería, imagen 1 de %s', 'momentum'),
				(string) $total
			)); ?>"
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
						? esc_attr(sprintf(
							/* translators: %s: número de fotos restantes que no se muestran como miniatura */
							__('Ver las %s fotos restantes', 'momentum'),
							(string) $remaining
						))
						: esc_attr(sprintf(
							/* translators: 1: número de la foto, 2: número total de fotos */
							__('Abrir galería, imagen %1$s de %2$s', 'momentum'),
							(string) ($real_index + 1),
							(string) $total
						)); ?>"
				>
					<img
						src="<?php echo esc_url($image['gallery_thumb']); ?>"
						width="<?php echo esc_attr((string) $image['gallery_thumb_width']); ?>"
						height="<?php echo esc_attr((string) $image['gallery_thumb_height']); ?>"
						alt="<?php echo esc_attr($image['alt']); ?>"
						loading="lazy"
						decoding="async"
					>
					<?php if ($has_more) : ?>
						<span class="momentum-property-gallery__more" aria-hidden="true">+<?php echo esc_html(sprintf(
							/* translators: %s: número de fotos restantes, se muestra como "+6 fotos" sobre la última miniatura */
							__('%s fotos', 'momentum'),
							(string) $remaining
						)); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<div class="momentum-lightbox" data-momentum-lightbox hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Galería de imágenes de la propiedad', 'momentum'); ?>">
	<div class="momentum-lightbox__backdrop" data-momentum-lightbox-close></div>

	<div class="momentum-lightbox__content">
		<button type="button" class="momentum-lightbox__close" data-momentum-lightbox-close aria-label="<?php esc_attr_e('Cerrar galería', 'momentum'); ?>">&times;</button>

		<button type="button" class="momentum-lightbox__nav momentum-lightbox__nav--prev" data-momentum-lightbox-prev aria-label="<?php esc_attr_e('Imagen anterior', 'momentum'); ?>">&#8249;</button>

		<div class="momentum-lightbox__stage">
			<img src="" alt="" data-momentum-lightbox-image>
		</div>

		<button type="button" class="momentum-lightbox__nav momentum-lightbox__nav--next" data-momentum-lightbox-next aria-label="<?php esc_attr_e('Imagen siguiente', 'momentum'); ?>">&#8250;</button>

		<div class="momentum-lightbox__counter" data-momentum-lightbox-counter aria-live="polite"></div>
	</div>

	<?php if ($total > 1) : ?>
		<div class="momentum-lightbox__thumbs" data-momentum-lightbox-thumbs>
			<?php foreach ($images as $index => $image) : ?>
				<button type="button" class="momentum-lightbox__thumb" data-momentum-lightbox-goto="<?php echo esc_attr((string) $index); ?>" aria-label="<?php echo esc_attr(sprintf(
					/* translators: %s: número de la foto en la tira de miniaturas del lightbox */
					__('Ir a la imagen %s', 'momentum'),
					(string) ($index + 1)
				)); ?>">
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
