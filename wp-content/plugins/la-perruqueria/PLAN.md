# Plan de Desarrollo: La Perruquería (Plugin de WordPress)

Este documento define la arquitectura técnica, el modelo de datos relacional nativo y la hoja de ruta de implementación estructurada en fases progresivas e independientes. Todo el desarrollo parte desde cero, aplicando desde la primera línea los estándares de seguridad de WordPress (nonces, sanitización, escape, verificación de capacidades y comprobación de propiedad de datos).

---

## 1. Principios Técnicos y Reglas de Arquitectura

1. **Tablas Relacionales Propias:**
   - La persistencia no utiliza tipos de contenido personalizados (`wp_posts` / `wp_postmeta`), sino cuatro tablas propias en MariaDB con motor **InnoDB** para garantizar integridad referencial.
   - Creación mediante `dbDelta` usando siempre `$wpdb->prefix` y enlace a usuarios con `$wpdb->users`.
   - Adición de llaves foráneas con `ALTER TABLE` posterior a `dbDelta`, verificando previamente su existencia en `information_schema.TABLE_CONSTRAINTS`.
2. **Capacidad Única (Sin Empleados):**
   - El negocio opera con un único estilista/puesto de atención. No existe `empleado_id`.
   - La concurrencia atómica de reservas se gestiona mediante `GET_LOCK` y `RELEASE_LOCK` a nivel de fecha (`perruqueria_{YYYY-MM-DD}`).
3. **Ciclo de Estados Estricto:**
   - Estados de cita: `pendiente`, `confirmada`, `reprogramada`, `cancelada`.
   - Transiciones validadas en el servidor; las citas canceladas liberan el horario.
4. **Gestión de Usuarios y Eliminación:**
   - Los clientes utilizan `wp_users` con el rol `cliente` (sin acceso a `wp-admin` ni barra superior).
   - Hook a `delete_user` para evitar fallos por llave foránea o registros huérfanos si un usuario es borrado desde WordPress.
5. **Seguridad Continua en Cada Fase:**
   - Nonces en todas las peticiones POST/AJAX/REST.
   - Sanitización a la entrada (`sanitize_text_field`, `absint`, `sanitize_textarea_field`, etc.).
   - Escape contextual a la salida (`esc_html`, `esc_attr`, `esc_url`).
   - Comprobación de capacidades (`current_user_can`).
   - Verificación de propiedad (IDOR): el usuario solo opera sobre sus propios registros (`cliente_id === get_current_user_id()`), reforzado a nivel de base de datos mediante la clave foránea compuesta `(mascota_id, cliente_id)`.

---

## 2. Esquema de Base de Datos (MariaDB / InnoDB)

### 2.1 Tablas
```sql
-- 1. Categorías
CREATE TABLE {$wpdb->prefix}perruqueria_categorias (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(50) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_categoria (nombre)
) ENGINE=InnoDB;

-- 2. Servicios
CREATE TABLE {$wpdb->prefix}perruqueria_servicios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id TINYINT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT NULL,
  duracion_min SMALLINT UNSIGNED NOT NULL,
  precio DECIMAL(8,2) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY idx_categoria (categoria_id)
) ENGINE=InnoDB;

-- 3. Mascotas
CREATE TABLE {$wpdb->prefix}perruqueria_mascotas (
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
) ENGINE=InnoDB;

-- 4. Citas
CREATE TABLE {$wpdb->prefix}perruqueria_citas (
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
) ENGINE=InnoDB;
```

### 2.2 Llaves Foráneas (Inyectadas con ALTER TABLE)
- `fk_perruqueria_servicios_cat`: `servicios(categoria_id) -> categorias(id)`
- `fk_perruqueria_mascotas_cliente`: `mascotas(cliente_id) -> wp_users(ID)`
- `fk_perruqueria_citas_cliente`: `citas(cliente_id) -> wp_users(ID)`
- `fk_perruqueria_citas_mascota_cliente`: `citas(mascota_id, cliente_id) -> mascotas(id, cliente_id)`
- `fk_perruqueria_citas_servicio`: `citas(servicio_id) -> servicios(id)`

---

## 3. Fases de Desarrollo

