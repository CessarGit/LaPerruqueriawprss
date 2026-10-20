<?php
/**
 * Cargador principal y orquestador del plugin.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Plugin {

	/**
	 * Instancia única (Singleton).
	 *
	 * @var La_Perruqueria_Plugin|null
	 */
	private static $instancia = null;

	/**
	 * Obtiene la instancia única de la clase.
	 *
	 * @return La_Perruqueria_Plugin
	 */
	public static function obtener_instancia() {
		if ( null === self::$instancia ) {
			self::$instancia = new self();
		}
		return self::$instancia;
	}

	/**
	 * Constructor privado.
	 */
	private function __construct() {
		$this->cargar_dependencias();
		$this->definir_hooks();
	}

	/**
	 * Carga de archivos y clases correspondientes a las fases activas.
	 */
	private function cargar_dependencias() {
		require_once PERRUQUERIA_PATH . 'includes/class-seguridad.php';
		require_once PERRUQUERIA_PATH . 'includes/class-roles.php';
		require_once PERRUQUERIA_PATH . 'includes/class-auth.php';
		require_once PERRUQUERIA_PATH . 'includes/class-mascotas.php';
		require_once PERRUQUERIA_PATH . 'includes/class-servicios.php';
		require_once PERRUQUERIA_PATH . 'includes/class-panel-admin.php';
		require_once PERRUQUERIA_PATH . 'includes/class-horarios.php';
		require_once PERRUQUERIA_PATH . 'includes/class-citas-db.php';
		require_once PERRUQUERIA_PATH . 'includes/class-reservas.php';
		require_once PERRUQUERIA_PATH . 'includes/class-rest-api.php';
		require_once PERRUQUERIA_PATH . 'includes/class-perfil.php';
	}

	/**
	 * Inicialización de ganchos del sistema.
	 */
	private function definir_hooks() {
		add_action( 'plugins_loaded', array( $this, 'verificar_version_bd' ) );
		add_action( 'init', array( $this, 'cargar_traducciones' ) );
		add_action( 'rest_api_init', array( 'La_Perruqueria_REST_API', 'registrar_rutas' ) );

		// Inicializar módulos de Fases 2, 3, 4 y 5
		La_Perruqueria_Auth::init();
		La_Perruqueria_Mascotas::init();
		La_Perruqueria_Servicios::init();
		La_Perruqueria_Panel_Admin::init();
		La_Perruqueria_Reservas::init();
		La_Perruqueria_Perfil::init();

		// Restricciones de seguridad para el rol Cliente
		add_action( 'admin_init', array( 'La_Perruqueria_Roles', 'restringir_acceso_admin' ) );
		add_filter( 'show_admin_bar', array( 'La_Perruqueria_Roles', 'ocultar_admin_bar_clientes' ) );

		// Encolado de scripts y estilos frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'encolar_scripts_frontend' ) );
	}

	/**
	 * Encola estilos y scripts frontend con configuración localizada para la API REST.
	 */
	public function encolar_scripts_frontend() {
		wp_enqueue_style(
			'perruqueria-frontend-css',
			PERRUQUERIA_URL . 'assets/css/frontend.css',
			array(),
			PERRUQUERIA_VERSION
		);

		wp_enqueue_script(
			'perruqueria-frontend-js',
			PERRUQUERIA_URL . 'assets/js/frontend.js',
			array(),
			PERRUQUERIA_VERSION,
			true
		);

		$tz = wp_timezone();
		wp_localize_script(
			'perruqueria-frontend-js',
			'perruqueriaSettings',
			array(
				'root'     => esc_url_raw( rest_url() ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'hoy'      => ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' ),
				'i18n'     => array(
					'cargando_turnos'     => __( 'Consultando turnos disponibles...', 'la-perruqueria' ),
					'sin_turnos'          => __( 'No hay turnos disponibles para la fecha seleccionada. Por favor, selecciona otro día.', 'la-perruqueria' ),
					'selecciona_turno'    => __( 'Por favor, selecciona un turno disponible para continuar.', 'la-perruqueria' ),
					'confirmando_reserva' => __( 'Procesando reserva...', 'la-perruqueria' ),
					'error_generico'      => __( 'Ocurrió un error inesperado al procesar la solicitud.', 'la-perruqueria' ),
					'reserva_exitosa'     => __( '¡Reserva confirmada con éxito!', 'la-perruqueria' ),
				),
			)
		);
	}

	/**
	 * Verifica si la versión de base de datos ha cambiado para sincronizar tablas y llaves foráneas.
	 */
	public function verificar_version_bd() {
		$version_instalada = get_option( 'perruqueria_db_version', '' );
		if ( version_compare( $version_instalada, PERRUQUERIA_VERSION, '<' ) ) {
			require_once PERRUQUERIA_PATH . 'includes/class-activator.php';
			La_Perruqueria_Activator::crear_tablas();
			La_Perruqueria_Activator::crear_llaves_foraneas();
			La_Perruqueria_Activator::sembrar_categorias_iniciales();
		}
	}

	/**
	 * Carga el dominio de traducción del plugin.
	 */
	public function cargar_traducciones() {
		load_plugin_textdomain(
			'la-perruqueria',
			false,
			dirname( plugin_basename( PERRUQUERIA_FILE ) ) . '/languages'
		);
	}
}
