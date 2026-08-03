<?php
/**
 * Contact form handler for the Contacto page.
 *
 * Native WordPress wp_mail(), no plugin. Uses the Post/Redirect/Get
 * pattern so a page refresh never resubmits the form.
 */

if (! defined('ABSPATH')) {
	exit;
}

function momentum_handle_contact_form()
{
	if (empty($_POST['momentum_contact_submit'])) {
		return;
	}

	// No usa is_page('contacto'): esa comprobación por slug se rompería en la
	// traducción en inglés de esta página (slug 'contact', no 'contacto').
	if (! momentum_is_page_variant(['contacto'])) {
		return;
	}

	if (
		! isset($_POST['momentum_contact_nonce'])
		|| ! wp_verify_nonce($_POST['momentum_contact_nonce'], 'momentum_contact_form')
	) {
		return;
	}

	$nombre   = sanitize_text_field(wp_unslash($_POST['nombre'] ?? ''));
	$telefono = sanitize_text_field(wp_unslash($_POST['telefono'] ?? ''));
	$email    = sanitize_email(wp_unslash($_POST['email'] ?? ''));
	$mensaje  = sanitize_textarea_field(wp_unslash($_POST['mensaje'] ?? ''));

	$permalink = get_permalink(get_queried_object_id());

	if (empty($nombre) || empty($mensaje) || ! is_email($email)) {
		wp_safe_redirect(add_query_arg('contacto', 'error', $permalink));
		exit;
	}

	$body = "Nuevo mensaje desde el formulario de contacto del sitio.\n\n"
		. "Nombre: {$nombre}\n"
		. "Teléfono: {$telefono}\n"
		. "Email: {$email}\n\n"
		. "Mensaje:\n{$mensaje}";

	// Si el admin deja vacío el correo en Admin > General, cae al valor
	// original -este envío no puede quedarse sin destinatario en silencio-.
	$sent = wp_mail(
		momentum_get_email() ?: 'info@momentumcocoyoc.com',
		'Nuevo mensaje de contacto — Momentum Cocoyoc',
		$body,
		["Reply-To: {$nombre} <{$email}>"]
	);

	wp_safe_redirect(add_query_arg('contacto', $sent ? 'ok' : 'error', $permalink));
	exit;
}
add_action('template_redirect', 'momentum_handle_contact_form');
