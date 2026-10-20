<?php
/**
 * Lógica de activación, creación de tablas relacionales y llaves foráneas.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class La_Perruqueria_Activator {

	/**
	 * Ejecuta el proceso de activación del plugin.
	 */
	public static function activar() {
		self::crear_tablas();
		self::crear_llaves_foraneas();
		self::sembrar_categorias_iniciales();
		self::establecer_opciones_por_defecto();
		La_Perruqueria_Roles::registrar_roles();
		flush_rewrite_rules();
	}

	/**
	 * Crea las 4 tablas con dbDelta en orden estricto de dependencia.
	 */
	public static function crear_tablas() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$tabla_categorias = $wpdb->prefix . 'perruqueria_categorias';
		$tabla_servicios  = $wpdb->prefix . 'perruqueria_servicios';
		$tabla_mascotas   = $wpdb->prefix . 'perruqueria_mascotas';
		$tabla_citas      = $wpdb->prefix . 'perruqueria_citas';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Tabla Categorías
		$sql_categorias = "CREATE TABLE {$tabla_categorias} (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(50) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_categoria (nombre)
) {$charset_collate} ENGINE=InnoDB;";
		dbDelta( $sql_categorias );

		// 2. Tabla Servicios
		$sql_servicios = "CREATE TABLE {$tabla_servicios} (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id TINYINT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT NULL,
  duracion_min SMALLINT UNSIGNED NOT NULL,
  precio DECIMAL(8,2) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY idx_categoria (categoria_id)
) {$charset_collate} ENGINE=InnoDB;";
		dbDelta( $sql_servicios );

		// 3. Tabla Mascotas
		$sql_mascotas = "CREATE TABLE {$tabla_mascotas} (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  especie VARCHAR(30) NOT NULL,
  raza VARCHAR(60) NULL,
  tamano ENUM('pequeno','mediano','grande') NOT NULL,
  edad_anios TINYINT UNSIGNED NULL,
  observaciones TEXT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_mascota_cliente (id, cliente_id),
  KEY idx_cliente (cliente_id)
) {$charset_collate} ENGINE=InnoDB;";
		dbDelta( $sql_mascotas );

		// 4. Tabla Citas
		$sql_citas = "CREATE TABLE {$tabla_citas} (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id BIGINT UNSIGNED NOT NULL,
  mascota_id INT UNSIGNED NOT NULL,
  servicio_id INT UNSIGNED NOT NULL,
  inicio DATETIME NOT NULL,
  fin DATETIME NOT NULL,
  estado ENUM('pendiente','confirmada','reprogramada','cancelada') NOT NULL DEFAULT 'pendiente',
  notas TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_disponibilidad (estado, inicio, fin),
  KEY idx_cliente (cliente_id, inicio),
  KEY idx_servicio (servicio_id),
  KEY idx_mascota_cliente (mascota_id, cliente_id)
) {$charset_collate} ENGINE=InnoDB;";
		dbDelta( $sql_citas );

		update_option( 'perruqueria_db_version', PERRUQUERIA_VERSION );
	}

	/**
	 * Agrega las llaves foráneas con ALTER TABLE tras verificar information_schema.TABLE_CONSTRAINTS.
	 */
	public static function crear_llaves_foraneas() {
		global $wpdb;

		$tabla_categorias = $wpdb->prefix . 'perruqueria_categorias';
		$tabla_servicios  = $wpdb->prefix . 'perruqueria_servicios';
		$tabla_mascotas   = $wpdb->prefix . 'perruqueria_mascotas';
		$tabla_citas      = $wpdb->prefix . 'perruqueria_citas';
		$tabla_usuarios   = $wpdb->users;

		$foreign_keys = array(
			// 1. Servicios -> Categorías
			array(
				'tabla'      => $tabla_servicios,
				'constraint' => 'fk_perruqueria_servicios_cat',
				'definicion' => "FOREIGN KEY (categoria_id) REFERENCES {$tabla_categorias} (id)",
			),
			// 2. Mascotas -> wp_users
			array(
				'tabla'      => $tabla_mascotas,
				'constraint' => 'fk_perruqueria_mascotas_cliente',
				'definicion' => "FOREIGN KEY (cliente_id) REFERENCES {$tabla_usuarios} (ID)",
			),
			// 3. Citas -> wp_users
			array(
				'tabla'      => $tabla_citas,
				'constraint' => 'fk_perruqueria_citas_cliente',
				'definicion' => "FOREIGN KEY (cliente_id) REFERENCES {$tabla_usuarios} (ID)",
			),
			// 4. Citas -> Mascotas (clave foránea compuesta id, cliente_id)
			array(
				'tabla'      => $tabla_citas,
				'constraint' => 'fk_perruqueria_citas_mascota_cliente',
				'definicion' => "FOREIGN KEY (mascota_id, cliente_id) REFERENCES {$tabla_mascotas} (id, cliente_id)",
			),
			// 5. Citas -> Servicios
			array(
				'tabla'      => $tabla_citas,
				'constraint' => 'fk_perruqueria_citas_servicio',
				'definicion' => "FOREIGN KEY (servicio_id) REFERENCES {$tabla_servicios} (id)",
			),
		);

		foreach ( $foreign_keys as $fk ) {
			if ( ! self::existe_llave_foranea( $fk['tabla'], $fk['constraint'] ) ) {
				$sql = "ALTER TABLE {$fk['tabla']} ADD CONSTRAINT {$fk['constraint']} {$fk['definicion']}";
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	/**
	 * Comprueba si una llave foránea ya existe consultando information_schema.TABLE_CONSTRAINTS.
	 *
	 * @param string $tabla      Nombre completo de la tabla.
	 * @param string $constraint Nombre de la restricción foránea.
	 * @return bool
	 */
	private static function existe_llave_foranea( $tabla, $constraint ) {
		global $wpdb;

		$resultado = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM information_schema.TABLE_CONSTRAINTS
				 WHERE CONSTRAINT_SCHEMA = DATABASE()
				   AND TABLE_NAME = %s
				   AND CONSTRAINT_NAME = %s
				   AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
				$tabla,
				$constraint
			)
		);

		return (int) $resultado > 0;
	}

	/**
	 * Siembra las categorías iniciales requeridas al activar el plugin.
	 */
	public static function sembrar_categorias_iniciales() {
		global $wpdb;

		$tabla_categorias = $wpdb->prefix . 'perruqueria_categorias';
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabla_categorias}" );

		if ( 0 === $total ) {
			$categorias_iniciales = array( 'Higiene', 'Cuidado', 'Estética' );
			foreach ( $categorias_iniciales as $nombre ) {
				$wpdb->insert(
					$tabla_categorias,
					array( 'nombre' => $nombre ),
					array( '%s' )
				);
			}
		}
	}

	/**
	 * Registra opciones iniciales de configuración.
	 */
	private static function establecer_opciones_por_defecto() {
		if ( false === get_option( 'perruqueria_purgar_al_desinstalar' ) ) {
			add_option( 'perruqueria_purgar_al_desinstalar', false );
		}

		if ( false === get_option( 'perruqueria_anticipacion_cancelacion' ) ) {
			add_option( 'perruqueria_anticipacion_cancelacion', 2 );
		}

		if ( false === get_option( 'perruqueria_horarios_config' ) ) {
			$horarios_defecto = array(
				'dias_laborales' => array( 1, 2, 3, 4, 5, 6 ),
				'hora_apertura'  => '09:00',
				'hora_cierre'    => '18:00',
				'feriados'       => array(),
			);
			add_option( 'perruqueria_horarios_config', $horarios_defecto );
		}
	}
}
