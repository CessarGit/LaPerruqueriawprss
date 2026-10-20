<?php
/**
 * Plugin Name:       La Perruquería
 * Plugin URI:        https://laperruqueria.local
 * Description:       Agendamiento de servicios de higiene, cuidado y estética canina, con gestión de citas para el personal.
 * Version:           1.0.0
 * Author:            La Perruquería Team
 * Text Domain:       la-perruqueria
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package           La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Constantes globales del plugin
define( 'PERRUQUERIA_VERSION', '1.0.0' );
define( 'PERRUQUERIA_FILE', __FILE__ );
define( 'PERRUQUERIA_PATH', plugin_dir_path( __FILE__ ) );
define( 'PERRUQUERIA_URL', plugin_dir_url( __FILE__ ) );

// 2. Ganchos de ciclo de vida (activación y desactivación)
register_activation_hook(
	__FILE__,
	function () {
		require_once PERRUQUERIA_PATH . 'includes/class-roles.php';
		require_once PERRUQUERIA_PATH . 'includes/class-activator.php';
		La_Perruqueria_Activator::activar();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		require_once PERRUQUERIA_PATH . 'includes/class-deactivator.php';
		La_Perruqueria_Deactivator::desactivar();
	}
);

// 3. Carga del orquestador principal
require_once PERRUQUERIA_PATH . 'includes/class-plugin.php';

function la_perruqueria_iniciar() {
	return La_Perruqueria_Plugin::obtener_instancia();
}

la_perruqueria_iniciar();
