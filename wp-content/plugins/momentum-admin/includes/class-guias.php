<?php
/**
 * Módulo Guías: página estática de solo lectura que enlaza los manuales en
 * PDF (administrador y desarrollador) guardados en assets/guias/. No hay
 * CRUD ni AJAX, así que no requiere init().
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Guias
{
	public static function render(): void
	{
		$guias = [
			[
				'titulo'      => __('Guía del administrador', 'momentum-admin'),
				'descripcion' => __('Cómo agregar propiedades, terrenos y zonas, publicar o pasar a borrador, subir imágenes, y configurar contacto, Analytics, Ads y Meta Pixel.', 'momentum-admin'),
				'archivo'     => 'Guia-Admin-Momentum-Cocoyoc.pdf',
			],
			[
				'titulo'      => __('Guía del desarrollador', 'momentum-admin'),
				'descripcion' => __('Estructura del proyecto, dónde vive cada archivo, y cómo agregar campos a propiedades, terrenos y a la ficha pública.', 'momentum-admin'),
				'archivo'     => 'Guia-Desarrollador-Momentum-Cocoyoc.pdf',
			],
		];

		include MOMENTUM_ADMIN_PATH . 'templates/guias/list.php';
	}
}
