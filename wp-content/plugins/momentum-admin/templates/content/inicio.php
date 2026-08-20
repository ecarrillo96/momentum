<?php
/**
 * @var array{es: WP_Post, en: ?WP_Post} $pages
 * @var array $values_es  clave del schema => valor actual en la página ES
 * @var array $values_en  clave del schema => valor actual en la página EN (sin uso aquí: el único campo es compartido)
 * @var array $schema     momentum_home_meta_schema()
 *
 * A propósito, un solo campo: de toda la página de Inicio, solo la imagen
 * de la sección "Sobre Momentum Cocoyoc" se pidió hacer editable. El resto
 * de la home (hero, servicios, trust bar, FAQ, CTA final) no forma parte de
 * este alcance.
 */
if (! defined('ABSPATH')) {
	exit;
}

$error_code   = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';
$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';

$errores = [
	'nonce'    => __('La sesión del formulario expiró. Intenta guardar de nuevo.', 'momentum-admin'),
	'permisos' => __('No tienes permisos para guardar estos cambios.', 'momentum-admin'),
	'pagina'   => __('No se encontró la página "Inicio".', 'momentum-admin'),
];

$mensajes = [
	'actualizado' => __('Cambios guardados correctamente.', 'momentum-admin'),
];

$action_url = home_url('/admin/general-home-images/');
$image_id   = (int) $values_es['home_about_image'];
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Editar Inicio', 'momentum-admin'); ?></h1>
	<a class="momentum-admin-link" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver página pública', 'momentum-admin'); ?> &rarr;</a>
</div>

<?php if ($mensaje_code && isset($mensajes[$mensaje_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php echo esc_html($mensajes[$mensaje_code]); ?></p>
<?php endif; ?>

<?php if ($error_code && isset($errores[$error_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php echo esc_html($errores[$error_code]); ?></p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url($action_url); ?>" class="momentum-admin-form" id="momentum-home-content-form">
	<?php wp_nonce_field(Momentum_Admin_Content_Pages::NONCE_ACTION_HOME, Momentum_Admin_Content_Pages::NONCE_FIELD_HOME); ?>

	<details class="momentum-admin-collapse" open>
		<summary><?php esc_html_e('Sección "Sobre Momentum Cocoyoc"', 'momentum-admin'); ?></summary>

		<p class="momentum-admin-field-hint"><?php esc_html_e('Esta imagen es la misma en español e inglés: se captura una sola vez aquí y se usa en ambos idiomas.', 'momentum-admin'); ?></p>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field momentum-admin-field--full">
				<label><?php echo esc_html($schema['home_about_image']['label']); ?></label>
				<p class="momentum-admin-field-hint"><?php esc_html_e('Foto junto al texto "Inmobiliaria especializada en Lomas de Cocoyoc, Morelos". Recomendado: vertical u horizontal, mínimo 1200px de ancho.', 'momentum-admin'); ?></p>
				<input type="hidden" id="home_about_image" name="home_about_image" value="<?php echo esc_attr((string) $image_id); ?>">
				<div id="home-about-image-preview" class="momentum-admin-media-preview">
					<?php if ($image_id) : ?>
						<div class="momentum-gallery-item">
							<?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
							<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Quitar imagen', 'momentum-admin'); ?>">&times;</button>
						</div>
					<?php endif; ?>
				</div>
				<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="home-about-image-select"><?php esc_html_e('Seleccionar imagen', 'momentum-admin'); ?></button>
			</div>
		</div>
	</details>

	<div class="momentum-admin-form-actions">
		<button type="submit" name="momentum_admin_home_content_submit" value="1" class="momentum-admin-button" id="momentum-home-content-submit"><?php esc_html_e('Guardar cambios', 'momentum-admin'); ?></button>
	</div>
</form>