### Fase 1: Infraestructura, Base de Datos y Rol Cliente
- **Archivos a crear:**
  - `la-perruqueria.php`: Cabecera del plugin, definición de constantes (`PERRUQUERIA_VERSION`, rutas), hooks de activación y desactivación, inicialización del cargador.
  - `includes/class-plugin.php`: Clase principal orquestadora; carga dependencias, engancha verificación de versión de BD en `plugins_loaded`, gestiona hooks generales.
  - `includes/class-activator.php`: Creación de las 4 tablas con `dbDelta` en orden de dependencias, verificación de restricciones en `information_schema.TABLE_CONSTRAINTS` y adición de las 5 llaves foráneas con `ALTER TABLE`, sembrado de categorías iniciales (*Higiene*, *Cuidado*, *Estética*), registro de versión en opción `perruqueria_db_version`.
  - `includes/class-deactivator.php`: Limpieza de reescrituras al desactivar (sin borrar tablas ni datos).
  - `uninstall.php`: Limpieza total de tablas, opciones y rol solo si la opción `perruqueria_purgar_al_desinstalar` está activa (por defecto desactivada).
  - `includes/class-roles.php`: Registro del rol `cliente`, restricción de acceso a `wp-admin` y ocultamiento de la barra de administración de WordPress.
- **Prueba manual de la fase:**
  1. Activar el plugin desde el panel de WordPress.
  2. Verificar en phpMyAdmin o consola MySQL que existan las 4 tablas (`wp_perruqueria_categorias`, `wp_perruqueria_servicios`, `wp_perruqueria_mascotas`, `wp_perruqueria_citas`).
  3. Comprobar que la tabla de categorías contenga los 3 registros iniciales (*Higiene*, *Cuidado*, *Estética*).
  4. Comprobar que las llaves foráneas existan mediante `information_schema.TABLE_CONSTRAINTS`.
  5. Crear un usuario con rol "Cliente", iniciar sesión con él y constatar que no ve la barra superior de WP ni puede acceder a `/wp-admin/` (redirige a la portada).

---

### Fase 2: Registro, Inicio de Sesión y Gestión de Mascotas
- **Archivos a crear:**
  - `includes/class-auth.php`: Formularios seguros de registro e inicio de sesión de clientes con nonces, trampa antispam honeypot, redirección validada con `wp_validate_redirect()` y asignación automática del rol `cliente`.
  - `includes/class-mascotas.php`: Capa de datos SQL para operaciones CRUD sobre `wp_perruqueria_mascotas` (`cliente_id` forzado a `get_current_user_id()`, borrado lógico con `activo = 0`).
  - Gancho `delete_user`: Manejo seguro al borrar un usuario de WordPress desde `wp-admin` (eliminación lógica o borrado en cascada controlado de mascotas y citas para evitar bloqueos por llaves foráneas).
  - `templates/auth-login-registro.php`: Vistas de formularios de autenticación.
  - `templates/mascotas-crud.php`: Formulario y listado frontend de mascotas del cliente autenticado.
- **Prueba manual de la fase:**
  1. Registrar un cliente nuevo desde el formulario público; verificar asignación de rol `cliente`.
  2. Iniciar sesión y registrar 2 mascotas con distintos tamaños y edades.
  3. Modificar una mascota y verificar que solo el dueño puede editarla.
  4. Probar borrado lógico de mascota (`activo = 0`).
  5. Eliminar un usuario de prueba en `wp-admin` y validar que el hook `delete_user` previene errores SQL de llave foránea.

---

### Fase 3: Catálogo de Servicios y Pantalla de Administración
- **Archivos a crear:**
  - `includes/class-servicios.php`: Capa de datos SQL para consultar categorías y gestionar servicios (`categoria_id`, `nombre`, `descripcion`, `duracion_min`, `precio`, `activo`).
  - `includes/class-panel-admin.php`: Registro de menú y submenús en el escritorio de WordPress para administradores/personal.
  - `templates/admin-servicios.php`: Pantalla administrativa en `wp-admin` para listar, crear, editar y alternar disponibilidad de servicios y categorías con nonces y capacidades (`manage_options`).
  - `templates/catalogo-servicios.php`: Vista pública y responsiva del catálogo de servicios agrupados por categoría.
