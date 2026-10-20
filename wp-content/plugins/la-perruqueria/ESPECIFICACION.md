# La Perruquería: especificación técnica y funcional

## 1. Objetivo
Aplicación web (plugin para WordPress y MariaDB) para agendamiento de servicios de higiene, cuidado y estética canina, y gestión operativa integral por parte del personal.

---

## 2. Roles y Capacidades
- **Cliente (`cliente`)**:
  - Solo accede a sus propios datos (perfil, mascotas e historial de citas).
  - Capacidades específicas: `leer_mascota`, `crear_mascota`, `editar_mascota`, `eliminar_mascota`, `crear_cita`, `cancelar_cita_propia`.
  - Sin acceso al panel de administración general de WordPress (`show_admin_bar(false)` para este rol).
- **Personal / Administrador (`manage_options` o `gestionar_citas`)**:
  - Gestiona la totalidad de citas, horarios, estados y catálogo de servicios mediante pantallas de administración personalizadas.

---

## 3. Reglas de Negocio

### 3.1 Horarios y Disponibilidad
- **Horario comercial base**: Lunes a Sábado de 09:00 a 18:00 (en la zona horaria del sitio obtenida con `wp_timezone()`).
- **Días no laborables**: Domingos y feriados configurables.
- **Capacidad**: Puesto único de atención (un solo peluquero, sin `empleado_id`). La disponibilidad es global para el establecimiento.
- **Intervalos de Citas**: Sin fragmentación inconsistente; el fin de la cita se calcula de forma atómica en el servidor: `fin = inicio + duracion_min`.

### 3.2 Cancelación y Modificación
- **Anticipación mínima**: El cliente solo puede cancelar citas con un mínimo de **2 horas de anticipación** respecto a la hora de `inicio`. Si el plazo es menor, debe contactar directamente al establecimiento.
- **Integridad referencial y Mascotas**: La eliminación de mascotas por parte del cliente es **lógica** (`activo = 0`), nunca física, preservando la integridad histórica de citas pasadas y futuras.

### 3.3 Matriz de Estados y Transiciones
Los estados válidos son exclusivamente: `pendiente`, `confirmada`, `reprogramada` y `cancelada`.

| Estado actual | Transiciones permitidas |
| :--- | :--- |
| `pendiente` | `confirmada`, `cancelada` |
| `confirmada` | `reprogramada`, `cancelada` |
| `reprogramada` | `confirmada`, `cancelada` |
| `cancelada` | (Estado final) |

*Nota:* Las citas con estado `cancelada` no bloquean la disponibilidad horaria.

---

## 4. Esquema de Base de Datos Relacional (MariaDB / InnoDB)

Todas las entidades de negocio se gestionan en tablas propias con motor **InnoDB** para garantizar integridad referencial mediante llaves foráneas. Los usuarios se integran con `wp_users`.

### 4.1 Categorías (`wp_perruqueria_categorias`)
Almacena las clasificaciones de los servicios. Al activar el plugin se insertan las categorías iniciales: *Higiene*, *Cuidado* y *Estética*.
```sql
CREATE TABLE {$wpdb->prefix}perruqueria_categorias (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(50) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categoria (nombre)
) ENGINE=InnoDB;
```

### 4.2 Servicios (`wp_perruqueria_servicios`)
Catálogo de servicios ofrecidos por la peluquería canina.
```sql
CREATE TABLE {$wpdb->prefix}perruqueria_servicios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id TINYINT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT NULL,
  duracion_min SMALLINT UNSIGNED NOT NULL,
  precio DECIMAL(8,2) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  FOREIGN KEY (categoria_id) REFERENCES {$wpdb->prefix}perruqueria_categorias (id)
) ENGINE=InnoDB;
```

### 4.3 Mascotas (`wp_perruqueria_mascotas`)
Ficha de las mascotas de cada cliente. La llave única compuesta `(id, cliente_id)` permite validar la pertenencia estricta en las citas.
```sql
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
  PRIMARY KEY (id),
  UNIQUE KEY uq_mascota_cliente (id, cliente_id),
  FOREIGN KEY (cliente_id) REFERENCES {$wpdb->prefix}users (ID)
) ENGINE=InnoDB;
```

### 4.4 Citas (`wp_perruqueria_citas`)
Registro transaccional de citas agendadas con integridad foránea compuesta hacia mascota y cliente.
```sql
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
  PRIMARY KEY (id),
  KEY idx_disponibilidad (estado, inicio, fin),
  KEY idx_cliente (cliente_id, inicio),
  FOREIGN KEY (cliente_id) REFERENCES {$wpdb->prefix}users (ID),
  FOREIGN KEY (mascota_id, cliente_id) REFERENCES {$wpdb->prefix}perruqueria_mascotas (id, cliente_id),
  FOREIGN KEY (servicio_id) REFERENCES {$wpdb->prefix}perruqueria_servicios (id)
) ENGINE=InnoDB;
```

### 4.5 Reglas de Creación y Llaves Foráneas
1. Las tablas y sus índices primarios/secundarios se crean mediante `dbDelta()`.
2. Como `dbDelta()` omite o falla con las restricciones `FOREIGN KEY`, las llaves foráneas se agregan posteriormente mediante consultas `ALTER TABLE` tras comprobar previamente en `information_schema.TABLE_CONSTRAINTS` que no existan.
3. El borrado de datos en la desinstalación elimina las 4 tablas en orden inverso a sus dependencias si `perruqueria_purgar_al_desinstalar` está activo.

---

## 5. Prevención de Concurrencia y Seguridad Técnica
- **Reserva Atómica**: Bloqueo con `GET_LOCK` y `RELEASE_LOCK` en MariaDB delimitado por fecha:
  `lock = 'perruqueria_' + YYYY-MM-DD`.
- **Validación de Traslapes**: `inicio < fin_solicitado AND fin > inicio_solicitado` para estados activos (`pendiente`, `confirmada`, `reprogramada`).
- **Seguridad en Servidor**:
  - `cliente_id` siempre extraído desde `get_current_user_id()`.
  - Verificación de pertenencia de la mascota a nivel de base de datos (`FOREIGN KEY (mascota_id, cliente_id)`) y en código PHP.
  - Validación de que el servicio esté activo (`activo = 1`).
  - Nonces, sanitización y escape contextual en todas las capas.
  - Honeypot antispam en formularios públicos de registro/acceso.
  - Redirecciones protegidas con `wp_validate_redirect()`.
  - Zona horaria del sitio con `wp_timezone()`.
