<?php
/**
 * Capa de datos para servicios y categorías sobre wp_perruqueria_servicios y wp_perruqueria_categorias.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Servicios {

	/**
	 * Inicializa el shortcode del catálogo de servicios.
	 */
	public static function init() {
		add_shortcode( 'perruqueria_catalogo', array( __CLASS__, 'render_catalogo_shortcode' ) );
	}

	/**
	 * Retorna el nombre de la tabla de servicios con prefijo.
	 *
	 * @return string
	 */
	public static function tabla_servicios() {
		global $wpdb;
		return $wpdb->prefix . 'perruqueria_servicios';
	}

	/**
	 * Retorna el nombre de la tabla de categorías con prefijo.
	 *
	 * @return string
	 */
	public static function tabla_categorias() {
		global $wpdb;
		return $wpdb->prefix . 'perruqueria_categorias';
	}

	/**
	 * Retorna el nombre de la tabla de citas con prefijo.
	 *
	 * @return string
	 */
	public static function tabla_citas() {
		global $wpdb;
		return $wpdb->prefix . 'perruqueria_citas';
	}

	/**
	 * Obtiene todas las categorías disponibles.
	 *
	 * @return array
	 */
	public static function listar_categorias() {
		global $wpdb;

		$tabla = self::tabla_categorias();
		return $wpdb->get_results( "SELECT id, nombre FROM {$tabla} ORDER BY nombre ASC", ARRAY_A );
	}

	/**
	 * Comprueba si una categoría existe en la base de datos.
	 *
	 * @param int $categoria_id ID de la categoría.
	 * @return bool
	 */
	public static function existe_categoria( $categoria_id ) {
		global $wpdb;

		$tabla        = self::tabla_categorias();
		$categoria_id = absint( $categoria_id );

		if ( $categoria_id <= 0 ) {
			return false;
		}

		$existe = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE id = %d", $categoria_id )
		);

		return (int) $existe > 0;
	}

	/**
	 * Lista todos los servicios (activos e inactivos) con su categoría para administración.
	 *
	 * @return array
	 */
	public static function listar_servicios_admin() {
		global $wpdb;

		$t_serv = self::tabla_servicios();
		$t_cat  = self::tabla_categorias();

		$sql = "SELECT s.id, s.categoria_id, s.nombre, s.descripcion, s.duracion_min, s.precio, s.activo,
		               c.nombre AS categoria_nombre
		        FROM {$t_serv} s
		        INNER JOIN {$t_cat} c ON s.categoria_id = c.id
		        ORDER BY c.nombre ASC, s.nombre ASC";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Obtiene un servicio individual por su ID.
	 *
	 * @param int $servicio_id ID del servicio.
	 * @return array|null
	 */
	public static function obtener_servicio( $servicio_id ) {
		global $wpdb;

		$t_serv      = self::tabla_servicios();
		$t_cat       = self::tabla_categorias();
		$servicio_id = absint( $servicio_id );

		if ( $servicio_id <= 0 ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT s.id, s.categoria_id, s.nombre, s.descripcion, s.duracion_min, s.precio, s.activo,
			        c.nombre AS categoria_nombre
			 FROM {$t_serv} s
			 INNER JOIN {$t_cat} c ON s.categoria_id = c.id
			 WHERE s.id = %d",
			$servicio_id
		);

		return $wpdb->get_row( $sql, ARRAY_A );
	}

	/**
	 * Crea un nuevo servicio validando integridad de datos.
	 *
	 * @param array $datos Datos enviados desde el formulario.
	 * @return int|WP_Error ID del nuevo servicio o WP_Error.
	 */
	public static function crear_servicio( array $datos ) {
		global $wpdb;

		$validacion = self::validar_datos_servicio( $datos );
		if ( is_wp_error( $validacion ) ) {
			return $validacion;
		}

		$insertado = $wpdb->insert(
			self::tabla_servicios(),
			$validacion,
			array( '%d', '%s', '%s', '%d', '%f', '%d' )
		);

		if ( false === $insertado ) {
			return new WP_Error( 'db_error', __( 'Error al guardar el servicio en la base de datos.', 'la-perruqueria' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Edita un servicio existente previa validación.
	 *
	 * @param int   $servicio_id ID del servicio.
	 * @param array $datos       Datos actualizados.
	 * @return bool|WP_Error
	 */
	public static function editar_servicio( $servicio_id, array $datos ) {
		global $wpdb;

		$servicio_id = absint( $servicio_id );
		if ( $servicio_id <= 0 ) {
			return new WP_Error( 'id_invalido', __( 'Identificador de servicio no válido.', 'la-perruqueria' ) );
		}

		$validacion = self::validar_datos_servicio( $datos );
		if ( is_wp_error( $validacion ) ) {
			return $validacion;
		}

		$actualizado = $wpdb->update(
			self::tabla_servicios(),
			$validacion,
			array( 'id' => $servicio_id ),
			array( '%d', '%s', '%s', '%d', '%f', '%d' ),
			array( '%d' )
		);

		if ( false === $actualizado ) {
			return new WP_Error( 'db_error', __( 'Error al actualizar el servicio en la base de datos.', 'la-perruqueria' ) );
		}

		return true;
	}

	/**
	 * Alterna el estado activo/inactivo de un servicio.
	 *
	 * @param int $servicio_id ID del servicio.
	 * @return bool|WP_Error
	 */
	public static function alternar_activo( $servicio_id ) {
		global $wpdb;

		$servicio = self::obtener_servicio( $servicio_id );
		if ( ! $servicio ) {
			return new WP_Error( 'no_encontrado', __( 'Servicio no encontrado.', 'la-perruqueria' ) );
		}

		$nuevo_estado = ( (int) $servicio['activo'] === 1 ) ? 0 : 1;

		$actualizado = $wpdb->update(
			self::tabla_servicios(),
			array( 'activo' => $nuevo_estado ),
			array( 'id' => absint( $servicio_id ) ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $actualizado;
	}

	/**
	 * Elimina físicamente un servicio solo si no tiene citas asociadas.
	 *
	 * @param int $servicio_id ID del servicio.
	 * @return bool|WP_Error
	 */
	public static function eliminar_servicio( $servicio_id ) {
		global $wpdb;

		$servicio_id = absint( $servicio_id );
		if ( $servicio_id <= 0 ) {
			return new WP_Error( 'id_invalido', __( 'Identificador de servicio no válido.', 'la-perruqueria' ) );
		}

		$t_citas = self::tabla_citas();

		// Comprobar si el servicio tiene citas asociadas
		$citas_asociadas = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$t_citas} WHERE servicio_id = %d", $servicio_id )
		);

		if ( $citas_asociadas > 0 ) {
			return new WP_Error(
				'servicio_con_citas',
				__( 'No se puede eliminar físicamente este servicio porque tiene citas registradas. Desactívalo en su lugar para mantener el historial.', 'la-perruqueria' )
			);
		}

		$eliminado = $wpdb->delete(
			self::tabla_servicios(),
			array( 'id' => $servicio_id ),
			array( '%d' )
		);

		if ( false === $eliminado ) {
			return new WP_Error( 'db_error', __( 'Error al eliminar el servicio.', 'la-perruqueria' ) );
		}

		return true;
	}

	/**
	 * Valida y sanitiza los campos requeridos para un servicio.
	 *
	 * @param array $datos Datos de entrada.
	 * @return array|WP_Error
	 */
	private static function validar_datos_servicio( array $datos ) {
		$nombre       = isset( $datos['nombre'] ) ? sanitize_text_field( $datos['nombre'] ) : '';
		$categoria_id = isset( $datos['categoria_id'] ) ? absint( $datos['categoria_id'] ) : 0;
		$duracion_min = isset( $datos['duracion_min'] ) ? absint( $datos['duracion_min'] ) : 0;
		$precio_raw   = isset( $datos['precio'] ) ? $datos['precio'] : '';
		$descripcion  = isset( $datos['descripcion'] ) ? sanitize_textarea_field( $datos['descripcion'] ) : '';
		$activo       = ! empty( $datos['activo'] ) ? 1 : 0;

		// 1. Nombre obligatorio
		if ( empty( $nombre ) ) {
			return new WP_Error( 'nombre_obligatorio', __( 'El nombre del servicio es obligatorio.', 'la-perruqueria' ) );
		}

		// 2. Categoría existente
		if ( ! self::existe_categoria( $categoria_id ) ) {
			return new WP_Error( 'categoria_invalida', __( 'La categoría seleccionada no existe.', 'la-perruqueria' ) );
		}

		// 3. Duración como entero positivo
		if ( $duracion_min <= 0 ) {
			return new WP_Error( 'duracion_invalida', __( 'La duración debe ser un número entero positivo mayor a cero minutos.', 'la-perruqueria' ) );
		}

		// 4. Precio mayor o igual a cero
		if ( ! is_numeric( $precio_raw ) || floatval( $precio_raw ) < 0 ) {
			return new WP_Error( 'precio_invalido', __( 'El precio debe ser un valor numérico mayor o igual a cero.', 'la-perruqueria' ) );
		}

		$precio = round( floatval( $precio_raw ), 2 );

		return array(
			'categoria_id' => $categoria_id,
			'nombre'       => $nombre,
			'descripcion'  => $descripcion,
			'duracion_min' => $duracion_min,
			'precio'       => $precio,
			'activo'       => $activo,
		);
	}

	/**
	 * Retorna la lista plana de servicios activos para desplegables de reserva.
	 *
	 * @return array
	 */
	public static function listar_servicios_activos() {
		global $wpdb;

		$t_serv = self::tabla_servicios();
		$sql    = "SELECT id, categoria_id, nombre, descripcion, duracion_min, precio
		           FROM {$t_serv}
		           WHERE activo = 1
		           ORDER BY nombre ASC";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Retorna los servicios activos agrupados por categoría para el catálogo público.
	 *
	 * @return array Arreglo asociativo [nombre_categoria => [servicios...]].
	 */
	public static function obtener_servicios_activos_agrupados() {
		global $wpdb;

		$t_serv = self::tabla_servicios();
		$t_cat  = self::tabla_categorias();

		$sql = "SELECT s.id, s.categoria_id, s.nombre, s.descripcion, s.duracion_min, s.precio,
		               c.nombre AS categoria_nombre
		        FROM {$t_serv} s
		        INNER JOIN {$t_cat} c ON s.categoria_id = c.id
		        WHERE s.activo = 1
		        ORDER BY c.nombre ASC, s.nombre ASC";

		$servicios = $wpdb->get_results( $sql, ARRAY_A );
		$agrupados = array();

		foreach ( $servicios as $s ) {
			$cat_nombre = $s['categoria_nombre'];
			if ( ! isset( $agrupados[ $cat_nombre ] ) ) {
				$agrupados[ $cat_nombre ] = array();
			}
			$agrupados[ $cat_nombre ][] = $s;
		}

		return $agrupados;
	}

	/**
	 * Renderiza el shortcode [perruqueria_catalogo].
	 *
	 * @return string HTML del catálogo.
	 */
	public static function render_catalogo_shortcode() {
		$servicios_agrupados = self::obtener_servicios_activos_agrupados();

		ob_start();
		include PERRUQUERIA_PATH . 'templates/catalogo-servicios.php';
		return ob_get_clean();
	}
}
