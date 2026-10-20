<?php
/**
 * Registro de rutas y controladores de la API REST de La Perruquería.
 *
 * @package La_Perruqueria
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 
class La_Perruqueria_REST_API {
 
	/**
	 * Espacio de nombres para las rutas REST del plugin.
	 */
	const NAMESPACE = 'perruqueria/v1';
 
	/**
	 * Registra las rutas de la REST API.
	 */
	public static function registrar_rutas() {
		// 1. GET /perruqueria/v1/disponibilidad (Público)
		register_rest_route(
			self::NAMESPACE,
			'/disponibilidad',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'obtener_disponibilidad' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'servicio_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && absint( $param ) > 0;
						},
					),
					'fecha'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $param ) {
							$tz = wp_timezone();
							$dt = DateTimeImmutable::createFromFormat( 'Y-m-d', sanitize_text_field( $param ), $tz );
							return (bool) ( $dt && $dt->format( 'Y-m-d' ) === $param );
						},
					),
				),
			)
		);
 
		// 2. POST /perruqueria/v1/citas (Creación atómica de citas)
		register_rest_route(
			self::NAMESPACE,
			'/citas',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'crear_cita' ),
				'permission_callback' => array( __CLASS__, 'permiso_cliente' ),
				'args'                => array(
					'mascota_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && absint( $param ) > 0;
						},
					),
					'servicio_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && absint( $param ) > 0;
						},
					),
					'inicio'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $param ) {
							$tz = wp_timezone();
							$val = sanitize_text_field( $param );
							$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $val, $tz );
							if ( $dt && $dt->format( 'Y-m-d H:i:s' ) === $val ) {
								return true;
							}
							$dt_corta = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $val, $tz );
							return (bool) ( $dt_corta && $dt_corta->format( 'Y-m-d H:i' ) === $val );
						},
					),
					'notas'       => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
 
		// 3. POST /perruqueria/v1/citas/{id}/cancelar (Cancelación con validaciones de propiedad y tiempo)
		register_rest_route(
			self::NAMESPACE,
			'/citas/(?P<id>\d+)/cancelar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'cancelar_cita' ),
				'permission_callback' => array( __CLASS__, 'permiso_cliente' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && absint( $param ) > 0;
						},
					),
				),
			)
		);
	}
 
	/**
	 * Callback de permisos: exige sesión activa y rol Cliente.
	 * Los administradores y el personal gestionan las citas desde el panel de wp-admin,
	 * no desde estos endpoints.
	 *
	 * @return true|WP_Error
	 */
	public static function permiso_cliente() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'no_autenticado',
				__( 'Debes iniciar sesión para realizar esta operación.', 'la-perruqueria' ),
				array( 'status' => 401 )
			);
		}
 
		$usuario = wp_get_current_user();
		if ( ! in_array( 'cliente', (array) $usuario->roles, true ) ) {
			return new WP_Error(
				'acceso_denegado',
				__( 'Acceso restringido: se requiere el rol de Cliente.', 'la-perruqueria' ),
				array( 'status' => 403 )
			);
		}
 
		return true;
	}
 
	/**
	 * Traduce un código de error interno al código HTTP que corresponde.
	 *
	 * @param string $codigo Código del WP_Error.
	 * @return int
	 */
	private static function estado_http( $codigo ) {
		$mapa = array(
			'horario_no_disponible' => 409,
			'estado_modificado'     => 409,
			'bloqueo_ocupado'       => 503,
			'db_error'              => 500,
		);
 
		return isset( $mapa[ $codigo ] ) ? $mapa[ $codigo ] : 400;
	}
 
	/**
	 * Controlador para consultar turnos disponibles.
	 *
	 * @param WP_REST_Request $request Petición REST recibida.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function obtener_disponibilidad( WP_REST_Request $request ) {
		$servicio_id = absint( $request->get_param( 'servicio_id' ) );
		$fecha       = sanitize_text_field( $request->get_param( 'fecha' ) );
 
		// Comprobar que el servicio exista y esté activo
		$servicio = La_Perruqueria_Servicios::obtener_servicio( $servicio_id );
		if ( ! $servicio || 1 !== (int) $servicio['activo'] ) {
			return new WP_Error(
				'servicio_no_disponible',
				__( 'El servicio solicitado no existe o se encuentra inactivo.', 'la-perruqueria' ),
				array( 'status' => 404 )
			);
		}
 
		$resultado = La_Perruqueria_Horarios::calcular_disponibilidad( $servicio_id, $fecha );
 
		if ( is_wp_error( $resultado ) ) {
			return new WP_Error(
				$resultado->get_error_code(),
				$resultado->get_error_message(),
				array( 'status' => 400 )
			);
		}
 
		$respuesta = array(
			'fecha'        => $fecha,
			'servicio_id'  => $servicio_id,
			'servicio'     => $servicio['nombre'],
			'duracion_min' => (int) $servicio['duracion_min'],
			'turnos'       => $resultado,
		);
 
		return rest_ensure_response( $respuesta );
	}
 
	/**
	 * Controlador para crear una cita atómicamente.
	 *
	 * @param WP_REST_Request $request Petición REST recibida.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function crear_cita( WP_REST_Request $request ) {
		// El cliente_id se obtiene estrictamente de la sesión del servidor, nunca de la petición.
		$cliente_id  = (int) get_current_user_id();
		$mascota_id  = absint( $request->get_param( 'mascota_id' ) );
		$servicio_id = absint( $request->get_param( 'servicio_id' ) );
		$inicio_raw  = sanitize_text_field( $request->get_param( 'inicio' ) );
		$notas       = sanitize_textarea_field( (string) $request->get_param( 'notas' ) );
		$notas       = function_exists( 'mb_substr' ) ? mb_substr( $notas, 0, 500 ) : substr( $notas, 0, 500 );
 
		if ( $cliente_id <= 0 ) {
			return new WP_Error(
				'no_autenticado',
				__( 'Debes iniciar sesión para reservar.', 'la-perruqueria' ),
				array( 'status' => 401 )
			);
		}
 
		// 1. Verificar que la mascota pertenezca al cliente actual y esté activa.
		// Una mascota ajena y una inexistente producen exactamente la misma respuesta.
		if ( ! La_Perruqueria_Seguridad::cliente_es_dueno_mascota( $mascota_id, $cliente_id ) ) {
			return new WP_Error(
				'mascota_invalida',
				__( 'La mascota seleccionada no es válida o no pertenece a tu cuenta.', 'la-perruqueria' ),
				array( 'status' => 403 )
			);
		}
 
		// 2. Verificar que el servicio exista y se encuentre activo
		$servicio = La_Perruqueria_Servicios::obtener_servicio( $servicio_id );
		if ( ! $servicio || 1 !== (int) $servicio['activo'] ) {
			return new WP_Error(
				'servicio_no_disponible',
				__( 'El servicio seleccionado no existe o no se encuentra activo.', 'la-perruqueria' ),
				array( 'status' => 400 )
			);
		}
 
		// 3. Comprobación rápida del límite de citas activas (la definitiva se hace dentro del candado)
		if ( La_Perruqueria_Reservas::cliente_alcanzo_limite_citas( $cliente_id ) ) {
			return new WP_Error(
				'limite_citas_alcanzado',
				__( 'Has alcanzado el límite máximo permitido de 3 citas activas futuras simultáneas.', 'la-perruqueria' ),
				array( 'status' => 400 )
			);
		}
 
		// 4. Normalizar formato de inicio a YYYY-MM-DD HH:MM:SS
		$tz = wp_timezone();
		if ( 16 === strlen( $inicio_raw ) ) {
			$inicio_raw .= ':00';
		}
 
		$dt_inicio = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $inicio_raw, $tz );
		if ( ! $dt_inicio || $dt_inicio->format( 'Y-m-d H:i:s' ) !== $inicio_raw ) {
			return new WP_Error(
				'formato_fecha_invalido',
				__( 'El formato de fecha y hora es inválido.', 'la-perruqueria' ),
				array( 'status' => 400 )
			);
		}
 
		$datos_reserva = array(
			'cliente_id'  => $cliente_id,
			'mascota_id'  => $mascota_id,
			'servicio_id' => $servicio_id,
			'inicio'      => $inicio_raw,
			'notas'       => $notas,
		);
 
		// 5. Inserción atómica con candados por cliente y por fecha, y revalidación completa.
		// Esta función también aplica el horizonte máximo de reserva (vía calcular_disponibilidad).
		$resultado = La_Perruqueria_Citas_DB::crear_cita_atomica( $datos_reserva );
 
		if ( is_wp_error( $resultado ) ) {
			return new WP_Error(
				$resultado->get_error_code(),
				$resultado->get_error_message(),
				array( 'status' => self::estado_http( $resultado->get_error_code() ) )
			);
		}
 
		return new WP_REST_Response(
			array(
				'success' => true,
				'cita_id' => $resultado,
				'mensaje' => __( '¡Tu cita ha sido reservada con éxito!', 'la-perruqueria' ),
			),
			201
		);
	}
 
	/**
	 * Controlador para cancelar una cita existente.
	 *
	 * @param WP_REST_Request $request Petición REST recibida.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function cancelar_cita( WP_REST_Request $request ) {
		$cita_id    = absint( $request->get_param( 'id' ) );
		$cliente_id = (int) get_current_user_id();
 
		if ( $cliente_id <= 0 ) {
			return new WP_Error(
				'no_autenticado',
				__( 'Debes iniciar sesión para cancelar una cita.', 'la-perruqueria' ),
				array( 'status' => 401 )
			);
		}
 
		// 1. Obtener la cita
		$cita = La_Perruqueria_Citas_DB::obtener_cita( $cita_id );
 
		// 2. Si no existe o pertenece a otro cliente, responder 404 como si no existiera (prevención de IDOR)
		if ( ! $cita || (int) $cita['cliente_id'] !== $cliente_id ) {
			return new WP_Error(
				'cita_no_encontrada',
				__( 'Cita no encontrada.', 'la-perruqueria' ),
				array( 'status' => 404 )
			);
		}
 
		// 3. Validar la transición a cancelada mediante la matriz de estados
		$val_transicion = La_Perruqueria_Reservas::verificar_transicion( $cita['estado'], 'cancelada' );
		if ( is_wp_error( $val_transicion ) ) {
			return new WP_Error(
				$val_transicion->get_error_code(),
				$val_transicion->get_error_message(),
				array( 'status' => 400 )
			);
		}
 
		// 4. Validar el plazo de anticipación para cancelar (según ajustes del negocio)
		$val_plazo = La_Perruqueria_Reservas::validar_plazo_cancelacion( $cita['inicio'] );
		if ( is_wp_error( $val_plazo ) ) {
			return new WP_Error(
				$val_plazo->get_error_code(),
				$val_plazo->get_error_message(),
				array( 'status' => 400 )
			);
		}
 
		// 5. Actualizar el estado a cancelada, solo si la cita sigue en el estado que se validó
		$resultado = La_Perruqueria_Citas_DB::actualizar_estado_cita( $cita_id, 'cancelada', $cita['estado'] );
		if ( is_wp_error( $resultado ) ) {
			return new WP_Error(
				$resultado->get_error_code(),
				$resultado->get_error_message(),
				array( 'status' => self::estado_http( $resultado->get_error_code() ) )
			);
		}
 
		return rest_ensure_response(
			array(
				'success' => true,
				'cita_id' => $cita_id,
				'estado'  => 'cancelada',
				'mensaje' => __( 'La cita ha sido cancelada satisfactoriamente.', 'la-perruqueria' ),
			)
		);
	}
}
 