<?php
/** @var string $page */
if (! defined('ABSPATH')) {
	exit;
}

$current_user = wp_get_current_user();

$items = [
	'propiedades' => ['label' => __('Propiedades', 'momentum-admin'), 'icon' => 'dashicons-admin-home', 'url' => home_url('/admin/propiedades/')],
	'terrenos'    => ['label' => __('Terrenos', 'momentum-admin'), 'icon' => 'dashicons-palmtree', 'url' => home_url('/admin/terrenos/')],
	'zonas'       => ['label' => __('Zonas', 'momentum-admin'), 'icon' => 'dashicons-location-alt', 'url' => home_url('/admin/zonas/')],
	'general'     => [
		'label'    => __('General', 'momentum-admin'),
		'icon'     => 'dashicons-admin-generic',
		'url'      => home_url('/admin/general/'),
		// Submenú fijo (sin JS de acordeón): mismo criterio que WP core, donde
		// el link del padre y el primer hijo del submenú apuntan a la misma
		// pantalla ("Ajustes" > "General").
		'children' => [
			'general'               => ['label' => __('Configuración general', 'momentum-admin'), 'url' => home_url('/admin/general/')],
			'general-home-images'   => ['label' => __('Inicio', 'momentum-admin'), 'url' => home_url('/admin/general-home-images/')],
			'general-about'         => ['label' => __('Nosotros', 'momentum-admin'), 'url' => home_url('/admin/general-about/')],
			'general-construction'  => ['label' => __('Construcción', 'momentum-admin'), 'url' => home_url('/admin/general-construction/')],
			'general-seo'           => ['label' => __('SEO y traducción', 'momentum-admin'), 'url' => home_url('/admin/general-seo/')],
		],
	],
	'blog'        => ['label' => __('Blog', 'momentum-admin'), 'icon' => 'dashicons-admin-post', 'url' => home_url('/admin/blog/')],
	'traducciones' => ['label' => __('Traducciones', 'momentum-admin'), 'icon' => 'dashicons-translation', 'url' => home_url('/admin/traducciones/')],
	'estadisticas' => ['label' => __('Estadísticas', 'momentum-admin'), 'icon' => 'dashicons-chart-line', 'url' => home_url('/admin/estadisticas/')],
	'guias'        => ['label' => __('Guías', 'momentum-admin'), 'icon' => 'dashicons-media-document', 'url' => home_url('/admin/guias/')],
];
?>
<button type="button" class="momentum-admin-menu-toggle" id="momentum-admin-menu-toggle" aria-controls="momentum-admin-sidebar" aria-expanded="false">
	<span class="dashicons dashicons-menu"></span>
	<span><?php esc_html_e('Menú', 'momentum-admin'); ?></span>
</button>

<aside class="momentum-admin-sidebar" id="momentum-admin-sidebar">
	<div class="momentum-admin-brand momentum-admin-brand--center">
		<?php if (has_custom_logo()) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<span class="momentum-admin-brand-name">Momentum Cocoyoc</span>
		<?php endif; ?>
	</div>

	<div class="momentum-admin-user">
		<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
		<span class="momentum-admin-user-name"><?php echo esc_html($current_user->display_name ?: $current_user->user_login); ?></span>
	</div>

	<nav class="momentum-admin-nav" aria-label="<?php esc_attr_e('Menú principal', 'momentum-admin'); ?>">
		<?php foreach ($items as $slug => $item) :
			$children         = $item['children'] ?? [];
			$is_active_parent = $page === $slug || array_key_exists($page, $children);
		?>
			<a
				href="<?php echo esc_url($item['url']); ?>"
				class="momentum-admin-nav-link<?php echo $is_active_parent ? ' is-active' : ''; ?>"
				<?php echo $page === $slug ? 'aria-current="page"' : ''; ?>
			>
				<span class="dashicons <?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
				<span><?php echo esc_html($item['label']); ?></span>
			</a>

			<?php if (! empty($children)) : ?>
				<div class="momentum-admin-subnav">
					<?php foreach ($children as $child_slug => $child) : ?>
						<a
							href="<?php echo esc_url($child['url']); ?>"
							class="momentum-admin-subnav-link<?php echo $page === $child_slug ? ' is-active' : ''; ?>"
							<?php echo $page === $child_slug ? 'aria-current="page"' : ''; ?>
						>
							<?php echo esc_html($child['label']); ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>

		<a href="<?php echo esc_url(Momentum_Admin_Auth::logout_url()); ?>" class="momentum-admin-nav-link momentum-admin-nav-link--logout">
			<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
			<span><?php esc_html_e('Salir', 'momentum-admin'); ?></span>
		</a>
	</nav>
</aside>
