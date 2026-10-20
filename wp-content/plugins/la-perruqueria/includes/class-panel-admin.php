<?php
/**
 * Panel de administración en wp-admin: servicios, ajustes y listado de citas.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Panel_Admin {

	/**
	 * Citas mostradas por página en el panel del personal.
	 */
	const CITAS_POR_PAGINA = 20;

	/**
	 * Inicializa los ganchos de administración.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'registrar_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'procesar_acciones_admin' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'encolar_assets_admin' ) );
		add_action( 'admin_notices', array( __CLASS__, 'mostrar_aviso_temporal' ) );
	}

	/**
	 * Registra el menú principal y las páginas de administración en wp-admin.
	 */
	public static function registrar_menu() {
		add_menu_page(
			__( 'La Perruquería', 'la-perruqueria' ),
			__( 'La Perruquería', 'la-perruqueria' ),
			'manage_options',
			'la-perruqueria',
			array( __CLASS__, 'render_pantalla_servicios' ),
			'dashicons-pets',
			26
		);

		add_submenu_page(
			'la-perruqueria',
			__( 'Gestión de Servicios', 'la-perruqueria' ),
			__( 'Servicios', 'la-perruqueria' ),
			'manage_options',
			'la-perruqueria',
			array( __CLASS__, 'render_pantalla_servicios' )
		);

		add_submenu_page(
			'la-perruqueria',
			__( 'Gestión de Citas', 'la-perruqueria' ),
			__( 'Citas', 'la-perruqueria' ),
			'manage_options',
			'la-perruqueria-citas',
			array( __CLASS__, 'render_pantalla_citas' )
		);

		add_submenu_page(
			'la-perruqueria',
			__( 'Ajustes de Horarios y Disponibilidad', 'la-perruqueria' ),
			__( 'Ajustes', 'la-perruqueria' ),
			'manage_options',
			'la-perruqueria-ajustes',
			array( __CLASS__, 'render_pantalla_ajustes' )
		);
	}

	/**
	 * Encola los estilos del panel de administración únicamente en las pantallas del plugin.
	 *
	 * @param string $hook Identificador de la página de administración actual.
	 */
	public static function encolar_assets_admin( $hook ) {
		if ( false !== strpos( $hook, 'la-perruqueria' ) ) {
			wp_enqueue_style(
				'perruqueria-admin-css',
				PERRUQUERIA_URL . 'assets/css/admin.css',
				array(),
				PERRUQUERIA_VERSION
			);
		}
	}

	/**
	 * Guarda un aviso temporal (un minuto) para el usuario actual, en lugar de enviarlo por la URL.
	 *
	 * @param string $tipo  'exito' o 'error'.
	 * @param string $texto Mensaje a mostrar.
	 */
	private static function guardar_aviso( $tipo, $texto ) {
		set_transient(
			'perruqueria_admin_aviso_' . get_current_user_id(),
			array(
				'tipo'  => ( 'error' === $tipo ) ? 'error' : 'exito',
				'texto' => (string) $texto,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Redirige a una pantalla del plugin dejando un aviso temporal.
	 *
	 * @param string $pagina Slug de la pantalla (page=...).
	 * @param string $tipo   'exito' o 'error'.
	 * @param string $texto  Mensaje.
	 * @param array  $extra  Parámetros adicionales de la URL.
	 */
	private static function redirigir_con_aviso( $pagina, $tipo, $texto, array $extra = array() ) {
		self::guardar_aviso( $tipo, $texto );

		$url = add_query_arg(
			array_merge( array( 'page' => $pagina ), $extra ),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirige a una URL (ya validada) dejando un aviso temporal.
	 *
	 * @param string $url   URL de destino.
	 * @param string $tipo  'exito' o 'error'.
	 * @param string $texto Mensaje.
	 */
	private static function redirigir_a_url_con_aviso( $url, $tipo, $texto ) {
		self::guardar_aviso( $tipo, $texto );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Obtiene la URL de retorno enviada por el formulario, validada contra el propio sitio.
	 *
	 * @return string
	 */
	private static function url_retorno_citas() {
		$por_defecto = admin_url( 'admin.php?page=la-perruqueria-citas' );
		$raw         = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return wp_validate_redirect( $raw, $por_defecto );
	}

	/**
	 * Muestra (y elimina) el aviso temporal en las pantallas del plugin.
	 */
	public static function mostrar_aviso_temporal() {
		$pagina = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $pagina, 'la-perruqueria' ) ) {
			return;
		}

		$clave = 'perruqueria_admin_aviso_' . get_current_user_id();
		$aviso = get_transient( $clave );
		if ( ! is_array( $aviso ) || empty( $aviso['texto'] ) ) {
			return;
		}
		delete_transient( $clave );

		$clase = ( isset( $aviso['tipo'] ) && 'error' === $aviso['tipo'] ) ? 'notice-error' : 'notice-success';
		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $clase ),
			esc_html( $aviso['texto'] )
		);
	}

	/**
	 * Procesa las peticiones POST y GET de administración (servicios y ajustes).
	 */
	public static function procesar_acciones_admin() {
		if ( ! is_admin() || ! isset( $_REQUEST['perruqueria_admin_accion'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para realizar esta acción.', 'la-perruqueria' ) );
		}

		$accion = sanitize_text_field( wp_unslash( $_REQUEST['perruqueria_admin_accion'] ) );

		// 1. Guardar ajustes de horarios y disponibilidad (POST)
		if ( 'guardar_ajustes_horarios' === $accion && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'perruqueria_guardar_ajustes_accion', 'perruqueria_ajustes_nonce' );

			$datos = array(
				'dias_laborales'        => isset( $_POST['dias_laborales'] ) ? (array) $_POST['dias_laborales'] : array(),
				'hora_apertura'         => isset( $_POST['hora_apertura'] ) ? wp_unslash( $_POST['hora_apertura'] ) : '',
				'hora_cierre'           => isset( $_POST['hora_cierre'] ) ? wp_unslash( $_POST['hora_cierre'] ) : '',
				'dias_no_laborables'    => isset( $_POST['dias_no_laborables'] ) ? wp_unslash( $_POST['dias_no_laborables'] ) : '',
				'intervalo_min'         => isset( $_POST['intervalo_min'] ) ? wp_unslash( $_POST['intervalo_min'] ) : '',
				'anticipacion_reserva'  => isset( $_POST['anticipacion_reserva'] ) ? wp_unslash( $_POST['anticipacion_reserva'] ) : '',
				'anticipacion_cancelar' => isset( $_POST['anticipacion_cancelar'] ) ? wp_unslash( $_POST['anticipacion_cancelar'] ) : '',
				'max_dias_reserva'      => isset( $_POST['max_dias_reserva'] ) ? wp_unslash( $_POST['max_dias_reserva'] ) : '',
			);

			$resultado = La_Perruqueria_Horarios::guardar_ajustes( $datos );

			if ( is_wp_error( $resultado ) ) {
				self::redirigir_con_aviso( 'la-perruqueria-ajustes', 'error', $resultado->get_error_message() );
			}
			self::redirigir_con_aviso( 'la-perruqueria-ajustes', 'exito', __( 'Ajustes de horarios guardados correctamente.', 'la-perruqueria' ) );
		}

		// 2. Crear nuevo servicio (POST)
		if ( 'crear_servicio' === $accion && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'perruqueria_servicio_admin', 'perruqueria_nonce' );

			$datos = array(
				'nombre'       => isset( $_POST['nombre'] ) ? wp_unslash( $_POST['nombre'] ) : '',
				'categoria_id' => isset( $_POST['categoria_id'] ) ? wp_unslash( $_POST['categoria_id'] ) : '',
				'duracion_min' => isset( $_POST['duracion_min'] ) ? wp_unslash( $_POST['duracion_min'] ) : '',
				'precio'       => isset( $_POST['precio'] ) ? wp_unslash( $_POST['precio'] ) : '',
				'descripcion'  => isset( $_POST['descripcion'] ) ? wp_unslash( $_POST['descripcion'] ) : '',
				'activo'       => isset( $_POST['activo'] ) ? 1 : 0,
			);

			$resultado = La_Perruqueria_Servicios::crear_servicio( $datos );
			if ( is_wp_error( $resultado ) ) {
				self::redirigir_con_aviso( 'la-perruqueria', 'error', $resultado->get_error_message() );
			}
			self::redirigir_con_aviso( 'la-perruqueria', 'exito', __( 'Servicio creado correctamente.', 'la-perruqueria' ) );
		}

		// 3. Editar servicio existente (POST)
		if ( 'editar_servicio' === $accion && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'perruqueria_servicio_admin', 'perruqueria_nonce' );

			$servicio_id = isset( $_POST['servicio_id'] ) ? absint( $_POST['servicio_id'] ) : 0;
			$datos       = array(
				'nombre'       => isset( $_POST['nombre'] ) ? wp_unslash( $_POST['nombre'] ) : '',
				'categoria_id' => isset( $_POST['categoria_id'] ) ? wp_unslash( $_POST['categoria_id'] ) : '',
				'duracion_min' => isset( $_POST['duracion_min'] ) ? wp_unslash( $_POST['duracion_min'] ) : '',
				'precio'       => isset( $_POST['precio'] ) ? wp_unslash( $_POST['precio'] ) : '',
				'descripcion'  => isset( $_POST['descripcion'] ) ? wp_unslash( $_POST['descripcion'] ) : '',
				'activo'       => isset( $_POST['activo'] ) ? 1 : 0,
			);

			$resultado = La_Perruqueria_Servicios::editar_servicio( $servicio_id, $datos );
			if ( is_wp_error( $resultado ) ) {
				self::redirigir_con_aviso( 'la-perruqueria', 'error', $resultado->get_error_message(), array( 'editar_id' => $servicio_id ) );
			}
			self::redirigir_con_aviso( 'la-perruqueria', 'exito', __( 'Servicio actualizado correctamente.', 'la-perruqueria' ) );
		}

		// 4. Alternar estado activo / inactivo (GET)
		if ( 'alternar_activo' === $accion ) {
			$servicio_id = isset( $_GET['servicio_id'] ) ? absint( $_GET['servicio_id'] ) : 0;
			check_admin_referer( 'perruqueria_alternar_' . $servicio_id, '_wpnonce' );

			$resultado = La_Perruqueria_Servicios::alternar_activo( $servicio_id );
			if ( is_wp_error( $resultado ) ) {
				self::redirigir_con_aviso( 'la-perruqueria', 'error', $resultado->get_error_message() );
			}
			self::redirigir_con_aviso( 'la-perruqueria', 'exito', __( 'Estado de disponibilidad actualizado.', 'la-perruqueria' ) );
		}

		// 5. Eliminar físicamente el servicio (GET)
		if ( 'eliminar_servicio' === $accion ) {
			$servicio_id = isset( $_GET['servicio_id'] ) ? absint( $_GET['servicio_id'] ) : 0;
			check_admin_referer( 'perruqueria_eliminar_' . $servicio_id, '_wpnonce' );

			$resultado = La_Perruqueria_Servicios::eliminar_servicio( $servicio_id );
			if ( is_wp_error( $resultado ) ) {
				self::redirigir_con_aviso( 'la-perruqueria', 'error', $resultado->get_error_message() );
			}
			self::redirigir_con_aviso( 'la-perruqueria', 'exito', __( 'Servicio eliminado correctamente.', 'la-perruqueria' ) );
		}

		// 6. Confirmar o cancelar una cita desde el panel del personal (POST)
		if ( 'cambiar_estado_cita' === $accion && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$cita_id = isset( $_POST['cita_id'] ) ? absint( $_POST['cita_id'] ) : 0;
			check_admin_referer( 'perruqueria_cita_estado_' . $cita_id, 'perruqueria_cita_nonce' );

			$destino = self::url_retorno_citas();
			$nuevo   = isset( $_POST['nuevo_estado'] ) ? sanitize_key( wp_unslash( $_POST['nuevo_estado'] ) ) : '';

			// Desde este formulario solo se permite confirmar o cancelar; reprogramar tiene su propia pantalla.
			if ( ! in_array( $nuevo, array( 'confirmada', 'cancelada' ), true ) ) {
				self::redirigir_a_url_con_aviso( $destino, 'error', __( 'La acción solicitada no es válida.', 'la-perruqueria' ) );
			}

			$cita = La_Perruqueria_Citas_DB::obtener_cita( $cita_id );
			if ( ! $cita ) {
				self::redirigir_a_url_con_aviso( $destino, 'error', __( 'Cita no encontrada.', 'la-perruqueria' ) );
			}

			$transicion = La_Perruqueria_Reservas::verificar_transicion( $cita['estado'], $nuevo );
			if ( is_wp_error( $transicion ) ) {
				self::redirigir_a_url_con_aviso( $destino, 'error', $transicion->get_error_message() );
			}

			// El cambio solo se aplica si la cita sigue en el estado que se validó.
			$resultado = La_Perruqueria_Citas_DB::actualizar_estado_cita( $cita_id, $nuevo, $cita['estado'] );
			if ( is_wp_error( $resultado ) ) {
				self::redirigir_a_url_con_aviso( $destino, 'error', $resultado->get_error_message() );
			}

			$texto = ( 'confirmada' === $nuevo )
				? sprintf( /* translators: %d: ID de la cita */ __( 'La cita #%d fue confirmada.', 'la-perruqueria' ), $cita_id )
				: sprintf( /* translators: %d: ID de la cita */ __( 'La cita #%d fue cancelada.', 'la-perruqueria' ), $cita_id );

			self::redirigir_a_url_con_aviso( $destino, 'exito', $texto );
		}

		// 7. Reprogramar una cita (POST)
		if ( 'reprogramar_cita' === $accion && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$cita_id = isset( $_POST['cita_id'] ) ? absint( $_POST['cita_id'] ) : 0;
			check_admin_referer( 'perruqueria_cita_reprogramar_' . $cita_id, 'perruqueria_cita_nonce' );

			$destino = self::url_retorno_citas();
			$inicio  = isset( $_POST['inicio'] ) ? sanitize_text_field( wp_unslash( $_POST['inicio'] ) ) : '';

			if ( '' === $inicio ) {
				self::redirigir_con_aviso(
					'la-perruqueria-citas',
					'error',
					__( 'Selecciona un turno disponible para reprogramar la cita.', 'la-perruqueria' ),
					array( 'reprogramar' => $cita_id )
				);
			}

			$resultado = La_Perruqueria_Citas_DB::reprogramar_cita_atomica( $cita_id, $inicio );

			if ( is_wp_error( $resultado ) ) {
				$extra = array( 'reprogramar' => $cita_id );
				$fecha = substr( $inicio, 0, 10 );
				if ( '' !== self::validar_fecha_filtro( $fecha ) ) {
					$extra['fecha'] = $fecha;
				}
				self::redirigir_con_aviso( 'la-perruqueria-citas', 'error', $resultado->get_error_message(), $extra );
			}

			$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $inicio, wp_timezone() );
			self::redirigir_a_url_con_aviso(
				$destino,
				'exito',
				sprintf(
					/* translators: 1: ID de la cita, 2: fecha y hora nuevas */
					__( 'La cita #%1$d fue reprogramada para el %2$s.', 'la-perruqueria' ),
					$cita_id,
					$dt ? wp_date( 'd/m/Y H:i', $dt->getTimestamp() ) : $inicio
				)
			);
		}
	}

	/**
	 * Valida una fecha YYYY-MM-DD; devuelve cadena vacía si no es válida.
	 *
	 * @param mixed $valor Valor recibido.
	 * @return string
	 */
	private static function validar_fecha_filtro( $valor ) {
		$valor = sanitize_text_field( (string) $valor );
		$dt    = DateTimeImmutable::createFromFormat( 'Y-m-d', $valor, wp_timezone() );

		return ( $dt && $dt->format( 'Y-m-d' ) === $valor ) ? $valor : '';
	}

	/**
	 * Lee y valida los filtros del listado de citas (parámetros GET, solo lectura).
	 * Si el formulario no se ha enviado todavía, se muestra la agenda desde hoy.
	 *
	 * @return array Claves: desde, hasta, estado.
	 */
	public static function leer_filtros_citas() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$enviado = isset( $_GET['filtrar'] );
		$hoy     = ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );

		$desde  = $enviado ? ( isset( $_GET['fecha_desde'] ) ? self::validar_fecha_filtro( wp_unslash( $_GET['fecha_desde'] ) ) : '' ) : $hoy;
		$hasta  = $enviado && isset( $_GET['fecha_hasta'] ) ? self::validar_fecha_filtro( wp_unslash( $_GET['fecha_hasta'] ) ) : '';
		$estado = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $estado, array( 'pendiente', 'confirmada', 'reprogramada', 'cancelada' ), true ) ) {
			$estado = '';
		}

		return array(
			'desde'  => $desde,
			'hasta'  => $hasta,
			'estado' => $estado,
		);
	}

	/**
	 * Construye la cláusula WHERE y sus parámetros a partir de los filtros.
	 *
	 * @param array $filtros Filtros validados.
	 * @return array array( string $where, array $params ).
	 */
	private static function construir_where_citas( array $filtros ) {
		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $filtros['desde'] ) {
			$where[]  = 'c.inicio >= %s';
			$params[] = $filtros['desde'] . ' 00:00:00';
		}

		if ( '' !== $filtros['hasta'] ) {
			$dt_hasta = DateTimeImmutable::createFromFormat( 'Y-m-d', $filtros['hasta'], wp_timezone() );
			if ( $dt_hasta ) {
				$where[]  = 'c.inicio < %s';
				$params[] = $dt_hasta->modify( '+1 day' )->format( 'Y-m-d' ) . ' 00:00:00';
			}
		}

		if ( '' !== $filtros['estado'] ) {
			$where[]  = 'c.estado = %s';
			$params[] = $filtros['estado'];
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Cuenta las citas que cumplen los filtros (solo lectura).
	 *
	 * @param array $filtros Filtros validados.
	 * @return int
	 */
	public static function contar_citas_admin( array $filtros ) {
		global $wpdb;

		$t_citas = $wpdb->prefix . 'perruqueria_citas';
		list( $where, $params ) = self::construir_where_citas( $filtros );

		$sql = "SELECT COUNT(*) FROM {$t_citas} c WHERE {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Lista una página de citas con los datos de cliente, mascota y servicio (solo lectura).
	 *
	 * @param array $filtros Filtros validados.
	 * @param int   $pagina  Número de página (desde 1).
	 * @return array
	 */
	public static function listar_citas_admin( array $filtros, $pagina ) {
		global $wpdb;

		$pagina  = max( 1, absint( $pagina ) );
		$limite  = self::CITAS_POR_PAGINA;
		$offset  = ( $pagina - 1 ) * $limite;
		$t_citas = $wpdb->prefix . 'perruqueria_citas';
		$t_masc  = $wpdb->prefix . 'perruqueria_mascotas';
		$t_serv  = $wpdb->prefix . 'perruqueria_servicios';
		$t_users = $wpdb->users;
		$t_meta  = $wpdb->usermeta;

		list( $where, $params ) = self::construir_where_citas( $filtros );

		$params[] = $limite;
		$params[] = $offset;

		$sql = "SELECT c.id, c.cliente_id, c.inicio, c.fin, c.estado, c.notas,
		               u.display_name AS cliente_nombre, u.user_email AS cliente_correo,
		               um.meta_value AS cliente_telefono,
		               m.nombre AS mascota_nombre, m.especie AS mascota_especie,
		               s.nombre AS servicio_nombre
		        FROM {$t_citas} c
		        INNER JOIN {$t_users} u ON u.ID = c.cliente_id
		        INNER JOIN {$t_masc} m ON m.id = c.mascota_id AND m.cliente_id = c.cliente_id
		        INNER JOIN {$t_serv} s ON s.id = c.servicio_id
		        LEFT JOIN {$t_meta} um ON um.user_id = c.cliente_id AND um.meta_key = 'perruqueria_telefono'
		        WHERE {$where}
		        ORDER BY c.inicio ASC, c.id ASC
		        LIMIT %d OFFSET %d";

		$filas = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $filas ) ? $filas : array();
	}

	/**
	 * Renderiza la pantalla de gestión de servicios.
	 */
	public static function render_pantalla_servicios() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso no autorizado.', 'la-perruqueria' ) );
		}

		include PERRUQUERIA_PATH . 'templates/admin-servicios.php';
	}

	/**
	 * Renderiza el listado de citas del personal.
	 */
	public static function render_pantalla_citas() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso no autorizado.', 'la-perruqueria' ) );
		}

		// Pantalla de reprogramación de una cita concreta.
		$reprogramar_id = isset( $_GET['reprogramar'] ) ? absint( $_GET['reprogramar'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $reprogramar_id > 0 ) {
			self::render_pantalla_reprogramar( $reprogramar_id );
			return;
		}

		$filtros     = self::leer_filtros_citas();
		$total_citas = self::contar_citas_admin( $filtros );
		$paginas     = max( 1, (int) ceil( $total_citas / self::CITAS_POR_PAGINA ) );
		$pagina      = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pagina      = min( max( 1, $pagina ), $paginas );
		$citas       = self::listar_citas_admin( $filtros, $pagina );

		include PERRUQUERIA_PATH . 'templates/admin-panel-citas.php';
	}

	/**
	 * Renderiza la pantalla para reprogramar una cita: elegir fecha y turno disponible.
	 *
	 * @param int $cita_id ID de la cita a reprogramar.
	 */
	private static function render_pantalla_reprogramar( $cita_id ) {
		$cita    = La_Perruqueria_Citas_DB::obtener_cita_con_detalles( $cita_id );
		$cliente = $cita ? get_userdata( (int) $cita['cliente_id'] ) : false;

		$fecha  = isset( $_GET['fecha'] ) ? self::validar_fecha_filtro( wp_unslash( $_GET['fecha'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$turnos = null;
		$error  = '';

		if ( ! $cita ) {
			$error = __( 'Cita no encontrada.', 'la-perruqueria' );
		} else {
			$transicion = La_Perruqueria_Reservas::verificar_transicion( $cita['estado'], 'reprogramada' );
			if ( is_wp_error( $transicion ) ) {
				$error = $transicion->get_error_message();
			} elseif ( '' !== $fecha ) {
				$resultado = La_Perruqueria_Horarios::calcular_disponibilidad( (int) $cita['servicio_id'], $fecha, $cita_id );
				if ( is_wp_error( $resultado ) ) {
					$error = $resultado->get_error_message();
				} else {
					$turnos = $resultado;
				}
			}
		}

		include PERRUQUERIA_PATH . 'templates/admin-reprogramar-cita.php';
	}

	/**
	 * Renderiza la pantalla de ajustes de horarios y disponibilidad.
	 */
	public static function render_pantalla_ajustes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso no autorizado.', 'la-perruqueria' ) );
		}

		include PERRUQUERIA_PATH . 'templates/admin-ajustes.php';
	}
}
