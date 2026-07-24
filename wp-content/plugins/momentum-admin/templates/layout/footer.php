<?php
/** @var string $page */
if (! defined('ABSPATH')) {
	exit;
}
?>
</div><!-- .momentum-admin-shell -->
<?php
/*
 * wp_editor() (usado en Blog) encola sus scripts (editor, quicktags,
 * tinymce) de forma diferida, enganchados a la acción
 * 'wp_print_footer_scripts' -normalmente disparada por wp_footer() en el
 * front-end público, que esta plantilla no usa-. Hay que dispararla a mano
 * ANTES de wp_print_scripts() para que esos handles ya estén encolados
 * cuando se imprime la cola.
 */
if (($page ?? '') === 'blog') {
	wp_print_footer_scripts();
}

/*
 * wp_print_scripts() sin argumentos imprime TODA la cola encolada por
 * Momentum_Admin_Assets (jquery siempre + el bundle de wp_enqueue_media()
 * cuando aplica), incluyendo dependencias hermanas como media-audiovideo
 * que un listado manual de handles omitía.
 */
wp_print_scripts();

if (in_array($page ?? '', ['propiedades', 'terrenos', 'blog'], true)) {
	wp_print_media_templates();
}
?>
<?php if (($page ?? '') === 'zonas') : ?>
	<?php
	/*
	 * admin-panel.js se imprime como <script src> plano (no wp_enqueue_script),
	 * así que no puede recibir datos vía wp_localize_script(). Se expone un
	 * objeto global equivalente, mismo criterio que el resto de este archivo:
	 * datos propios de este panel, impresos directamente, sin encolar nada
	 * nuevo.
	 */
	?>
	<script>
		window.momentumAdminZonas = <?php echo wp_json_encode([
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('momentum_admin_zonas'),
		]); ?>;
	</script>
<?php endif; ?>
<script src="<?php echo esc_url(MOMENTUM_ADMIN_URL . 'assets/js/admin-panel.js?v=' . MOMENTUM_ADMIN_VERSION); ?>"></script>
</body>
</html>
