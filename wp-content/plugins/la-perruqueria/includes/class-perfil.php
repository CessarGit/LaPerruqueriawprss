<?php
/**
 * Perfil del cliente: datos de contacto (nombre, correo, teléfono) e historial de citas.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Perfil {

	/**
	 * Clave del metadato de usuario donde se guarda el teléfono.
	 */
	const META_TELEFONO = 'perruqueria_telefono';

	/**
	 * Citas mostradas por página en el historial.
	 */
	const CITAS_POR_PAGINA = 10;

	/**
	 * Inicializa el procesamiento del formulario, el shortcode y los recursos.
	 * Prioridad 5 en init para poder redirigir antes de cualquier salida HTML.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'procesar_formulario' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'encolar_assets_si_corresponde' ) );
		add_shortcode( 'perruqueria_perfil', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Encola CSS y JS del perfil solo en páginas que contengan el shortcode.
	 */
	public static function encolar_assets_si_corresponde() {
		global $post;
		if ( is_singular() && $post instanceof WP_Post && has_shortcode( $post->post_content, 'perruqueria_perfil' ) ) {
			self::encolar_assets();
		}
	}

	/**
	 * Encola CSS y JS del perfil (es seguro llamarlo más de una vez).
	 */
	private static function encolar_assets() {
		wp_enqueue_style(
			'perruqueria-perfil-css',
			PERRUQUERIA_URL . 'assets/css/perfil.css',
			array( 'perruqueria-frontend-css' ),
			PERRUQUERIA_VERSION
		);

		wp_enqueue_script(
			'perruqueria-perfil-js',
			PERRUQUERIA_URL . 'assets/js/perfil.js',
			array( 'perruqueria-frontend-js' ),
			PERRUQUERIA_VERSION,
			true
		);
	}

	/**
	 * Indica si el usuario actual tiene el rol Cliente.
	 *
	 * @return bool
	 */
	private static function usuario_es_cliente() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$usuario = wp_get_current_user();
		return in_array( 'cliente', (array) $usuario->roles, true );
	}

	/**
	 * Guarda avisos temporales (un minuto) para mostrarlos tras la redirección.
	 *
	 * @param int   $user_id ID del usuario.
	 * @param array $errores Mensajes de error.
	 * @param array $exitos  Mensajes de éxito.
	 */
	private static function guardar_avisos( $user_id, array $errores, array $exitos ) {
		set_transient(
			'perruqueria_perfil_avisos_' . absint( $user_id ),
			array(
				'error' => $errores,
				'exito' => $exitos,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Lee y elimina los avisos temporales del usuario.
	 *
	 * @param int $user_id ID del usuario.
	 * @return array
	 */
	public static function obtener_avisos( $user_id ) {
		$clave  = 'perruqueria_perfil_avisos_' . absint( $user_id );
		$avisos = get_transient( $clave );
		delete_transient( $clave );

		if ( ! is_array( $avisos ) ) {
			$avisos = array();
		}

		return wp_parse_args(
			$avisos,
			array(
				'error' => array(),
				'exito' => array(),
			)
		);
	}

	/**
	 * Obtiene el teléfono guardado del cliente.
	 *
	 * @param int $user_id ID del usuario.
	 * @return string
	 */
	public static function obtener_telefono( $user_id ) {
		return (string) get_user_meta( absint( $user_id ), self::META_TELEFONO, true );
	}

	/**
	 * Cuenta todas las citas de un cliente (cualquier estado).
	 *
	 * @param int $cliente_id ID del cliente.
	 * @return int
	 */
	public static function contar_citas_cliente( $cliente_id ) {
		global $wpdb;

		$cliente_id = absint( $cliente_id );
		if ( $cliente_id <= 0 ) {
			return 0;
		}

		$tabla = $wpdb->prefix . 'perruqueria_citas';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE cliente_id = %d", $cliente_id )
		);
	}

	/**
	 * Lista una página del historial de citas de un cliente.
	 * El cliente_id va siempre en el WHERE y la mascota se une también por cliente_id.
	 * No se filtra por mascotas activas, para que las citas de una mascota "eliminada" sigan visibles.
	 *
	 * @param int $cliente_id ID del cliente (get_current_user_id()).
	 * @param int $pagina     Número de página (desde 1).
	 * @return array
	 */
	public static function listar_citas_cliente( $cliente_id, $pagina ) {
		global $wpdb;

		$cliente_id = absint( $cliente_id );
		$pagina     = max( 1, absint( $pagina ) );
		if ( $cliente_id <= 0 ) {
			return array();
		}

		$limite  = self::CITAS_POR_PAGINA;
		$offset  = ( $pagina - 1 ) * $limite;
		$t_citas = $wpdb->prefix . 'perruqueria_citas';
		$t_masc  = $wpdb->prefix . 'perruqueria_mascotas';
		$t_serv  = $wpdb->prefix . 'perruqueria_servicios';

		$sql = $wpdb->prepare(
			"SELECT c.id, c.inicio, c.fin, c.estado, c.notas,
			        m.nombre AS mascota_nombre,
			        s.nombre AS servicio_nombre, s.precio AS servicio_precio
			 FROM {$t_citas} c
			 INNER JOIN {$t_masc} m ON m.id = c.mascota_id AND m.cliente_id = c.cliente_id
			 INNER JOIN {$t_serv} s ON s.id = c.servicio_id
			 WHERE c.cliente_id = %d
			 ORDER BY c.inicio DESC, c.id DESC
			 LIMIT %d OFFSET %d",
			$cliente_id,
			$limite,
			$offset
		);

		$filas = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $filas ) ? $filas : array();
	}

	/**
	 * Determina si el cliente puede ver el botón de cancelar en una cita.
	 *
	 * @param array $cita Fila de la cita.
	 * @return string 'si' (puede cancelar), 'plazo' (cita futura fuera de plazo) o 'no'.
	 */
	public static function estado_cancelacion( array $cita ) {
		if ( ! La_Perruqueria_Reservas::validar_transicion( $cita['estado'], 'cancelada' ) ) {
			return 'no';
		}

		$validacion = La_Perruqueria_Reservas::validar_plazo_cancelacion( $cita['inicio'] );
		if ( true === $validacion ) {
			return 'si';
		}

		$tz        = wp_timezone();
		$dt_inicio = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $cita['inicio'], $tz );
		$ahora     = new DateTimeImmutable( 'now', $tz );

		if ( $dt_inicio && $dt_inicio > $ahora ) {
			return 'plazo';
		}

		return 'no';
	}

	/**
	 * Procesa el envío del formulario de perfil.
	 */
	public static function procesar_formulario() {
		if ( ! isset( $_POST['perruqueria_perfil_accion'] ) || 'guardar' !== $_POST['perruqueria_perfil_accion'] ) {
			return;
		}

		if ( ! self::usuario_es_cliente() ) {
			return;
		}

		// El usuario siempre sale de la sesión, nunca del formulario.
		$user_id = (int) get_current_user_id();
		$errores = array();
		$exitos  = array();

		$redirect_raw = ! empty( $_POST['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) : home_url();
		$destino      = wp_validate_redirect( $redirect_raw, home_url() );

		if ( ! isset( $_POST['perruqueria_perfil_nonce'] ) || ! La_Perruqueria_Seguridad::verificar_nonce( sanitize_text_field( wp_unslash( $_POST['perruqueria_perfil_nonce'] ) ), 'perruqueria_perfil_formulario' ) ) {
			self::guardar_avisos( $user_id, array( __( 'Sesión expirada o petición inválida. Intenta nuevamente.', 'la-perruqueria' ) ), array() );
			wp_safe_redirect( $destino );
			exit;
		}

		$usuario = get_userdata( $user_id );
		if ( ! $usuario ) {
			return;
		}

		$nombre   = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
		$correo   = isset( $_POST['correo'] ) ? sanitize_email( wp_unslash( $_POST['correo'] ) ) : '';
		$telefono = isset( $_POST['telefono'] ) ? sanitize_text_field( wp_unslash( $_POST['telefono'] ) ) : '';
		$password = isset( $_POST['password_actual'] ) ? (string) wp_unslash( $_POST['password_actual'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// 1. Validaciones (si algo falla, no se guarda nada).
		if ( '' === $nombre ) {
			$errores[] = __( 'El nombre es obligatorio.', 'la-perruqueria' );
		} elseif ( mb_strlen( $nombre ) > 100 ) {
			$errores[] = __( 'El nombre no puede superar los 100 caracteres.', 'la-perruqueria' );
		}

		if ( '' === $correo || ! is_email( $correo ) ) {
			$errores[] = __( 'El correo electrónico no es válido.', 'la-perruqueria' );
		}

		if ( '' !== $telefono && ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $telefono ) ) {
			$errores[] = __( 'El teléfono solo puede contener números, espacios, +, - y paréntesis (entre 7 y 20 caracteres).', 'la-perruqueria' );
		}

		$correo_cambia = ( '' !== $correo && 0 !== strcasecmp( $correo, $usuario->user_email ) );

		if ( empty( $errores ) && $correo_cambia ) {
			// Cambiar el correo exige la contraseña actual (protege la cuenta si la sesión queda abierta).
			if ( '' === $password || ! wp_check_password( $password, $usuario->user_pass, $user_id ) ) {
				$errores[] = __( 'Para cambiar el correo debes escribir tu contraseña actual correctamente.', 'la-perruqueria' );
			} else {
				$existente = email_exists( $correo );
				if ( $existente && (int) $existente !== $user_id ) {
					$errores[] = __( 'Ese correo electrónico ya está en uso.', 'la-perruqueria' );
				}
			}
		}

		if ( ! empty( $errores ) ) {
			self::guardar_avisos( $user_id, $errores, array() );
			wp_safe_redirect( $destino );
			exit;
		}

		// 2. Guardado.
		$datos = array(
			'ID'           => $user_id,
			'display_name' => $nombre,
			'first_name'   => $nombre,
		);
		if ( $correo_cambia ) {
			$datos['user_email'] = $correo;
		}

		$resultado = wp_update_user( $datos );
		if ( is_wp_error( $resultado ) ) {
			error_log( 'La Perruqueria: fallo al actualizar perfil: ' . $resultado->get_error_message() );
			self::guardar_avisos( $user_id, array( __( 'No se pudo actualizar el perfil. Inténtalo de nuevo.', 'la-perruqueria' ) ), array() );
			wp_safe_redirect( $destino );
			exit;
		}

		if ( '' === $telefono ) {
			delete_user_meta( $user_id, self::META_TELEFONO );
		} else {
			update_user_meta( $user_id, self::META_TELEFONO, $telefono );
		}

		$exitos[] = __( 'Tu perfil se actualizó correctamente.', 'la-perruqueria' );
		self::guardar_avisos( $user_id, array(), $exitos );
		wp_safe_redirect( $destino );
		exit;
	}

	/**
	 * Renderiza el shortcode [perruqueria_perfil].
	 *
	 * @return string
	 */
	public static function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="perruqueria-card perruqueria-auth-required">
				<h3><?php esc_html_e( 'Mi perfil', 'la-perruqueria' ); ?></h3>
				<p><?php esc_html_e( 'Debes iniciar sesión para ver tu perfil.', 'la-perruqueria' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		if ( ! self::usuario_es_cliente() ) {
			ob_start();
			?>
			<div class="perruqueria-card perruqueria-auth-required">
				<h3><?php esc_html_e( 'Acceso restringido', 'la-perruqueria' ); ?></h3>
				<p><?php esc_html_e( 'Esta sección está reservada exclusivamente para clientes de La Perruquería.', 'la-perruqueria' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		self::encolar_assets();

		$user_id  = (int) get_current_user_id();
		$usuario  = wp_get_current_user();
		$avisos   = self::obtener_avisos( $user_id );
		$telefono = self::obtener_telefono( $user_id );
		$destino  = get_permalink();
		if ( ! $destino ) {
			$destino = home_url();
		}

		// Historial paginado (la página se toma de la URL y se limita al rango válido).
		$total_citas = self::contar_citas_cliente( $user_id );
		$paginas     = max( 1, (int) ceil( $total_citas / self::CITAS_POR_PAGINA ) );
		$pagina      = isset( $_GET['pagina_citas'] ) ? absint( $_GET['pagina_citas'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pagina      = min( max( 1, $pagina ), $paginas );
		$citas       = self::listar_citas_cliente( $user_id, $pagina );
		$ajustes     = La_Perruqueria_Horarios::obtener_ajustes();
		$horas_cancelar = absint( $ajustes['anticipacion_cancelar'] );

		ob_start();
		include PERRUQUERIA_PATH . 'templates/perfil-cliente.php';
		return ob_get_clean();
	}
}
