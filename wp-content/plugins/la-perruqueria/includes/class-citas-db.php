<?php
/**
 * Capa de acceso a datos para la tabla wp_perruqueria_citas con soporte para reservas atómicas.
 *
 * @package La_Perruqueria
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 
class La_Perruqueria_Citas_DB {
 
	/**
	 * Retorna el nombre de la tabla de citas con prefijo.
	 *
	 * @return string
	 */
	public static function nombre_tabla() {
		global $wpdb;
		return $wpdb->prefix . 'perruqueria_citas';
	}
 
	/**
	 * Construye el nombre de un candado de MariaDB.
	 *
	 * Los candados de GET_LOCK son globales en todo el servidor, no por base de datos,
	 * por eso el nombre incluye una huella de la base de datos y del prefijo de tablas.
	 * El nombre resultante mide unos 50 caracteres (el límite de MariaDB es 64).
	 *
	 * @param string     $tipo  'cli' (candado por cliente) o 'dia' (candado por fecha).
	 * @param string|int $valor ID del cliente o fecha YYYY-MM-DD.
	 * @return string
	 */
	private static function nombre_candado( $tipo, $valor ) {
		global $wpdb;
		$huella = substr( md5( DB_NAME . '|' . $wpdb->prefix ), 0, 16 );
		return 'perr_' . $huella . '_' . $tipo . '_' . $valor;
	}
 
	/**
	 * Intenta adquirir un candado de MariaDB.
	 *
	 * @param string $nombre Nombre del candado.
	 * @param int    $espera Segundos máximos de espera.
	 * @return bool
	 */
	private static function adquirir_candado( $nombre, $espera = 5 ) {
		global $wpdb;
		$resultado = $wpdb->get_var(
			$wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $nombre, absint( $espera ) )
		);
		return '1' === (string) $resultado;
	}
 
	/**
	 * Libera un candado de MariaDB.
	 *
	 * @param string $nombre Nombre del candado.
	 */
	private static function liberar_candado( $nombre ) {
		global $wpdb;
		$wpdb->get_var(
			$wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $nombre )
		);
	}
 
	/**
	 * Crea una cita de forma atómica previniendo condiciones de carrera.
	 *
	 * Adquiere dos candados en un orden fijo (primero el del cliente, luego el de la fecha):
	 * - El candado del cliente impide que dos peticiones simultáneas del mismo cliente
	 *   superen el límite de citas activas.
	 * - El candado de la fecha impide que dos clientes reserven el mismo horario.
	 * Como siempre se adquieren en el mismo orden, no pueden producirse interbloqueos.
	 * Ambos se liberan en el bloque finally.
	 *
	 * @param array $datos Datos de la reserva: cliente_id, mascota_id, servicio_id, inicio, notas (opcional).
	 * @return int|WP_Error ID de la cita creada o WP_Error en caso de fallo.
	 */
	public static function crear_cita_atomica( array $datos ) {
		global $wpdb;
 
		$cliente_id  = isset( $datos['cliente_id'] ) ? absint( $datos['cliente_id'] ) : 0;
		$mascota_id  = isset( $datos['mascota_id'] ) ? absint( $datos['mascota_id'] ) : 0;
		$servicio_id = isset( $datos['servicio_id'] ) ? absint( $datos['servicio_id'] ) : 0;
		$inicio      = isset( $datos['inicio'] ) ? sanitize_text_field( $datos['inicio'] ) : '';
 
		if ( $cliente_id <= 0 || $mascota_id <= 0 || $servicio_id <= 0 ) {
			return new WP_Error( 'datos_invalidos', __( 'Los datos de la reserva están incompletos.', 'la-perruqueria' ) );
		}
 
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $inicio ) ) {
			return new WP_Error( 'inicio_invalido', __( 'Fecha y hora de inicio no válidas.', 'la-perruqueria' ) );
		}
 
		$fecha = substr( $inicio, 0, 10 );
 
		// Orden fijo de adquisición: cliente y después fecha.
		$candados   = array(
			self::nombre_candado( 'cli', $cliente_id ),
			self::nombre_candado( 'dia', $fecha ),
		);
		$adquiridos = array();
 
		try {
			foreach ( $candados as $candado ) {
				if ( ! self::adquirir_candado( $candado, 5 ) ) {
					return new WP_Error(
						'bloqueo_ocupado',
						__( 'El sistema se encuentra procesando otra reserva en este momento. Por favor, reintenta en unos instantes.', 'la-perruqueria' )
					);
				}
				$adquiridos[] = $candado;
			}
 
			// 1. Comprobar de nuevo, dentro del candado, que la mascota sigue siendo del cliente y está activa
			if ( ! La_Perruqueria_Seguridad::cliente_es_dueno_mascota( $mascota_id, $cliente_id ) ) {
				return new WP_Error(
					'mascota_invalida',
					__( 'La mascota seleccionada no es válida o no pertenece a tu cuenta.', 'la-perruqueria' )
				);
			}
 
			// 2. Comprobación definitiva del límite de citas activas futuras (dentro del candado del cliente)
			if ( La_Perruqueria_Reservas::cliente_alcanzo_limite_citas( $cliente_id ) ) {
				return new WP_Error(
					'limite_citas_alcanzado',
					__( 'Has alcanzado el límite máximo permitido de 3 citas activas futuras simultáneas.', 'la-perruqueria' )
				);
			}
 
			// 3. Revalidar disponibilidad dentro del candado (incluye el horizonte máximo de reserva)
			$turnos_disponibles = La_Perruqueria_Horarios::calcular_disponibilidad( $servicio_id, $fecha );
 
			if ( is_wp_error( $turnos_disponibles ) ) {
				return $turnos_disponibles;
			}
 
			// 4. Buscar si el inicio solicitado sigue libre
			$slot_coincidente = null;
			foreach ( $turnos_disponibles as $slot ) {
				if ( $slot['inicio'] === $inicio ) {
					$slot_coincidente = $slot;
					break;
				}
			}
 
			if ( ! $slot_coincidente ) {
				return new WP_Error(
					'horario_no_disponible',
					__( 'El horario seleccionado ya no se encuentra disponible. Por favor, selecciona otro turno.', 'la-perruqueria' )
				);
			}
 
			$fin   = $slot_coincidente['fin'];
			$tz    = wp_timezone();
			$ahora = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );
			$tabla = self::nombre_tabla();
 
			// 5. Insertar la cita en la base de datos
			$insertado = $wpdb->insert(
				$tabla,
				array(
					'cliente_id'  => $cliente_id,
					'mascota_id'  => $mascota_id,
					'servicio_id' => $servicio_id,
					'inicio'      => $inicio,
					'fin'         => $fin,
					'estado'      => 'pendiente',
					'notas'       => isset( $datos['notas'] ) && '' !== trim( $datos['notas'] ) ? sanitize_textarea_field( $datos['notas'] ) : null,
					'created_at'  => $ahora,
					'updated_at'  => $ahora,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
 
			if ( false === $insertado ) {
				// El detalle técnico queda en el registro de errores, no se muestra al cliente.
				error_log( 'La Perruqueria: fallo al insertar cita: ' . $wpdb->last_error );
				return new WP_Error(
					'db_error',
					__( 'Error al registrar la cita en la base de datos.', 'la-perruqueria' )
				);
			}
 
			return (int) $wpdb->insert_id;
 
		} finally {
			// Los candados siempre se liberan, en orden inverso al de adquisición.
			foreach ( array_reverse( $adquiridos ) as $candado ) {
				self::liberar_candado( $candado );
			}
		}
	}
 
	/**
	 * Reprograma una cita de forma atómica (uso del personal).
	 *
	 * Solo se permite si la matriz de estados lo autoriza (confirmada -> reprogramada).
	 * Adquiere el candado de la fecha de destino, revalida la disponibilidad ignorando la propia cita
	 * (para poder moverla dentro del mismo día) y actualiza inicio, fin y estado en una sola consulta
	 * que solo se aplica si la cita sigue en el estado y horario que se validaron.
	 *
	 * @param int    $cita_id      ID de la cita.
	 * @param string $nuevo_inicio Nuevo inicio, formato YYYY-MM-DD HH:MM:SS.
	 * @return true|WP_Error
	 */
	public static function reprogramar_cita_atomica( $cita_id, $nuevo_inicio ) {
		global $wpdb;

		$cita_id      = absint( $cita_id );
		$nuevo_inicio = sanitize_text_field( $nuevo_inicio );

		if ( $cita_id <= 0 ) {
			return new WP_Error( 'cita_invalida', __( 'Identificador de cita no válido.', 'la-perruqueria' ) );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $nuevo_inicio ) ) {
			return new WP_Error( 'inicio_invalido', __( 'Fecha y hora de inicio no válidas.', 'la-perruqueria' ) );
		}

		$cita = self::obtener_cita( $cita_id );
		if ( ! $cita ) {
			return new WP_Error( 'cita_no_encontrada', __( 'Cita no encontrada.', 'la-perruqueria' ) );
		}

		$transicion = La_Perruqueria_Reservas::verificar_transicion( $cita['estado'], 'reprogramada' );
		if ( is_wp_error( $transicion ) ) {
			return $transicion;
		}

		if ( $cita['inicio'] === $nuevo_inicio ) {
			return new WP_Error( 'sin_cambios', __( 'El nuevo horario es igual al actual. Elige otro turno.', 'la-perruqueria' ) );
		}

		$fecha   = substr( $nuevo_inicio, 0, 10 );
		$candado = self::nombre_candado( 'dia', $fecha );

		if ( ! self::adquirir_candado( $candado, 5 ) ) {
			return new WP_Error(
				'bloqueo_ocupado',
				__( 'El sistema se encuentra procesando otra reserva en este momento. Por favor, reintenta en unos instantes.', 'la-perruqueria' )
			);
		}

		try {
			// Revalidar disponibilidad dentro del candado, sin contar la propia cita como ocupada.
			$turnos = La_Perruqueria_Horarios::calcular_disponibilidad( (int) $cita['servicio_id'], $fecha, $cita_id );
			if ( is_wp_error( $turnos ) ) {
				return $turnos;
			}

			$slot_coincidente = null;
			foreach ( $turnos as $slot ) {
				if ( $slot['inicio'] === $nuevo_inicio ) {
					$slot_coincidente = $slot;
					break;
				}
			}

			if ( ! $slot_coincidente ) {
				return new WP_Error(
					'horario_no_disponible',
					__( 'El horario seleccionado ya no se encuentra disponible. Por favor, selecciona otro turno.', 'la-perruqueria' )
				);
			}

			$tabla = self::nombre_tabla();
			$ahora = ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' );

			// Solo se aplica si la cita sigue con el mismo estado y horario que se validaron.
			$filas = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$tabla}
					 SET inicio = %s, fin = %s, estado = 'reprogramada', updated_at = %s
					 WHERE id = %d AND estado = %s AND inicio = %s",
					$slot_coincidente['inicio'],
					$slot_coincidente['fin'],
					$ahora,
					$cita_id,
					$cita['estado'],
					$cita['inicio']
				)
			);

			if ( false === $filas ) {
				error_log( 'La Perruqueria: fallo al reprogramar cita: ' . $wpdb->last_error );
				return new WP_Error(
					'db_error',
					__( 'Error al reprogramar la cita en la base de datos.', 'la-perruqueria' )
				);
			}

			if ( 0 === (int) $filas ) {
				return new WP_Error(
					'estado_modificado',
					__( 'La cita cambió mientras se procesaba la solicitud. Actualiza la página e inténtalo de nuevo.', 'la-perruqueria' )
				);
			}

			return true;

		} finally {
			self::liberar_candado( $candado );
		}
	}

	/**
	 * Obtiene los datos de una cita por su ID.
	 *
	 * @param int $cita_id ID de la cita.
	 * @return array|null
	 */
	public static function obtener_cita( $cita_id ) {
		global $wpdb;
 
		$cita_id = absint( $cita_id );
		if ( $cita_id <= 0 ) {
			return null;
		}
 
		$tabla = self::nombre_tabla();
		$sql   = $wpdb->prepare( "SELECT * FROM {$tabla} WHERE id = %d", $cita_id );
 
		return $wpdb->get_row( $sql, ARRAY_A );
	}
 
	/**
	 * Obtiene una cita verificando que pertenezca a un cliente específico.
	 *
	 * @param int $cita_id    ID de la cita.
	 * @param int $cliente_id ID del cliente.
	 * @return array|null
	 */
	public static function obtener_cita_cliente( $cita_id, $cliente_id ) {
		global $wpdb;
 
		$cita_id    = absint( $cita_id );
		$cliente_id = absint( $cliente_id );
 
		if ( $cita_id <= 0 || $cliente_id <= 0 ) {
			return null;
		}
 
		$tabla = self::nombre_tabla();
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$tabla} WHERE id = %d AND cliente_id = %d",
			$cita_id,
			$cliente_id
		);
 
		return $wpdb->get_row( $sql, ARRAY_A );
	}
 
	/**
	 * Cuenta cuántas citas activas futuras (pendiente, confirmada, reprogramada) tiene un cliente.
	 *
	 * @param int $cliente_id ID del cliente.
	 * @return int Cantidad de citas activas futuras.
	 */
	public static function contar_citas_activas_futuras_cliente( $cliente_id ) {
		global $wpdb;
 
		$cliente_id = absint( $cliente_id );
		if ( $cliente_id <= 0 ) {
			return 0;
		}
 
		$tabla = self::nombre_tabla();
		$tz    = wp_timezone();
		$ahora = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );
 
		$sql = $wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$tabla}
			 WHERE cliente_id = %d
			   AND estado IN ('pendiente', 'confirmada', 'reprogramada')
			   AND inicio > %s",
			$cliente_id,
			$ahora
		);
 
		return (int) $wpdb->get_var( $sql );
	}
 
	/**
	 * Actualiza el estado de una cita en la base de datos.
	 *
	 * Si se indica $estado_esperado, el cambio solo se aplica cuando la cita todavía tiene ese
	 * estado (comprobación y actualización en una sola consulta). Así, dos cambios simultáneos
	 * sobre la misma cita no se pisan entre sí.
	 *
	 * @param int         $cita_id         ID de la cita.
	 * @param string      $nuevo_estado    Nuevo estado ('pendiente', 'confirmada', 'reprogramada', 'cancelada').
	 * @param string|null $estado_esperado Estado que la cita debe tener en este momento (opcional).
	 * @return true|WP_Error
	 */
	public static function actualizar_estado_cita( $cita_id, $nuevo_estado, $estado_esperado = null ) {
		global $wpdb;
 
		$cita_id      = absint( $cita_id );
		$nuevo_estado = sanitize_key( $nuevo_estado );
		$estados      = array( 'pendiente', 'confirmada', 'reprogramada', 'cancelada' );
 
		if ( $cita_id <= 0 ) {
			return new WP_Error( 'cita_invalida', __( 'Identificador de cita no válido.', 'la-perruqueria' ) );
		}
 
		if ( ! in_array( $nuevo_estado, $estados, true ) ) {
			return new WP_Error( 'estado_invalido', __( 'El estado indicado no es válido.', 'la-perruqueria' ) );
		}
 
		$tabla = self::nombre_tabla();
		$tz    = wp_timezone();
		$ahora = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );
 
		if ( null !== $estado_esperado ) {
			$estado_esperado = sanitize_key( $estado_esperado );
 
			$filas = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$tabla} SET estado = %s, updated_at = %s WHERE id = %d AND estado = %s",
					$nuevo_estado,
					$ahora,
					$cita_id,
					$estado_esperado
				)
			);
 
			if ( false === $filas ) {
				error_log( 'La Perruqueria: fallo al actualizar estado: ' . $wpdb->last_error );
				return new WP_Error(
					'db_error',
					__( 'Error al actualizar el estado de la cita en la base de datos.', 'la-perruqueria' )
				);
			}
 
			if ( 0 === (int) $filas ) {
				return new WP_Error(
					'estado_modificado',
					__( 'La cita cambió de estado mientras se procesaba tu solicitud. Actualiza la página e inténtalo de nuevo.', 'la-perruqueria' )
				);
			}
 
			return true;
		}
 
		$actualizado = $wpdb->update(
			$tabla,
			array(
				'estado'     => $nuevo_estado,
				'updated_at' => $ahora,
			),
			array( 'id' => $cita_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
 
		if ( false === $actualizado ) {
			error_log( 'La Perruqueria: fallo al actualizar estado: ' . $wpdb->last_error );
			return new WP_Error(
				'db_error',
				__( 'Error al actualizar el estado de la cita en la base de datos.', 'la-perruqueria' )
			);
		}
 
		return true;
	}
 
	/**
	 * Obtiene los detalles completos de una cita uniendo mascotas y servicios.
	 *
	 * @param int $cita_id ID de la cita.
	 * @return array|null
	 */
	public static function obtener_cita_con_detalles( $cita_id ) {
		global $wpdb;
 
		$cita_id = absint( $cita_id );
		if ( $cita_id <= 0 ) {
			return null;
		}
 
		$t_citas     = self::nombre_tabla();
		$t_mascotas  = $wpdb->prefix . 'perruqueria_mascotas';
		$t_servicios = $wpdb->prefix . 'perruqueria_servicios';
 
		$sql = $wpdb->prepare(
			"SELECT c.*,
			        m.nombre AS mascota_nombre, m.especie AS mascota_especie, m.raza AS mascota_raza,
			        s.nombre AS servicio_nombre, s.duracion_min AS servicio_duracion, s.precio AS servicio_precio
			 FROM {$t_citas} c
			 INNER JOIN {$t_mascotas} m ON c.mascota_id = m.id
			 INNER JOIN {$t_servicios} s ON c.servicio_id = s.id
			 WHERE c.id = %d",
			$cita_id
		);
 
		return $wpdb->get_row( $sql, ARRAY_A );
	}
}
 