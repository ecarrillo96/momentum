<?php
/**
 * Asset loading for the Momentum child theme.
 */

if (! defined('ABSPATH')) {
	exit;
}

function momentum_enqueue_assets()
{
	$theme_version = wp_get_theme()->get('Version');

	wp_enqueue_style(
		'momentum-style',
		get_stylesheet_directory_uri() . '/assets/css/momentum.css',
		['ct-main-styles'],
		$theme_version
	);

	wp_enqueue_script(
		'momentum-header',
		get_stylesheet_directory_uri() . '/assets/js/header.js',
		[],
		$theme_version,
		true
	);

	if (is_front_page()) {
		wp_enqueue_style(
			'momentum-home',
			get_stylesheet_directory_uri() . '/assets/css/home.css',
			['momentum-style'],
			$theme_version
		);
	}

	if (is_page(['venta', 'renta', 'terrenos'])) {
		wp_enqueue_style(
			'momentum-listing',
			get_stylesheet_directory_uri() . '/assets/css/listing.css',
			['momentum-style'],
			$theme_version
		);
	}

	if (is_page(['construccion'])) {
		wp_enqueue_style(
			'momentum-hero-image',
			get_stylesheet_directory_uri() . '/assets/css/hero-image.css',
			['momentum-style'],
			$theme_version
		);
	}

	if (is_page(['contacto'])) {
		wp_enqueue_style(
			'momentum-contacto',
			get_stylesheet_directory_uri() . '/assets/css/contacto.css',
			['momentum-style'],
			$theme_version
		);
	}

	if (is_singular(['propiedades', 'terrenos'])) {
		wp_enqueue_style(
			'momentum-property',
			get_stylesheet_directory_uri() . '/assets/css/property.css',
			['momentum-style'],
			$theme_version
		);

		wp_enqueue_script(
			'momentum-property',
			get_stylesheet_directory_uri() . '/assets/js/property.js',
			[],
			$theme_version,
			true
		);
	}

	// Blocksy enqueues its page-title stylesheet on every request (it assumes
	// its own template calls blocksy_output_content_title()), but every
	// Momentum template renders its own hero/page-hero markup instead — the
	// .ct-page-title block never exists in the DOM here. Confirmed via HTML
	// source inspection: the file loads render-blocking on 100% of pages
	// with zero matching elements. Skip it during the Customizer preview so
	// live-editing Blocksy's page title settings still works as expected.
	if (! is_customize_preview()) {
		wp_dequeue_style('ct-page-title-styles');
	}
}
add_action('wp_enqueue_scripts', 'momentum_enqueue_assets', 20);
