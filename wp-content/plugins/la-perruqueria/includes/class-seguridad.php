<?php
/**
 * Funciones de seguridad, validación y verificación de propiedad de datos.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Seguridad {

	/**
	 * Verifica si un nonce recibido es válido.
	 *
	 * @param string $nonce  Valor del nonce enviado.
	 * @param string $accion Nombre de la acción protegida.
	 * @return bool
	 */
	public static function verificar_nonce( $nonce, $accion ) {
		return ! empty( $nonce ) && (bool) wp_verify_nonce( $nonce, $accion );
	}

	/**
	 * Obtiene el ID del usuario actualmente autenticado.
	 *
	 * @return int ID del usuario o 0 si no hay sesión activa.
	 */
	public static function obtener_cliente_actual_id() {
		return (int) get_current_user_id();
	}

	/**
	 * Comprueba si una mascota pertenece al cliente actual o al ID indicado.
	 *
	 * @param int $mascota_id ID de la mascota.
	 * @param int $cliente_id ID del cliente (opcional, por defecto el usuario actual).
	 * @return bool
	 */
	public static function cliente_es_dueno_mascota( $mascota_id, $cliente_id = 0 ) {
		global $wpdb;

		$mascota_id = absint( $mascota_id );
		if ( $mascota_id <= 0 ) {
			return false;
		}

		if ( 0 === $cliente_id ) {
			$cliente_id = self::obtener_cliente_actual_id();
		}

		if ( $cliente_id <= 0 ) {
			return false;
		}

		$tabla = $wpdb->prefix . 'perruqueria_mascotas';

		$existe = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$tabla}
				 WHERE id = %d
				   AND cliente_id = %d
				   AND activo = 1",
				$mascota_id,
				$cliente_id
			)
		);

		return (int) $existe > 0;
	}

	/**
	 * Sanitiza y restringe el tamaño de la mascota a los valores permitidos del ENUM.
	 *
	 * @param string $tamano Valor recibido.
	 * @return string 'pequeno', 'mediano' o 'grande'.
	 */
	public static function sanitizar_tamano( $tamano ) {
		$tamano  = sanitize_text_field( $tamano );
		$validos = array( 'pequeno', 'mediano', 'grande' );

		return in_array( $tamano, $validos, true ) ? $tamano : 'mediano';
	}
}
