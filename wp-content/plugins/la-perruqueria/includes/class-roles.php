<?php
/**
 * Gestión de roles y capacidades para La Perruquería.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Roles {

	/**
	 * Registra el rol de Cliente en WordPress.
	 */
	public static function registrar_roles() {
		add_role(
			'cliente',
			__( 'Cliente', 'la-perruqueria' ),
			array(
				'read' => true,
			)
		);
	}

	/**
	 * Bloquea el acceso al panel wp-admin para usuarios con rol Cliente,
	 * excluyendo peticiones AJAX y envíos a admin-post.php.
	 */
	public static function restringir_acceso_admin() {
		global $pagenow;

		// Permitir llamadas AJAX y envíos de formularios procesados por admin-post.php
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || 'admin-post.php' === $pagenow ) {
			return;
		}

		if ( is_admin() && is_user_logged_in() && current_user_can( 'cliente' ) && ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Oculta la barra superior de administración en el frontend para el rol Cliente.
	 *
	 * @param bool $mostrar Estado actual de visibilidad de la barra.
	 * @return bool
	 */
	public static function ocultar_admin_bar_clientes( $mostrar ) {
		if ( is_user_logged_in() && current_user_can( 'cliente' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $mostrar;
	}
}
