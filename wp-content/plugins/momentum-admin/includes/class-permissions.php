<?php
/**
 * Capacidad mínima requerida para acceder al panel.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Permissions
{
	const CAPABILITY = 'manage_options';

	public static function current_user_can_access(): bool
	{
		return current_user_can(self::CAPABILITY);
	}
}
