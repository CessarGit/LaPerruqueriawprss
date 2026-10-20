<?php
/**
 * Lógica de desactivación del plugin (sin borrado de datos).
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Deactivator {

	/**
	 * Limpieza temporal al desactivar el plugin.
	 * No elimina tablas, registros ni configuraciones del negocio.
	 */
	public static function desactivar() {
		flush_rewrite_rules();
	}
}
