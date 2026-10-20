<?php
/**
 * Plantilla de gestión de mascotas en frontend para el cliente.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cliente_id = La_Perruqueria_Seguridad::obtener_cliente_actual_id();
$mensajes   = La_Perruqueria_Mascotas::obtener_mensajes();

// Verificar si se solicitó editar una mascota específica
$modo_edicion     = false;
$mascota_a_editar = null;

if ( isset( $_GET['editar'] ) ) {
	$id_ed = absint( $_GET['editar'] );
	if ( La_Perruqueria_Seguridad::cliente_es_dueno_mascota( $id_ed, $cliente_id ) ) {
		$mascota_a_editar = La_Perruqueria_Mascotas::obtener_mascota( $id_ed, $cliente_id );
		if ( $mascota_a_editar ) {
			$modo_edicion = true;
		}
	}
}

$mascotas = La_Perruqueria_Mascotas::listar_mascotas( $cliente_id );
?>

<div class="perruqueria-mascotas-wrap">
	<!-- Alertas y mensajes -->
	<?php if ( ! empty( $mensajes['error'] ) ) : ?>
		<div class="perruqueria-alert perruqueria-alert-error" role="alert">
			<?php foreach ( $mensajes['error'] as $err ) : ?>
				<p><?php echo esc_html( $err ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $mensajes['exito'] ) ) : ?>
		<div class="perruqueria-alert perruqueria-alert-success" role="alert">
			<?php foreach ( $mensajes['exito'] as $msg ) : ?>
				<p><?php echo esc_html( $msg ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="perruqueria-grid perruqueria-grid-form-list">
		<!-- Formulario de Creación / Edición -->
		<div class="perruqueria-card">
			<h3>
				<?php echo $modo_edicion ? esc_html__( 'Editar Mascota', 'la-perruqueria' ) : esc_html__( 'Registrar Nueva Mascota', 'la-perruqueria' ); ?>
			</h3>

			<form method="post" action="<?php echo esc_url( remove_query_arg( 'editar' ) ); ?>" class="perruqueria-form">
				<?php wp_nonce_field( 'perruqueria_mascota_formulario', 'perruqueria_mascota_nonce' ); ?>
				<input type="hidden" name="perruqueria_mascota_accion" value="<?php echo $modo_edicion ? 'editar' : 'crear'; ?>">
				<?php if ( $modo_edicion ) : ?>
					<input type="hidden" name="mascota_id" value="<?php echo esc_attr( $mascota_a_editar['id'] ); ?>">
				<?php endif; ?>

				<div class="perruqueria-field">
					<label for="mascota_nombre"><?php esc_html_e( 'Nombre de la mascota *', 'la-perruqueria' ); ?></label>
					<input type="text" id="mascota_nombre" name="nombre" value="<?php echo $modo_edicion ? esc_attr( $mascota_a_editar['nombre'] ) : ''; ?>" required class="perruqueria-input">
				</div>

				<div class="perruqueria-field">
					<label for="mascota_especie"><?php esc_html_e( 'Especie *', 'la-perruqueria' ); ?></label>
					<input type="text" id="mascota_especie" name="especie" value="<?php echo $modo_edicion ? esc_attr( $mascota_a_editar['especie'] ) : 'Perro'; ?>" required class="perruqueria-input">
				</div>

				<div class="perruqueria-field">
					<label for="mascota_raza"><?php esc_html_e( 'Raza', 'la-perruqueria' ); ?></label>
					<input type="text" id="mascota_raza" name="raza" value="<?php echo $modo_edicion ? esc_attr( $mascota_a_editar['raza'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Ej: Labrador, Caniche, Mestizo...', 'la-perruqueria' ); ?>" class="perruqueria-input">
				</div>

				<div class="perruqueria-field">
					<label for="mascota_tamano"><?php esc_html_e( 'Tamaño *', 'la-perruqueria' ); ?></label>
					<?php $tam_actual = $modo_edicion ? $mascota_a_editar['tamano'] : 'mediano'; ?>
					<select id="mascota_tamano" name="tamano" required class="perruqueria-input">
						<option value="pequeno" <?php selected( $tam_actual, 'pequeno' ); ?>><?php esc_html_e( 'Pequeño', 'la-perruqueria' ); ?></option>
						<option value="mediano" <?php selected( $tam_actual, 'mediano' ); ?>><?php esc_html_e( 'Mediano', 'la-perruqueria' ); ?></option>
						<option value="grande" <?php selected( $tam_actual, 'grande' ); ?>><?php esc_html_e( 'Grande', 'la-perruqueria' ); ?></option>
					</select>
				</div>

				<div class="perruqueria-field">
					<label for="mascota_edad"><?php esc_html_e( 'Edad aproximada (años)', 'la-perruqueria' ); ?></label>
					<input type="number" id="mascota_edad" name="edad_anios" min="0" max="30" value="<?php echo ( $modo_edicion && null !== $mascota_a_editar['edad_anios'] ) ? esc_attr( $mascota_a_editar['edad_anios'] ) : ''; ?>" class="perruqueria-input">
				</div>

				<div class="perruqueria-field">
					<label for="mascota_observaciones"><?php esc_html_e( 'Observaciones o cuidados especiales', 'la-perruqueria' ); ?></label>
					<textarea id="mascota_observaciones" name="observaciones" rows="3" class="perruqueria-input" placeholder="<?php esc_attr_e( 'Temperamento, alergias, sensibilidad en la piel...', 'la-perruqueria' ); ?>"><?php echo $modo_edicion ? esc_textarea( $mascota_a_editar['observaciones'] ) : ''; ?></textarea>
				</div>

				<div class="perruqueria-actions">
					<button type="submit" class="perruqueria-btn perruqueria-btn-primary">
						<?php echo $modo_edicion ? esc_html__( 'Guardar Cambios', 'la-perruqueria' ) : esc_html__( 'Registrar Mascota', 'la-perruqueria' ); ?>
					</button>

					<?php if ( $modo_edicion ) : ?>
						<a href="<?php echo esc_url( remove_query_arg( 'editar' ) ); ?>" class="perruqueria-btn perruqueria-btn-link">
							<?php esc_html_e( 'Cancelar edición', 'la-perruqueria' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<!-- Listado de Mascotas del Cliente -->
		<div class="perruqueria-card">
			<h3><?php esc_html_e( 'Mis Mascotas', 'la-perruqueria' ); ?></h3>

			<?php if ( empty( $mascotas ) ) : ?>
				<p class="perruqueria-empty-text">
					<?php esc_html_e( 'Aún no tienes mascotas registradas. Agrega tu primera mascota en el formulario.', 'la-perruqueria' ); ?>
				</p>
			<?php else : ?>
				<div class="perruqueria-mascotas-lista">
					<?php foreach ( $mascotas as $m ) : ?>
						<div class="perruqueria-mascota-item">
							<div class="perruqueria-mascota-info">
								<h4 class="perruqueria-mascota-nombre"><?php echo esc_html( $m['nombre'] ); ?></h4>
								<span class="perruqueria-badge perruqueria-badge-tamano">
									<?php echo esc_html( ucfirst( $m['tamano'] ) ); ?>
								</span>
								<p class="perruqueria-mascota-detalles">
									<strong><?php esc_html_e( 'Especie:', 'la-perruqueria' ); ?></strong> <?php echo esc_html( $m['especie'] ); ?>
									<?php if ( ! empty( $m['raza'] ) ) : ?>
										| <strong><?php esc_html_e( 'Raza:', 'la-perruqueria' ); ?></strong> <?php echo esc_html( $m['raza'] ); ?>
									<?php endif; ?>
									<?php if ( null !== $m['edad_anios'] && '' !== $m['edad_anios'] ) : ?>
										| <strong><?php esc_html_e( 'Edad:', 'la-perruqueria' ); ?></strong> <?php printf( esc_html__( '%d años', 'la-perruqueria' ), (int) $m['edad_anios'] ); ?>
									<?php endif; ?>
								</p>
								<?php if ( ! empty( $m['observaciones'] ) ) : ?>
									<p class="perruqueria-mascota-obs">
										<em><?php echo esc_html( $m['observaciones'] ); ?></em>
									</p>
								<?php endif; ?>
							</div>

							<div class="perruqueria-mascota-acciones">
								<a href="<?php echo esc_url( add_query_arg( 'editar', $m['id'] ) ); ?>" class="perruqueria-btn-sm perruqueria-btn-secondary">
									<?php esc_html_e( 'Editar', 'la-perruqueria' ); ?>
								</a>

								<form method="post" action="" style="display:inline-block;" onsubmit="return confirm('¿Confirmas que deseas eliminar esta mascota?');">
									<?php wp_nonce_field( 'perruqueria_mascota_formulario', 'perruqueria_mascota_nonce' ); ?>
									<input type="hidden" name="perruqueria_mascota_accion" value="desactivar">
									<input type="hidden" name="mascota_id" value="<?php echo esc_attr( $m['id'] ); ?>">
									<button type="submit" class="perruqueria-btn-sm perruqueria-btn-danger">
										<?php esc_html_e( 'Eliminar', 'la-perruqueria' ); ?>
									</button>
								</form>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
