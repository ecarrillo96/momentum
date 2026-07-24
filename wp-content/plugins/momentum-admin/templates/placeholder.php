<?php
/**
 * @var string      $page_title
 * @var string|null $placeholder_message Opcional; si no se define, se usa el
 *                                       texto genérico de siempre (Terrenos).
 */
if (! defined('ABSPATH')) {
	exit;
}

$placeholder_message = $placeholder_message ?? __('Módulo pendiente de implementación', 'momentum-admin');
?>
<div class="momentum-admin-page-header">
	<h1><?php echo esc_html($page_title); ?></h1>
</div>
<div class="momentum-admin-placeholder">
	<p><?php echo esc_html($placeholder_message); ?></p>
</div>