- **Prueba manual de la fase:**
  1. Acceder a `wp-admin` como administrador al menú "La Perruquería -> Servicios".
  2. Crear servicios de prueba asignándolos a las categorías iniciales (ej: Baño básico en Higiene, Corte de raza en Estética).
  3. Editar precio y duración de un servicio.
  4. Desactivar un servicio y verificar que no aparezca en el catálogo público.

---

### Fase 4: Horarios, Motor de Citas y REST API
- **Archivos a crear:**
  - `includes/class-horarios.php`: Configuración comercial (lunes a sábado 9:00 a 18:00, feriados) y cálculo de turnos libres usando `wp_timezone()`.
  - `includes/class-citas-db.php`: Operaciones SQL seguras sobre `wp_perruqueria_citas`. Reserva atómica con candado `GET_LOCK('perruqueria_' . $fecha, 5)` y validación estricta de traslapes (`inicio < fin_solicitado AND fin > inicio_solicitado`).
  - `includes/class-reservas.php`: Reglas de negocio del servidor (extracción de `cliente_id` del usuario en sesión, cálculo de `fin` según `duracion_min`, validación de estados).
  - `includes/class-rest-api.php`: Endpoints `GET /disponibilidad` y `POST /citas` con `permission_callback` y nonces.
  - `templates/formulario-reserva.php`: Formulario guiado de agendamiento.
  - `assets/js/frontend.js`: Selección reactiva de turnos y envío asíncrono.
- **Prueba manual de la fase:**
  1. Consultar disponibilidad para un día hábil; comprobar que los intervalos coincidan con la duración del servicio.
  2. Agendar una cita y constatar su inserción en `wp_perruqueria_citas`.
  3. Intentar agendar en el mismo horario y comprobar que el sistema rechaza el turno por colisión horaria.

---

### Fase 5: Perfil, Historial y Panel de Citas del Personal
- **Archivos a crear:**
  - `includes/class-perfil.php`: Actualización de datos de contacto y lógica de cancelación con anticipación mínima de 2 horas.
  - `templates/perfil-cliente.php`: Visualización de perfil e historial de citas del cliente con badges de estado y botón de cancelación condicional.
  - `templates/admin-panel-citas.php`: Vista de gestión operativa del personal en `wp-admin`: filtrado por fecha y estado, cambio directo según matriz de transiciones y reprogramación rápida.
- **Prueba manual de la fase:**
  1. Editar teléfono del cliente desde su perfil.
  2. Verificar listado de citas propias en el perfil.
  3. Intentar cancelar una cita con más de 2 horas de anticipación (debe cancelarse y liberar el horario).
  4. Intentar cancelar una cita a menos de 2 horas (debe denegarse con mensaje informativo).
  5. Acceder al panel del personal en `wp-admin`, filtrar por estado y confirmar una cita pendiente.

---

### Fase 6: Estilos Responsive, Revisión de Seguridad y Pruebas
- **Archivos a crear / consolidar:**
  - `assets/css/frontend.css` y `assets/css/admin.css`: Estilos visuales armónicos, paleta de colores coherente y apilamiento móvil de formularios, tablas y tarjetas.
  - `includes/class-shortcodes.php`: Shortcodes (`[perruqueria_portal]`, `[perruqueria_catalogo]`, etc.) para incrustar la aplicación en páginas de WordPress.
  - Módulos opcionales: `includes/class-emails.php` (notificaciones `wp_mail`) e `includes/class-footer.php` (pie de página en `wp_footer`).
  - `languages/la-perruqueria.pot`: Plantilla de internacionalización.
- **Checklist de Seguridad:**
  - Nonces verificados en el 100% de las rutas y formularios POST.
  - Sanitización estricta de todos los parámetros de entrada.
  - Escape contextual en todas las impresiones HTML.
  - Comprobación de capacidades (`current_user_can`) y control estricto contra IDOR.
- **Prueba Manual de Concurrencia (Doble Reserva Simultánea):**
  - Ejecución de dos solicitudes simultáneas al mismo horario mediante scripts o pestañas paralelas para confirmar que `GET_LOCK` y la validación atómica admiten únicamente una reserva y rechazan la otra sin generar inconsistencias.
