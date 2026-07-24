<?php
/**
 * Shared helpers for rendering propiedad data across Home, Venta, Renta,
 * Terrenos y las próximas plantillas de listado/detalle.
 */

if (! defined('ABSPATH')) {
	exit;
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
	$formatted = '$' . number_format($price, 0, '.', ',') . ' MXN';

	if ($tipo_operacion === 'Renta') {
		$formatted .= '/mes';
	}

	return $formatted;
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
 * URL of the Home hero poster (shown behind/before the autoplay video and
 * used as the page's LCP resource). Centralized here so hero.php and the
 * <link rel=preload> in inc/performance.php always request the same size.
 *
 * Attachment 65 is 1600x1342 originally; 'large' (1024x859, ~133KB) is
 * plenty given the video covers it within moments and a dark overlay sits
 * on top (see .momentum-hero::before in home.css). Falls back to 'full'
 * if that intermediate size is ever missing for this attachment.
 */
function momentum_get_hero_poster_url(): string
{
	$id = 65;

	return wp_get_attachment_image_url($id, 'large')
		?: wp_get_attachment_image_url($id, 'full')
		?: '';
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
	];

	if (! isset($icons[$name])) {
		return '';
	}

	return $icons[$name];
}
