<?php
/**
 * Gestión de horarios, días laborables y cálculo de disponibilidad horaria.
 *
 * @package La_Perruqueria
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 
class La_Perruqueria_Horarios {
 
	/**
	 * Nombre de la opción de ajustes en la base de datos de WordPress.
	 */
	const OPTION_KEY = 'perruqueria_ajustes_horarios';
 
	/**
	 * Retorna los valores por defecto de la configuración de horarios.
	 *
	 * @return array
	 */
	public static function obtener_valores_defecto() {
		return array(
			'dias_laborales'        => array( 1, 2, 3, 4, 5, 6 ), // 1 = Lunes, 6 = Sábado
			'hora_apertura'         => '09:00',
			'hora_cierre'           => '18:00',
			'dias_no_laborables'    => array(), // Fechas YYYY-MM-DD
			'intervalo_min'         => 30,      // 15 o 30 minutos
			'anticipacion_reserva'  => 2,       // Horas mínimas para reservar
			'anticipacion_cancelar' => 2,       // Horas mínimas para cancelar
			'max_dias_reserva'      => 60,      // Máximo de días en el futuro permitidos para reservar
		);
	}
 
	/**
	 * Obtiene los ajustes actuales combinados con los valores por defecto.
	 *
	 * @return array
	 */
	public static function obtener_ajustes() {
		$ajustes = get_option( self::OPTION_KEY, array() );
		$defecto = self::obtener_valores_defecto();
 
		if ( ! is_array( $ajustes ) ) {
			$ajustes = array();
		}
 
		return wp_parse_args( $ajustes, $defecto );
	}
 
	/**
	 * Convierte un valor a entero estricto dentro de un rango.
	 * A diferencia de absint(), no convierte "-5" en 5 ni "abc" en 0.
	 *
	 * @param mixed $valor Valor recibido.
	 * @param int   $min   Mínimo permitido.
	 * @param int   $max   Máximo permitido.
	 * @return int|false Entero válido o false si no es válido.
	 */
	private static function entero_en_rango( $valor, $min, $max ) {
		if ( is_string( $valor ) ) {
			$valor = trim( $valor );
		}
 
		$numero = filter_var(
			$valor,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => $min,
					'max_range' => $max,
				),
			)
		);
 
		return false === $numero ? false : (int) $numero;
	}
 
	/**
	 * Valida y guarda los ajustes de horarios en la base de datos de WordPress.
	 * Si un valor no es válido se rechaza todo el formulario con un mensaje claro,
	 * sin guardar nada y sin corregir en silencio.
	 *
	 * @param array $datos Datos enviados desde la pantalla de administración.
	 * @return true|WP_Error
	 */
	public static function guardar_ajustes( array $datos ) {
		$actual = self::obtener_ajustes();
 
		$hora_apertura = isset( $datos['hora_apertura'] ) ? sanitize_text_field( $datos['hora_apertura'] ) : $actual['hora_apertura'];
		$hora_cierre   = isset( $datos['hora_cierre'] ) ? sanitize_text_field( $datos['hora_cierre'] ) : $actual['hora_cierre'];
 
		// 1. Validar formato de horas (HH:MM)
		if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $hora_apertura ) || ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $hora_cierre ) ) {
			return new WP_Error( 'formato_hora', __( 'El formato de hora de apertura o cierre no es válido. Usa HH:MM (24 horas).', 'la-perruqueria' ) );
		}
 
		// 2. Comprobar que la hora de apertura sea anterior a la de cierre
		if ( $hora_apertura >= $hora_cierre ) {
			return new WP_Error( 'rango_horario_invalido', __( 'La hora de apertura debe ser estrictamente anterior a la hora de cierre.', 'la-perruqueria' ) );
		}
 
		// 3. Validar días de la semana (1 = Lunes a 7 = Domingo); debe quedar al menos uno
		$dias_recibidos = isset( $datos['dias_laborales'] ) && is_array( $datos['dias_laborales'] ) ? array_map( 'absint', $datos['dias_laborales'] ) : array();
		$dias_validos   = array();
		foreach ( $dias_recibidos as $dia ) {
			if ( $dia >= 1 && $dia <= 7 ) {
				$dias_validos[] = $dia;
			}
		}
		$dias_validos = array_values( array_unique( $dias_validos ) );
		sort( $dias_validos );
 
		if ( empty( $dias_validos ) ) {
			return new WP_Error( 'sin_dias_laborales', __( 'Debes marcar al menos un día de atención.', 'la-perruqueria' ) );
		}
 
		// 4. Validar intervalo de turnos (15 o 30 minutos)
		$intervalo_min = isset( $datos['intervalo_min'] ) ? self::entero_en_rango( $datos['intervalo_min'], 15, 30 ) : (int) $actual['intervalo_min'];
		if ( false === $intervalo_min || ! in_array( $intervalo_min, array( 15, 30 ), true ) ) {
			return new WP_Error( 'intervalo_invalido', __( 'El intervalo entre turnos debe ser de 15 o 30 minutos.', 'la-perruqueria' ) );
		}
 
		// 5. Validar anticipaciones y horizonte de reserva (números enteros dentro de un rango)
		$anticipacion_reserva = isset( $datos['anticipacion_reserva'] ) ? self::entero_en_rango( $datos['anticipacion_reserva'], 0, 720 ) : (int) $actual['anticipacion_reserva'];
		if ( false === $anticipacion_reserva ) {
			return new WP_Error( 'anticipacion_reserva_invalida', __( 'La anticipación mínima para reservar debe ser un número entero de horas entre 0 y 720.', 'la-perruqueria' ) );
		}
 
		$anticipacion_cancelar = isset( $datos['anticipacion_cancelar'] ) ? self::entero_en_rango( $datos['anticipacion_cancelar'], 0, 720 ) : (int) $actual['anticipacion_cancelar'];
		if ( false === $anticipacion_cancelar ) {
			return new WP_Error( 'anticipacion_cancelar_invalida', __( 'La anticipación mínima para cancelar debe ser un número entero de horas entre 0 y 720.', 'la-perruqueria' ) );
		}
 
		$max_dias_reserva = isset( $datos['max_dias_reserva'] ) ? self::entero_en_rango( $datos['max_dias_reserva'], 1, 365 ) : (int) $actual['max_dias_reserva'];
		if ( false === $max_dias_reserva ) {
			return new WP_Error( 'max_dias_invalido', __( 'El máximo de días de antelación para reservar debe ser un número entero entre 1 y 365.', 'la-perruqueria' ) );
		}
 
		// 6. Procesar lista de fechas no laborables (YYYY-MM-DD); si alguna es inválida se rechaza todo
		$dias_no_lab_raw = isset( $datos['dias_no_laborables'] ) ? sanitize_textarea_field( $datos['dias_no_laborables'] ) : '';
		$tz              = wp_timezone();
		$fechas_no_lab   = array();
		$fechas_malas    = array();
		$lineas_fechas   = preg_split( '/[\r\n,]+/', $dias_no_lab_raw );
 
		foreach ( $lineas_fechas as $f_str ) {
			$f_clean = trim( sanitize_text_field( $f_str ) );
			if ( '' === $f_clean ) {
				continue;
			}
 
			$dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $f_clean, $tz );
			if ( $dt && $dt->format( 'Y-m-d' ) === $f_clean ) {
				$fechas_no_lab[] = $f_clean;
			} else {
				$fechas_malas[] = $f_clean;
			}
		}
 
		if ( ! empty( $fechas_malas ) ) {
			return new WP_Error(
				'fechas_no_laborables_invalidas',
				sprintf(
					/* translators: %s: lista de fechas inválidas */
					__( 'Estas fechas no laborables no son válidas (usa el formato AAAA-MM-DD): %s', 'la-perruqueria' ),
					implode( ', ', array_map( 'esc_html', array_slice( $fechas_malas, 0, 5 ) ) )
				)
			);
		}
 
		$fechas_no_lab = array_values( array_unique( $fechas_no_lab ) );
		sort( $fechas_no_lab );
 
		$ajustes_limpios = array(
			'dias_laborales'        => $dias_validos,
			'hora_apertura'         => $hora_apertura,
			'hora_cierre'           => $hora_cierre,
			'dias_no_laborables'    => $fechas_no_lab,
			'intervalo_min'         => $intervalo_min,
			'anticipacion_reserva'  => $anticipacion_reserva,
			'anticipacion_cancelar' => $anticipacion_cancelar,
			'max_dias_reserva'      => $max_dias_reserva,
		);
 
		update_option( self::OPTION_KEY, $ajustes_limpios );
		update_option( 'perruqueria_anticipacion_cancelacion', $anticipacion_cancelar );
 
		return true;
	}
 
	/**
	 * Determina si una fecha específica es laborable según los días de atención y fechas no laborables.
	 *
	 * @param string $fecha Cadena en formato YYYY-MM-DD.
	 * @return bool
	 */
	public static function es_dia_laborable( $fecha ) {
		$tz = wp_timezone();
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $fecha, $tz );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $fecha ) {
			return false;
		}
 
		$ajustes = self::obtener_ajustes();
 
		// Día de la semana en formato ISO-8601: 1 (Lunes) a 7 (Domingo)
		$dia_semana = (int) $dt->format( 'N' );
		if ( ! in_array( $dia_semana, $ajustes['dias_laborales'], true ) ) {
			return false;
		}
 
		// Fechas no laborables o festivas
		if ( in_array( $fecha, $ajustes['dias_no_laborables'], true ) ) {
			return false;
		}
 
		return true;
	}
 
	/**
	 * Calcula los horarios de inicio disponibles para un servicio y fecha específicos.
	 * Excluye días no laborables, horas pasadas, las que no cumplan la anticipación mínima
	 * y aquellas que se traslapen con citas activas.
	 *
	 * @param int    $servicio_id ID del servicio (debe estar activo).
	 * @param string $fecha       Fecha en formato YYYY-MM-DD.
	 * @param int    $excluir_cita_id ID de una cita que no debe contarse como ocupada (para reprogramarla). 0 = ninguna.
	 * @return array|WP_Error Lista de slots con formato array('hora' => 'HH:MM', 'inicio' => '...', 'fin' => '...').
	 */
	public static function calcular_disponibilidad( $servicio_id, $fecha, $excluir_cita_id = 0 ) {
		global $wpdb;
 
		$servicio_id = absint( $servicio_id );
		if ( $servicio_id <= 0 ) {
			return new WP_Error( 'servicio_invalido', __( 'Identificador de servicio no válido.', 'la-perruqueria' ) );
		}
 
		// 1. Obtener y verificar que el servicio exista y esté activo
		$servicio = La_Perruqueria_Servicios::obtener_servicio( $servicio_id );
		if ( ! $servicio || 1 !== (int) $servicio['activo'] ) {
			return new WP_Error( 'servicio_no_disponible', __( 'El servicio solicitado no existe o se encuentra inactivo.', 'la-perruqueria' ) );
		}
 
		// 2. Validar formato de fecha YYYY-MM-DD con wp_timezone()
		$tz = wp_timezone();
		$dt_fecha = DateTimeImmutable::createFromFormat( 'Y-m-d', $fecha, $tz );
		if ( ! $dt_fecha || $dt_fecha->format( 'Y-m-d' ) !== $fecha ) {
			return new WP_Error( 'fecha_invalida', __( 'La fecha no tiene un formato válido (YYYY-MM-DD).', 'la-perruqueria' ) );
		}
 
		// 2b. Validar que la fecha no supere el horizonte máximo de reserva configurado
		$ajustes          = self::obtener_ajustes();
		$max_dias_reserva = isset( $ajustes['max_dias_reserva'] ) ? absint( $ajustes['max_dias_reserva'] ) : 60;
		$ahora_ini        = new DateTimeImmutable( 'now', $tz );
		$fecha_limite     = $ahora_ini->modify( "+{$max_dias_reserva} days" )->setTime( 23, 59, 59 );
 
		if ( $dt_fecha > $fecha_limite ) {
			return new WP_Error(
				'fecha_fuera_horizonte',
				sprintf(
					/* translators: %d: número máximo de días hacia el futuro permitidos */
					__( 'Solo se permite reservar con un máximo de %d días de anticipación.', 'la-perruqueria' ),
					$max_dias_reserva
				)
			);
		}
 
		// 3. Comprobar si la fecha es día laborable
		if ( ! self::es_dia_laborable( $fecha ) ) {
			return array();
		}
 
		$duracion_min = absint( $servicio['duracion_min'] );
		if ( $duracion_min <= 0 ) {
			$duracion_min = 60;
		}
 
		// 4. Instanciar límites de apertura y cierre del día
		$dt_apertura = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $fecha . ' ' . $ajustes['hora_apertura'], $tz );
		$dt_cierre   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $fecha . ' ' . $ajustes['hora_cierre'], $tz );
 
		if ( ! $dt_apertura || ! $dt_cierre || $dt_apertura >= $dt_cierre ) {
			return array();
		}
 
		// 5. Límites temporales: no permitir horas pasadas ni aquellas que no cumplan la anticipación mínima
		$ahora                = new DateTimeImmutable( 'now', $tz );
		$horas_anticipacion   = absint( $ajustes['anticipacion_reserva'] );
		$limite_anticipacion  = $ahora->modify( "+{$horas_anticipacion} hours" );
 
		$intervalo_paso = absint( $ajustes['intervalo_min'] );
		if ( ! in_array( $intervalo_paso, array( 15, 30 ), true ) ) {
			$intervalo_paso = 30;
		}
 
		// 6. Generar turnos candidatos donde inicio + duracion <= hora_cierre
		$candidatos = array();
		$slot_actual = $dt_apertura;
 
		while ( true ) {
			$slot_fin = $slot_actual->modify( "+{$duracion_min} minutes" );
 
			// El servicio debe concluir a más tardar a la hora de cierre del establecimiento
			if ( $slot_fin > $dt_cierre ) {
				break;
			}
 
			// Excluir horas que ya pasaron o que no cumplan la anticipación mínima requerida
			if ( $slot_actual > $ahora && $slot_actual >= $limite_anticipacion ) {
				$candidatos[] = array(
					'hora'   => $slot_actual->format( 'H:i' ),
					'inicio' => $slot_actual->format( 'Y-m-d H:i:s' ),
					'fin'    => $slot_fin->format( 'Y-m-d H:i:s' ),
				);
			}
 
			$slot_actual = $slot_actual->modify( "+{$intervalo_paso} minutes" );
		}
 
		if ( empty( $candidatos ) ) {
			return array();
		}
 
		// 7. Consultar citas activas del día en wp_perruqueria_citas
		// Estados activos: pendiente, confirmada, reprogramada (las canceladas no bloquean)
		$tabla_citas = $wpdb->prefix . 'perruqueria_citas';
		$dia_inicio  = $fecha . ' 00:00:00';
		$dia_fin     = $fecha . ' 23:59:59';
 
		$sql_citas = $wpdb->prepare(
			"SELECT inicio, fin
			 FROM {$tabla_citas}
			 WHERE estado IN ('pendiente', 'confirmada', 'reprogramada')
			   AND inicio < %s AND fin > %s
			   AND id <> %d
			 ORDER BY inicio ASC",
			$dia_fin,
			$dia_inicio,
			absint( $excluir_cita_id )
		);
 
		$citas_ocupadas = $wpdb->get_results( $sql_citas, ARRAY_A );
 
		// 8. Filtrar colisiones (se atiende una sola cita a la vez en todo el negocio)
		// Condición de traslape: slot.inicio < cita.fin AND slot.fin > cita.inicio
		$disponibles = array();
 
		foreach ( $candidatos as $slot ) {
			$colision = false;
 
			foreach ( $citas_ocupadas as $cita ) {
				if ( $slot['inicio'] < $cita['fin'] && $slot['fin'] > $cita['inicio'] ) {
					$colision = true;
					break;
				}
			}
 
			if ( ! $colision ) {
				$disponibles[] = $slot;
			}
		}
 
		return $disponibles;
	}
}
 