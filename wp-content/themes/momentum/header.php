<?php
/**
 * Header — Momentum child theme.
 *
 * Mirrors Blocksy's header.php structure (head, body, #main-container,
 * hooks) so plugin/theme integrations that hook into blocksy:* actions
 * keep working. Only the header bar markup is replaced, via
 * template-parts/site-header.php.
 *
 * @package Momentum
 */

?><!doctype html>
<html <?php language_attributes(); ?><?php echo blocksy_html_attr() ?>>
<head>
	<?php do_action('blocksy:head:start') ?>

	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<?php wp_head(); ?>
	<?php do_action('blocksy:head:end') ?>
</head>

<body <?php body_class(); ?> <?php echo blocksy_body_attr() ?>>

<?php
if (function_exists('wp_body_open')) {
	wp_body_open();
}
?>

<div id="main-container">
	<?php
	do_action('blocksy:header:before');

	get_template_part('template-parts/site-header');

	do_action('blocksy:header:after');
	do_action('blocksy:content:before');
	?>

	<main <?php echo blocksy_main_attr() ?>>

		<?php
		do_action('blocksy:content:top');
		blocksy_before_current_template();
		?>
