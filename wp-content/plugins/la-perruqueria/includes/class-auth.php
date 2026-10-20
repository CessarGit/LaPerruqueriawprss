<?php
/**
 * Módulo de autenticación (registro, login y logout) para clientes.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Auth {

	/**
	 * Mensajes de error en la autenticación.
	 *
	 * @var array
	 */
	private static $errores = array();

	/**
	 * Mensajes de éxito en la autenticación.
	 *
	 * @var array
	 */
	private static $exitos = array();

	/**
	 * Inicializa los ganchos de procesamiento temprano y el shortcode.
	 * Se usa init con prioridad temprana (5) para procesar antes de cualquier salida HTML.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'procesar_login' ), 5 );
		add_action( 'init', array( __CLASS__, 'procesar_registro' ), 5 );
		add_action( 'init', array( __CLASS__, 'procesar_logout' ), 5 );
		add_shortcode( 'perruqueria_auth', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Procesa el formulario de registro de nuevos clientes.
	 */
	public static function procesar_registro() {
		if ( ! isset( $_POST['perruqueria_auth_accion'] ) || 'registro' !== $_POST['perruqueria_auth_accion'] ) {
			return;
		}

		if ( ! isset( $_POST['perruqueria_auth_nonce'] ) || ! La_Perruqueria_Seguridad::verificar_nonce( sanitize_text_field( wp_unslash( $_POST['perruqueria_auth_nonce'] ) ), 'perruqueria_auth_formulario' ) ) {
			self::$errores[] = __( 'Sesión expirada o petición inválida. Intenta nuevamente.', 'la-perruqueria' );
			return;
		}

		// Trampa honeypot contra bots de spam
		if ( ! empty( $_POST['perruqueria_hp'] ) ) {
			self::$errores[] = __( 'Acción rechazada.', 'la-perruqueria' );
			return;
		}

		$usuario  = isset( $_POST['usuario'] ) ? sanitize_user( wp_unslash( $_POST['usuario'] ) ) : '';
		$correo   = isset( $_POST['correo'] ) ? sanitize_email( wp_unslash( $_POST['correo'] ) ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $usuario ) || empty( $correo ) || empty( $password ) ) {
			self::$errores[] = __( 'Todos los campos son obligatorios.', 'la-perruqueria' );
			return;
		}

		if ( ! is_email( $correo ) ) {
			self::$errores[] = __( 'El correo electrónico no es válido.', 'la-perruqueria' );
			return;
		}

		if ( username_exists( $usuario ) ) {
			self::$errores[] = __( 'El nombre de usuario ya está registrado.', 'la-perruqueria' );
			return;
		}

		if ( email_exists( $correo ) ) {
			self::$errores[] = __( 'El correo electrónico ya está registrado.', 'la-perruqueria' );
			return;
		}

		// Exigencia de mínimo 8 caracteres para mayor robustez
		if ( strlen( $password ) < 8 ) {
			self::$errores[] = __( 'La contraseña debe tener un mínimo de 8 caracteres.', 'la-perruqueria' );
			return;
		}

		$user_id = wp_create_user( $usuario, $password, $correo );
		if ( is_wp_error( $user_id ) ) {
			self::$errores[] = $user_id->get_error_message();
			return;
		}

		// (a) El rol Cliente se fija exclusivamente en el servidor; ningún parámetro externo puede sobreescribirlo
		$user = new WP_User( $user_id );
		$user->set_role( 'cliente' );

		// Iniciar sesión y fijar cookies antes de cualquier salida de headers
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		$redirect_raw = ! empty( $_POST['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) : home_url();
		$safe_url     = wp_validate_redirect( $redirect_raw, home_url() );
		wp_safe_redirect( $safe_url );
		exit;
	}

	/**
	 * Procesa el formulario de inicio de sesión de clientes.
	 */
	public static function procesar_login() {
		if ( ! isset( $_POST['perruqueria_auth_accion'] ) || 'login' !== $_POST['perruqueria_auth_accion'] ) {
			return;
		}

		if ( ! isset( $_POST['perruqueria_auth_nonce'] ) || ! La_Perruqueria_Seguridad::verificar_nonce( sanitize_text_field( wp_unslash( $_POST['perruqueria_auth_nonce'] ) ), 'perruqueria_auth_formulario' ) ) {
			self::$errores[] = __( 'Sesión expirada o petición inválida. Intenta nuevamente.', 'la-perruqueria' );
			return;
		}

		// Trampa honeypot
		if ( ! empty( $_POST['perruqueria_hp'] ) ) {
			self::$errores[] = __( 'Acción rechazada.', 'la-perruqueria' );
			return;
		}

		$login    = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? $_POST['pwd'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$remember = ! empty( $_POST['rememberme'] );

		if ( empty( $login ) || empty( $password ) ) {
			// (d) Mensaje genérico para no revelar detalles
			self::$errores[] = __( 'Nombre de usuario o contraseña incorrectos.', 'la-perruqueria' );
			return;
		}

		$credenciales = array(
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => $remember,
		);

		$user = wp_signon( $credenciales, is_ssl() );

		if ( is_wp_error( $user ) ) {
			// (d) Mensaje de error genérico que previene la enumeración de usuarios
			self::$errores[] = __( 'Nombre de usuario o contraseña incorrectos.', 'la-perruqueria' );
			return;
		}

		$redirect_raw = ! empty( $_POST['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) : home_url();
		$safe_url     = wp_validate_redirect( $redirect_raw, home_url() );
		wp_safe_redirect( $safe_url );
		exit;
	}

	/**
	 * Procesa el cierre de sesión seguro mediante nonce.
	 */
	public static function procesar_logout() {
		if ( isset( $_GET['perruqueria_logout'] ) && '1' === $_GET['perruqueria_logout'] ) {
			if ( isset( $_GET['_wpnonce'] ) && La_Perruqueria_Seguridad::verificar_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'perruqueria_logout' ) ) {
				wp_logout();
				wp_safe_redirect( home_url() );
				exit;
			}
		}
	}

	/**
	 * Genera la URL segura de logout.
	 *
	 * @return string
	 */
	public static function url_logout() {
		return wp_nonce_url( add_query_arg( 'perruqueria_logout', '1', home_url() ), 'perruqueria_logout' );
	}

	/**
	 * Obtiene los errores acumulados.
	 *
	 * @return array
	 */
	public static function obtener_errores() {
		return self::$errores;
	}

	/**
	 * Renderiza el shortcode [perruqueria_auth].
	 *
	 * @return string HTML de la vista.
	 */
	public static function render_shortcode() {
		ob_start();
		if ( is_user_logged_in() ) {
			$usuario = wp_get_current_user();
			?>
			<div class="perruqueria-card perruqueria-auth-logged">
				<p>
					<?php printf( esc_html__( 'Sesión iniciada como %s (%s).', 'la-perruqueria' ), '<strong>' . esc_html( $usuario->display_name ) . '</strong>', esc_html( $usuario->user_email ) ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( self::url_logout() ); ?>" class="perruqueria-btn perruqueria-btn-danger">
						<?php esc_html_e( 'Cerrar sesión', 'la-perruqueria' ); ?>
					</a>
				</p>
			</div>
			<?php
		} else {
			include PERRUQUERIA_PATH . 'templates/auth-login-registro.php';
		}
		return ob_get_clean();
	}
}
