<?php
/**
 * Capa de datos y gestión de mascotas para el cliente autenticado.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Mascotas {

	/**
	 * Mensajes de respuesta para la interfaz.
	 *
	 * @var array
	 */
	private static $mensajes = array(
		'error' => array(),
		'exito' => array(),
	);

	/**
	 * Inicializa los ganchos y el shortcode de mascotas.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'procesar_acciones_frontend' ) );
		add_action( 'delete_user', array( __CLASS__, 'manejar_eliminacion_usuario' ) );
		add_shortcode( 'perruqueria_mascotas', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Retorna el nombre de la tabla de mascotas.
	 *
	 * @return string
	 */
	public static function nombre_tabla() {
		global $wpdb;
		return $wpdb->prefix . 'perruqueria_mascotas';
	}

	/**
	 * Consulta las mascotas activas pertenecientes al cliente actual.
	 *
	 * @param int $cliente_id ID del cliente autenticado.
	 * @return array
	 */
	public static function listar_mascotas( $cliente_id ) {
		global $wpdb;

		$tabla      = self::nombre_tabla();
		$cliente_id = absint( $cliente_id );

		if ( $cliente_id <= 0 ) {
			return array();
		}

		$sql = $wpdb->prepare(
			"SELECT id, cliente_id, nombre, especie, raza, tamano, edad_anios, observaciones, activo
			 FROM {$tabla}
			 WHERE cliente_id = %d
			   AND activo = 1
			 ORDER BY id DESC",
			$cliente_id
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Obtiene los datos de una mascota específica validando que pertenezca al cliente.
	 *
	 * @param int $mascota_id ID de la mascota.
	 * @param int $cliente_id ID del cliente autenticado.
	 * @return array|null
	 */
	public static function obtener_mascota( $mascota_id, $cliente_id ) {
		global $wpdb;

		$tabla      = self::nombre_tabla();
		$mascota_id = absint( $mascota_id );
		$cliente_id = absint( $cliente_id );

		if ( $mascota_id <= 0 || $cliente_id <= 0 ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT id, cliente_id, nombre, especie, raza, tamano, edad_anios, observaciones, activo
			 FROM {$tabla}
			 WHERE id = %d
			   AND cliente_id = %d
			   AND activo = 1",
			$mascota_id,
			$cliente_id
		);

		return $wpdb->get_row( $sql, ARRAY_A );
	}

	/**
	 * Inserta una nueva mascota en la base de datos vinculada al cliente actual.
	 *
	 * @param int   $cliente_id ID del cliente autenticado.
	 * @param array $datos      Datos de la mascota sanitizados.
	 * @return int|WP_Error ID de la mascota creada o error.
	 */
	public static function crear_mascota( $cliente_id, array $datos ) {
		global $wpdb;

		$cliente_id = absint( $cliente_id );
		if ( $cliente_id <= 0 ) {
			return new WP_Error( 'no_autenticado', __( 'Usuario no válido.', 'la-perruqueria' ) );
		}

		$nombre = isset( $datos['nombre'] ) ? sanitize_text_field( $datos['nombre'] ) : '';
		if ( empty( $nombre ) ) {
			return new WP_Error( 'nombre_vacio', __( 'El nombre de la mascota es obligatorio.', 'la-perruqueria' ) );
		}

		$registro = array(
			'cliente_id'    => $cliente_id,
			'nombre'        => $nombre,
			'especie'       => isset( $datos['especie'] ) ? sanitize_text_field( $datos['especie'] ) : 'Perro',
			'raza'          => isset( $datos['raza'] ) ? sanitize_text_field( $datos['raza'] ) : '',
			'tamano'        => La_Perruqueria_Seguridad::sanitizar_tamano( isset( $datos['tamano'] ) ? $datos['tamano'] : 'mediano' ),
			'edad_anios'    => isset( $datos['edad_anios'] ) && '' !== $datos['edad_anios'] ? absint( $datos['edad_anios'] ) : null,
			'observaciones' => isset( $datos['observaciones'] ) ? sanitize_textarea_field( $datos['observaciones'] ) : '',
			'activo'        => 1,
		);

		$formatos = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d' );

		$insertado = $wpdb->insert( self::nombre_tabla(), $registro, $formatos );
		if ( false === $insertado ) {
			return new WP_Error( 'db_error', __( 'Error al registrar la mascota.', 'la-perruqueria' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Actualiza una mascota existente asegurando que cliente_id esté en la cláusula WHERE.
	 *
	 * @param int   $mascota_id ID de la mascota.
	 * @param int   $cliente_id ID del cliente autenticado (get_current_user_id()).
	 * @param array $datos      Datos de la mascota sanitizados.
	 * @return bool|WP_Error
	 */
	public static function editar_mascota( $mascota_id, $cliente_id, array $datos ) {
		global $wpdb;

		$mascota_id = absint( $mascota_id );
		$cliente_id = absint( $cliente_id );

		if ( $mascota_id <= 0 || $cliente_id <= 0 ) {
			return new WP_Error( 'invalido', __( 'Parámetros no válidos.', 'la-perruqueria' ) );
		}

		$nombre = isset( $datos['nombre'] ) ? sanitize_text_field( $datos['nombre'] ) : '';
		if ( empty( $nombre ) ) {
			return new WP_Error( 'nombre_vacio', __( 'El nombre de la mascota es obligatorio.', 'la-perruqueria' ) );
		}

		$actualizaciones = array(
			'nombre'        => $nombre,
			'especie'       => isset( $datos['especie'] ) ? sanitize_text_field( $datos['especie'] ) : 'Perro',
			'raza'          => isset( $datos['raza'] ) ? sanitize_text_field( $datos['raza'] ) : '',
			'tamano'        => La_Perruqueria_Seguridad::sanitizar_tamano( isset( $datos['tamano'] ) ? $datos['tamano'] : 'mediano' ),
			'edad_anios'    => isset( $datos['edad_anios'] ) && '' !== $datos['edad_anios'] ? absint( $datos['edad_anios'] ) : null,
			'observaciones' => isset( $datos['observaciones'] ) ? sanitize_textarea_field( $datos['observaciones'] ) : '',
		);

		$where = array(
			'id'         => $mascota_id,
			'cliente_id' => $cliente_id,
		);

		$formatos       = array( '%s', '%s', '%s', '%s', '%d', '%s' );
		$formatos_where = array( '%d', '%d' );

		$actualizado = $wpdb->update( self::nombre_tabla(), $actualizaciones, $where, $formatos, $formatos_where );
		if ( false === $actualizado ) {
			return new WP_Error( 'db_error', __( 'Error al actualizar la mascota.', 'la-perruqueria' ) );
		}

		return true;
	}

	/**
	 * Realiza un borrado lógico (activo = 0) asegurando que cliente_id esté en la cláusula WHERE.
	 *
	 * @param int $mascota_id ID de la mascota.
	 * @param int $cliente_id ID del cliente autenticado (get_current_user_id()).
	 * @return bool|WP_Error
	 */
	public static function desactivar_mascota( $mascota_id, $cliente_id ) {
		global $wpdb;

		$mascota_id = absint( $mascota_id );
		$cliente_id = absint( $cliente_id );

		if ( $mascota_id <= 0 || $cliente_id <= 0 ) {
			return new WP_Error( 'invalido', __( 'Parámetros no válidos.', 'la-perruqueria' ) );
		}

		$actualizado = $wpdb->update(
			self::nombre_tabla(),
			array( 'activo' => 0 ),
			array(
				'id'         => $mascota_id,
				'cliente_id' => $cliente_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		if ( false === $actualizado ) {
			return new WP_Error( 'db_error', __( 'Error al desactivar la mascota.', 'la-perruqueria' ) );
		}

		return true;
	}

	/**
	 * Maneja el borrado en cascada cuando se elimina un usuario de WordPress
	 * para evitar fallos por restricción de llave foránea.
	 *
	 * @param int $user_id ID del usuario eliminado en WordPress.
	 */
	public static function manejar_eliminacion_usuario( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}

		$tabla_citas    = $wpdb->prefix . 'perruqueria_citas';
		$tabla_mascotas = self::nombre_tabla();

		// 1. Eliminar citas asociadas al cliente primero
		$wpdb->delete( $tabla_citas, array( 'cliente_id' => $user_id ), array( '%d' ) );

		// 2. Eliminar mascotas del cliente
		$wpdb->delete( $tabla_mascotas, array( 'cliente_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * Procesa los envíos de formulario de creación, edición y desactivación de mascotas.
	 */
	public static function procesar_acciones_frontend() {
		if ( ! is_user_logged_in() || ! isset( $_POST['perruqueria_mascota_accion'] ) ) {
			return;
		}

		if ( ! isset( $_POST['perruqueria_mascota_nonce'] ) || ! La_Perruqueria_Seguridad::verificar_nonce( sanitize_text_field( wp_unslash( $_POST['perruqueria_mascota_nonce'] ) ), 'perruqueria_mascota_formulario' ) ) {
			self::$mensajes['error'][] = __( 'Sesión expirada o petición inválida.', 'la-perruqueria' );
			return;
		}

		$accion     = sanitize_text_field( wp_unslash( $_POST['perruqueria_mascota_accion'] ) );
		$cliente_id = La_Perruqueria_Seguridad::obtener_cliente_actual_id();

		$datos = array(
			'nombre'        => isset( $_POST['nombre'] ) ? wp_unslash( $_POST['nombre'] ) : '',
			'especie'       => isset( $_POST['especie'] ) ? wp_unslash( $_POST['especie'] ) : '',
			'raza'          => isset( $_POST['raza'] ) ? wp_unslash( $_POST['raza'] ) : '',
			'tamano'        => isset( $_POST['tamano'] ) ? wp_unslash( $_POST['tamano'] ) : '',
			'edad_anios'    => isset( $_POST['edad_anios'] ) ? wp_unslash( $_POST['edad_anios'] ) : '',
			'observaciones' => isset( $_POST['observaciones'] ) ? wp_unslash( $_POST['observaciones'] ) : '',
		);

		if ( 'crear' === $accion ) {
			$res = self::crear_mascota( $cliente_id, $datos );
			if ( is_wp_error( $res ) ) {
				self::$mensajes['error'][] = $res->get_error_message();
			} else {
				self::$mensajes['exito'][] = __( '¡Mascota registrada exitosamente!', 'la-perruqueria' );
			}
		} elseif ( 'editar' === $accion ) {
			$mascota_id = isset( $_POST['mascota_id'] ) ? absint( $_POST['mascota_id'] ) : 0;
			$res        = self::editar_mascota( $mascota_id, $cliente_id, $datos );
			if ( is_wp_error( $res ) ) {
				self::$mensajes['error'][] = $res->get_error_message();
			} else {
				self::$mensajes['exito'][] = __( 'Mascota actualizada correctamente.', 'la-perruqueria' );
			}
		} elseif ( 'desactivar' === $accion ) {
			$mascota_id = isset( $_POST['mascota_id'] ) ? absint( $_POST['mascota_id'] ) : 0;
			$res        = self::desactivar_mascota( $mascota_id, $cliente_id );
			if ( is_wp_error( $res ) ) {
				self::$mensajes['error'][] = $res->get_error_message();
			} else {
				self::$mensajes['exito'][] = __( 'Mascota eliminada del listado activo.', 'la-perruqueria' );
			}
		}
	}

	/**
	 * Obtiene los mensajes acumulados de la operación.
	 *
	 * @return array
	 */
	public static function obtener_mensajes() {
		return self::$mensajes;
	}

	/**
	 * Renderiza el shortcode [perruqueria_mascotas] (solo visible para usuarios autenticados).
	 *
	 * @return string HTML de la vista.
	 */
	public static function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="perruqueria-card perruqueria-auth-required">
				<h3><?php esc_html_e( 'Acceso exclusivo para clientes', 'la-perruqueria' ); ?></h3>
				<p><?php esc_html_e( 'Debes iniciar sesión para consultar o gestionar a tus mascotas.', 'la-perruqueria' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		ob_start();
		include PERRUQUERIA_PATH . 'templates/mascotas-crud.php';
		return ob_get_clean();
	}
}
