<?php
/**
 * Pantalla de administración de servicios en wp-admin.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Acceso no autorizado.', 'la-perruqueria' ) );
}

$categorias = La_Perruqueria_Servicios::listar_categorias();
$servicios  = La_Perruqueria_Servicios::listar_servicios_admin();

// Manejo de mensajes vía GET
$mensaje_tipo  = isset( $_GET['mensaje_tipo'] ) ? sanitize_text_field( wp_unslash( $_GET['mensaje_tipo'] ) ) : '';
$mensaje_texto = isset( $_GET['mensaje_texto'] ) ? sanitize_text_field( wp_unslash( rawurldecode( $_GET['mensaje_texto'] ) ) ) : '';

// Comprobar modo edición
$modo_edicion       = false;
$servicio_a_editar  = null;

if ( isset( $_GET['editar_id'] ) ) {
	$id_ed = absint( $_GET['editar_id'] );
	if ( $id_ed > 0 ) {
		$servicio_a_editar = La_Perruqueria_Servicios::obtener_servicio( $id_ed );
		if ( $servicio_a_editar ) {
			$modo_edicion = true;
		}
	}
}
?>

<div class="wrap perruqueria-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Gestión de Servicios - La Perruquería', 'la-perruqueria' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( ! empty( $mensaje_texto ) ) : ?>
		<div class="notice notice-<?php echo ( 'error' === $mensaje_tipo ) ? 'error' : 'success'; ?> is-dismissible">
			<p><?php echo esc_html( $mensaje_texto ); ?></p>
		</div>
	<?php endif; ?>

	<div class="perruqueria-admin-grid">
		<!-- Columna Formulario (Crear o Editar) -->
		<div class="perruqueria-admin-col-form">
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php echo $modo_edicion ? esc_html__( 'Editar Servicio', 'la-perruqueria' ) : esc_html__( 'Añadir Nuevo Servicio', 'la-perruqueria' ); ?></h2>
				</div>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=la-perruqueria' ) ); ?>">
						<?php wp_nonce_field( 'perruqueria_servicio_admin', 'perruqueria_nonce' ); ?>
						<input type="hidden" name="perruqueria_admin_accion" value="<?php echo $modo_edicion ? 'editar_servicio' : 'crear_servicio'; ?>">
						<?php if ( $modo_edicion ) : ?>
							<input type="hidden" name="servicio_id" value="<?php echo esc_attr( $servicio_a_editar['id'] ); ?>">
						<?php endif; ?>

						<table class="form-table">
							<tr>
								<th><label for="perruqueria_nombre"><strong><?php esc_html_e( 'Nombre del servicio *', 'la-perruqueria' ); ?></strong></label></th>
								<td>
									<input type="text" id="perruqueria_nombre" name="nombre" value="<?php echo $modo_edicion ? esc_attr( $servicio_a_editar['nombre'] ) : ''; ?>" class="regular-text" required>
								</td>
							</tr>
							<tr>
								<th><label for="perruqueria_categoria"><strong><?php esc_html_e( 'Categoría *', 'la-perruqueria' ); ?></strong></label></th>
								<td>
									<select id="perruqueria_categoria" name="categoria_id" class="regular-text" required>
										<option value=""><?php esc_html_e( '-- Selecciona una categoría --', 'la-perruqueria' ); ?></option>
										<?php foreach ( $categorias as $cat ) : ?>
											<?php
											$sel = ( $modo_edicion && (int) $servicio_a_editar['categoria_id'] === (int) $cat['id'] ) ? 'selected' : '';
											?>
											<option value="<?php echo esc_attr( $cat['id'] ); ?>" <?php echo $sel; ?>>
												<?php echo esc_html( $cat['nombre'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="perruqueria_duracion"><strong><?php esc_html_e( 'Duración (minutos) *', 'la-perruqueria' ); ?></strong></label></th>
								<td>
									<input type="number" id="perruqueria_duracion" name="duracion_min" min="1" step="1" value="<?php echo $modo_edicion ? esc_attr( $servicio_a_editar['duracion_min'] ) : '60'; ?>" class="small-text" required>
									<span class="description"><?php esc_html_e( 'Entero positivo mayor a cero (ej. 30, 45, 60).', 'la-perruqueria' ); ?></span>
								</td>
							</tr>
							<tr>
								<th><label for="perruqueria_precio"><strong><?php esc_html_e( 'Precio ($) *', 'la-perruqueria' ); ?></strong></label></th>
								<td>
									<input type="number" id="perruqueria_precio" name="precio" min="0" step="0.01" value="<?php echo $modo_edicion ? esc_attr( $servicio_a_editar['precio'] ) : '0.00'; ?>" class="regular-text" required>
									<span class="description"><?php esc_html_e( 'Mayor o igual a cero.', 'la-perruqueria' ); ?></span>
								</td>
							</tr>
							<tr>
								<th><label for="perruqueria_descripcion"><?php esc_html_e( 'Descripción', 'la-perruqueria' ); ?></label></th>
								<td>
									<textarea id="perruqueria_descripcion" name="descripcion" rows="4" class="large-text"><?php echo $modo_edicion ? esc_textarea( $servicio_a_editar['descripcion'] ) : ''; ?></textarea>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Disponibilidad', 'la-perruqueria' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="activo" value="1" <?php checked( $modo_edicion ? (int) $servicio_a_editar['activo'] : 1, 1 ); ?>>
										<?php esc_html_e( 'Servicio activo y visible en el catálogo de clientes', 'la-perruqueria' ); ?>
									</label>
								</td>
							</tr>
						</table>

						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php echo $modo_edicion ? esc_attr__( 'Guardar Cambios', 'la-perruqueria' ) : esc_attr__( 'Crear Servicio', 'la-perruqueria' ); ?>">
							<?php if ( $modo_edicion ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=la-perruqueria' ) ); ?>" class="button">
									<?php esc_html_e( 'Cancelar', 'la-perruqueria' ); ?>
								</a>
							<?php endif; ?>
						</p>
					</form>
				</div>
			</div>
		</div>

		<!-- Columna Listado de Servicios -->
		<div class="perruqueria-admin-col-list">
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Catálogo de Servicios Registrados', 'la-perruqueria' ); ?></h2>
				</div>
				<div class="inside" style="padding: 0;">
					<?php if ( empty( $servicios ) ) : ?>
						<p style="padding: 20px;"><?php esc_html_e( 'No hay servicios registrados todavía.', 'la-perruqueria' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th style="width: 50px;"><?php esc_html_e( 'ID', 'la-perruqueria' ); ?></th>
									<th><?php esc_html_e( 'Nombre', 'la-perruqueria' ); ?></th>
									<th><?php esc_html_e( 'Categoría', 'la-perruqueria' ); ?></th>
									<th style="width: 90px;"><?php esc_html_e( 'Duración', 'la-perruqueria' ); ?></th>
									<th style="width: 90px;"><?php esc_html_e( 'Precio', 'la-perruqueria' ); ?></th>
									<th style="width: 90px;"><?php esc_html_e( 'Estado', 'la-perruqueria' ); ?></th>
									<th style="width: 170px;"><?php esc_html_e( 'Acciones', 'la-perruqueria' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $servicios as $s ) : ?>
									<?php
									$es_activo      = ( (int) $s['activo'] === 1 );
									$url_editar     = add_query_arg( array( 'page' => 'la-perruqueria', 'editar_id' => $s['id'] ), admin_url( 'admin.php' ) );
									$url_alternar   = wp_nonce_url(
										add_query_arg(
											array(
												'page' => 'la-perruqueria',
												'perruqueria_admin_accion' => 'alternar_activo',
												'servicio_id' => $s['id'],
											),
											admin_url( 'admin.php' )
										),
										'perruqueria_alternar_' . $s['id']
									);
									$url_eliminar   = wp_nonce_url(
										add_query_arg(
											array(
												'page' => 'la-perruqueria',
												'perruqueria_admin_accion' => 'eliminar_servicio',
												'servicio_id' => $s['id'],
											),
											admin_url( 'admin.php' )
										),
										'perruqueria_eliminar_' . $s['id']
									);
									?>
									<tr>
										<td>#<?php echo esc_html( $s['id'] ); ?></td>
										<td>
											<strong><?php echo esc_html( $s['nombre'] ); ?></strong>
											<?php if ( ! empty( $s['descripcion'] ) ) : ?>
												<p class="description"><?php echo esc_html( wp_trim_words( $s['descripcion'], 12 ) ); ?></p>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $s['categoria_nombre'] ); ?></td>
										<td><?php printf( esc_html__( '%d min', 'la-perruqueria' ), (int) $s['duracion_min'] ); ?></td>
										<td>$<?php echo esc_html( number_format( (float) $s['precio'], 2 ) ); ?></td>
										<td>
											<span class="perruqueria-badge <?php echo $es_activo ? 'perruqueria-badge-activo' : 'perruqueria-badge-inactivo'; ?>">
												<?php echo $es_activo ? esc_html__( 'Activo', 'la-perruqueria' ) : esc_html__( 'Inactivo', 'la-perruqueria' ); ?>
											</span>
										</td>
										<td>
											<a href="<?php echo esc_url( $url_editar ); ?>" class="button button-small">
												<?php esc_html_e( 'Editar', 'la-perruqueria' ); ?>
											</a>

											<a href="<?php echo esc_url( $url_alternar ); ?>" class="button button-small">
												<?php echo $es_activo ? esc_html__( 'Desactivar', 'la-perruqueria' ) : esc_html__( 'Activar', 'la-perruqueria' ); ?>
											</a>

											<a href="<?php echo esc_url( $url_eliminar ); ?>" class="button button-small button-link-delete" onclick="return confirm('¿Confirmas que deseas eliminar este servicio? (Si tiene citas asociadas, el sistema impedirá su eliminación física).');">
												<?php esc_html_e( 'Borrar', 'la-perruqueria' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
