<?php
/**
 * Performance: LCP discovery hint, custom-logo byte savings, and
 * forward-looking WebP output for new image uploads.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * WordPress core auto-detects the first <img> in the DOM as a likely LCP
 * candidate and adds fetchpriority="high" to it (see
 * wp_get_loading_optimization_attributes(), WP 6.3+). On every page here
 * that first image is the site logo in the header, not the real LCP
 * element, so core's guess wastes priority bandwidth on a 185x84 logo.
 * Cap the requested size and correct the `sizes` attribute so the browser
 * at least picks a byte-appropriate file, and drop the misapplied
 * fetchpriority hint so it doesn't compete with the true LCP resource.
 */
add_filter('get_custom_logo_image_size', function () {
	return 'medium_large';
});

add_filter('get_custom_logo', function (string $html): string {
	// The logo renders at a fixed 84px height (see .momentum-site-header__logo
	// img in momentum.css), which computes to ~185px wide regardless of
	// viewport — safe to hardcode as a fixed `sizes` value.
	$html = preg_replace('/\ssizes="[^"]*"/', ' sizes="185px"', $html, 1);
	$html = preg_replace('/\sfetchpriority="high"/', '', $html, 1);

	return $html;
});

/**
 * From this point forward, generate WebP files (instead of JPEG) when
 * WordPress creates new attachment image sizes — new uploads and any
 * future thumbnail regeneration. This does not touch already-generated
 * JPEG/PNG files, so nothing currently referenced on the site changes.
 * Existing images can be migrated to WebP later as a separate, deliberate
 * step (e.g. regenerating thumbnails) once that's been visually verified.
 */
add_filter('image_editor_output_format', function (array $formats): array {
	$formats['image/jpeg'] = 'image/webp';

	return $formats;
});
