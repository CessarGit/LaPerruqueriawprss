<?php
/**
 * Reglas de negocio para reservas, matriz de estados y control de límites de citas.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Reservas {

	/**
	 * Constantes de estados de citas.
	 */
	const ESTADO_PENDIENTE    = 'pendiente';
	const ESTADO_CONFIRMADA   = 'confirmada';
	const ESTADO_REPROGRAMADA = 'reprogramada';
	const ESTADO_CANCELADA    = 'cancelada';

	/**
	 * Matriz de estados y transiciones permitidas.
	 *
	 * - Pendiente -> Confirmada, Cancelada.
	 * - Confirmada -> Reprogramada, Cancelada.
	 * - Reprogramada -> Confirmada, Cancelada.
	 * - Cancelada -> Estado final (sin transiciones permitidas).
	 */
	const TRANSICIONES = array(
		'pendiente'    => array( 'confirmada', 'cancelada' ),
		'confirmada'   => array( 'reprogramada', 'cancelada' ),
		'reprogramada' => array( 'confirmada', 'cancelada' ),
		'cancelada'    => array(),
	);

	/**
	 * Número máximo de citas activas futuras permitidas simultáneamente por cliente.
	 */
	const MAX_CITAS_FUTURAS = 3;

	/**
	 * Inicializa los ganchos y el shortcode de reservas.
	 */
	public static function init() {
		add_shortcode( 'perruqueria_reservar', array( __CLASS__, 'render_shortcode_reservar' ) );
	}

	/**
	 * Valida si una transición de estado es válida según la matriz de estados.
	 *
	 * @param string $estado_actual Estado de origen.
	 * @param string $nuevo_estado  Estado de destino.
	 * @return bool
	 */
	public static function validar_transicion( $estado_actual, $nuevo_estado ) {
		$estado_actual = sanitize_key( $estado_actual );
		$nuevo_estado  = sanitize_key( $nuevo_estado );

		if ( ! isset( self::TRANSICIONES[ $estado_actual ] ) ) {
			return false;
		}

		return in_array( $nuevo_estado, self::TRANSICIONES[ $estado_actual ], true );
	}

	/**
	 * Verifica una transición de estado retornando true o WP_Error con mensaje descriptivo.
	 *
	 * @param string $estado_actual Estado de origen.
	 * @param string $nuevo_estado  Estado de destino.
	 * @return true|WP_Error
	 */
	public static function verificar_transicion( $estado_actual, $nuevo_estado ) {
		$estado_actual = sanitize_key( $estado_actual );
		$nuevo_estado  = sanitize_key( $nuevo_estado );

		if ( ! self::validar_transicion( $estado_actual, $nuevo_estado ) ) {
			return new WP_Error(
				'transicion_invalida',
				sprintf(
					/* translators: 1: estado actual, 2: nuevo estado propuesto */
					__( 'Transición no permitida: no es posible cambiar una cita en estado "%1$s" a "%2$s".', 'la-perruqueria' ),
					$estado_actual,
					$nuevo_estado
				)
			);
		}

		return true;
	}

	/**
	 * Comprueba si un cliente ha alcanzado el límite máximo de citas activas futuras.
	 *
	 * @param int $cliente_id ID del cliente.
	 * @return bool True si ya alcanzó o superó el límite, false si puede reservar.
	 */
	public static function cliente_alcanzo_limite_citas( $cliente_id ) {
		$cliente_id = absint( $cliente_id );
		if ( $cliente_id <= 0 ) {
			return true;
		}

		$citas_activas = La_Perruqueria_Citas_DB::contar_citas_activas_futuras_cliente( $cliente_id );

		return $citas_activas >= self::MAX_CITAS_FUTURAS;
	}

	/**
	 * Valida que la cancelación de una cita cumpla con el plazo mínimo de anticipación configurado.
	 *
	 * @param string $inicio_cita Fecha y hora de inicio de la cita (YYYY-MM-DD HH:MM:SS).
	 * @return true|WP_Error
	 */
	public static function validar_plazo_cancelacion( $inicio_cita ) {
		$tz = wp_timezone();
		$dt_inicio = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $inicio_cita, $tz );

		if ( ! $dt_inicio ) {
			return new WP_Error( 'fecha_invalida', __( 'La fecha y hora de inicio de la cita no es válida.', 'la-perruqueria' ) );
		}

		$ahora   = new DateTimeImmutable( 'now', $tz );
		$ajustes = La_Perruqueria_Horarios::obtener_ajustes();
		$horas   = isset( $ajustes['anticipacion_cancelar'] ) ? absint( $ajustes['anticipacion_cancelar'] ) : 2;

		$limite_cancelacion = $ahora->modify( "+{$horas} hours" );

		if ( $dt_inicio < $limite_cancelacion ) {
			return new WP_Error(
				'plazo_cancelacion_vencido',
				sprintf(
					/* translators: %d: horas mínimas requeridas para cancelar */
					__( 'No es posible cancelar la cita con menos de %d hora(s) de anticipación al horario reservado.', 'la-perruqueria' ),
					$horas
				)
			);
		}

		return true;
	}

	/**
	 * Renderiza el shortcode [perruqueria_reservar].
	 *
	 * @return string HTML de la interfaz de reserva.
	 */
	public static function render_shortcode_reservar() {
		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="perruqueria-card perruqueria-auth-required">
				<h3><?php esc_html_e( 'Reserva de Citas', 'la-perruqueria' ); ?></h3>
				<p><?php esc_html_e( 'Debes iniciar sesión con tu cuenta de cliente para poder agendar una cita para tu mascota.', 'la-perruqueria' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		$cliente_id = La_Perruqueria_Seguridad::obtener_cliente_actual_id();

		// Comprobar rol cliente o administrador
		if ( ! current_user_can( 'cliente' ) && ! current_user_can( 'manage_options' ) ) {
			ob_start();
			?>
			<div class="perruqueria-card perruqueria-auth-required">
				<h3><?php esc_html_e( 'Acceso restringido', 'la-perruqueria' ); ?></h3>
				<p><?php esc_html_e( 'Esta sección está reservada exclusivamente para clientes de La Perruquería.', 'la-perruqueria' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		$mascotas       = La_Perruqueria_Mascotas::listar_mascotas( $cliente_id );
		$servicios      = La_Perruqueria_Servicios::listar_servicios_activos();
		$citas_activas  = La_Perruqueria_Citas_DB::contar_citas_activas_futuras_cliente( $cliente_id );
		$limite_max     = self::MAX_CITAS_FUTURAS;
		$limite_alcanzo = ( $citas_activas >= $limite_max );

		$tz  = wp_timezone();
		$hoy = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );

		ob_start();
		include PERRUQUERIA_PATH . 'templates/formulario-reserva.php';
		return ob_get_clean();
	}
}
