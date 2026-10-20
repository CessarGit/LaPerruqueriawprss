<?php
/**
 * Procedimiento de desinstalación de La Perruquería.
 * Solo se ejecuta cuando el usuario elimina el plugin desde el panel de WordPress.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Borrado de tablas y datos condicionado a la opción del administrador
$purgar_datos = (bool) get_option( 'perruqueria_purgar_al_desinstalar', false );

if ( $purgar_datos ) {
	global $wpdb;

	// 1. Eliminar tablas en orden inverso de dependencia para respetar llaves foráneas
	$tabla_citas      = $wpdb->prefix . 'perruqueria_citas';
	$tabla_mascotas   = $wpdb->prefix . 'perruqueria_mascotas';
	$tabla_servicios  = $wpdb->prefix . 'perruqueria_servicios';
	$tabla_categorias = $wpdb->prefix . 'perruqueria_categorias';

	$wpdb->query( "DROP TABLE IF EXISTS {$tabla_citas}" );
	$wpdb->query( "DROP TABLE IF EXISTS {$tabla_mascotas}" );
	$wpdb->query( "DROP TABLE IF EXISTS {$tabla_servicios}" );
	$wpdb->query( "DROP TABLE IF EXISTS {$tabla_categorias}" );

	// 2. Eliminar opciones de base de datos
	delete_option( 'perruqueria_db_version' );
	delete_option( 'perruqueria_purgar_al_desinstalar' );
	delete_option( 'perruqueria_anticipacion_cancelacion' );
	delete_option( 'perruqueria_horarios_config' );
	delete_option( 'perruqueria_ajustes_horarios' );

	// 3. Eliminar rol de cliente
	remove_role( 'cliente' );
}
