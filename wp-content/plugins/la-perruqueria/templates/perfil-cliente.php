<?php
/**
 * Plantilla del perfil del cliente en frontend: datos de contacto e historial de citas.
 *
 * Variables recibidas desde La_Perruqueria_Perfil::render_shortcode():
 * $usuario, $avisos, $telefono, $destino, $citas, $total_citas, $pagina, $paginas, $horas_cancelar.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$etiquetas_estado = array(
	'pendiente'    => __( 'Pendiente', 'la-perruqueria' ),
	'confirmada'   => __( 'Confirmada', 'la-perruqueria' ),
	'reprogramada' => __( 'Reprogramada', 'la-perruqueria' ),
	'cancelada'    => __( 'Cancelada', 'la-perruqueria' ),
);
$tz_sitio = wp_timezone();
?>

<div class="perruqueria-mascotas-wrap perruqueria-perfil-wrap">

	<?php if ( ! empty( $avisos['error'] ) ) : ?>
		<div class="perruqueria-alert perruqueria-alert-error" role="alert">
			<?php foreach ( $avisos['error'] as $err ) : ?>
				<p><?php echo esc_html( $err ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $avisos['exito'] ) ) : ?>
		<div class="perruqueria-alert perruqueria-alert-success" role="alert">
			<?php foreach ( $avisos['exito'] as $msg ) : ?>
				<p><?php echo esc_html( $msg ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="perruqueria-card">
		<h3><?php esc_html_e( 'Mi perfil', 'la-perruqueria' ); ?></h3>

		<form method="post" action="<?php echo esc_url( $destino ); ?>" class="perruqueria-form">
			<?php wp_nonce_field( 'perruqueria_perfil_formulario', 'perruqueria_perfil_nonce' ); ?>
			<input type="hidden" name="perruqueria_perfil_accion" value="guardar">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $destino ); ?>">

			<div class="perruqueria-field">
				<label for="perfil_usuario"><?php esc_html_e( 'Nombre de usuario', 'la-perruqueria' ); ?></label>
				<input type="text" id="perfil_usuario" value="<?php echo esc_attr( $usuario->user_login ); ?>" class="perruqueria-input" disabled>
			</div>

			<div class="perruqueria-field">
				<label for="perfil_nombre"><?php esc_html_e( 'Nombre *', 'la-perruqueria' ); ?></label>
				<input type="text" id="perfil_nombre" name="nombre" value="<?php echo esc_attr( $usuario->display_name ); ?>" maxlength="100" required class="perruqueria-input" autocomplete="name">
			</div>

			<div class="perruqueria-field">
				<label for="perfil_correo"><?php esc_html_e( 'Correo electrónico *', 'la-perruqueria' ); ?></label>
				<input type="email" id="perfil_correo" name="correo" value="<?php echo esc_attr( $usuario->user_email ); ?>" required class="perruqueria-input" autocomplete="email">
			</div>

			<div class="perruqueria-field">
				<label for="perfil_telefono"><?php esc_html_e( 'Teléfono', 'la-perruqueria' ); ?></label>
				<input type="tel" id="perfil_telefono" name="telefono" value="<?php echo esc_attr( $telefono ); ?>" maxlength="20" class="perruqueria-input" autocomplete="tel" placeholder="<?php esc_attr_e( 'Ej: 7000 0000', 'la-perruqueria' ); ?>">
			</div>

			<div class="perruqueria-field">
				<label for="perfil_password"><?php esc_html_e( 'Contraseña actual (solo si cambias el correo)', 'la-perruqueria' ); ?></label>
				<input type="password" id="perfil_password" name="password_actual" class="perruqueria-input" autocomplete="current-password">
			</div>

			<div class="perruqueria-actions">
				<button type="submit" class="perruqueria-btn perruqueria-btn-primary">
					<?php esc_html_e( 'Guardar cambios', 'la-perruqueria' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Historial de citas -->
	<div class="perruqueria-card perruqueria-historial-card" id="perruqueria-historial-card">
		<h3><?php esc_html_e( 'Mis citas', 'la-perruqueria' ); ?></h3>

		<div id="perruqueria-historial-aviso" class="perruqueria-alerta" style="display:none;" role="alert"></div>

		<?php if ( empty( $citas ) ) : ?>
			<p class="perruqueria-empty-text">
				<?php esc_html_e( 'Aún no tienes citas registradas.', 'la-perruqueria' ); ?>
			</p>
		<?php else : ?>
			<div id="perruqueria-historial" class="perruqueria-citas-lista">
				<?php foreach ( $citas as $cita ) : ?>
					<?php
					$dt_inicio = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $cita['inicio'], $tz_sitio );
					$dt_fin    = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $cita['fin'], $tz_sitio );
					$fecha_txt = $dt_inicio ? wp_date( 'd/m/Y', $dt_inicio->getTimestamp() ) : $cita['inicio'];
					$hora_txt  = $dt_inicio ? wp_date( 'H:i', $dt_inicio->getTimestamp() ) : '';
					if ( $dt_fin ) {
						$hora_txt .= ' - ' . wp_date( 'H:i', $dt_fin->getTimestamp() );
					}
					$estado_clave = isset( $etiquetas_estado[ $cita['estado'] ] ) ? $cita['estado'] : 'pendiente';
					$cancelacion  = La_Perruqueria_Perfil::estado_cancelacion( $cita );
					?>
					<div class="perruqueria-cita-item" data-cita-fila="<?php echo esc_attr( $cita['id'] ); ?>">
						<div class="perruqueria-cita-info">
							<h4 class="perruqueria-cita-servicio">
								<?php echo esc_html( $cita['servicio_nombre'] ); ?>
								<span class="perruqueria-estado perruqueria-estado-<?php echo esc_attr( $estado_clave ); ?>">
									<?php echo esc_html( $etiquetas_estado[ $estado_clave ] ); ?>
								</span>
							</h4>
							<p class="perruqueria-cita-detalles">
								<strong><?php esc_html_e( 'Mascota:', 'la-perruqueria' ); ?></strong> <?php echo esc_html( $cita['mascota_nombre'] ); ?>
								| <strong><?php esc_html_e( 'Fecha:', 'la-perruqueria' ); ?></strong> <?php echo esc_html( $fecha_txt ); ?>
								| <strong><?php esc_html_e( 'Hora:', 'la-perruqueria' ); ?></strong> <?php echo esc_html( $hora_txt ); ?>
								| <strong><?php esc_html_e( 'Precio:', 'la-perruqueria' ); ?></strong> $<?php echo esc_html( number_format_i18n( (float) $cita['servicio_precio'], 2 ) ); ?>
							</p>
							<?php if ( ! empty( $cita['notas'] ) ) : ?>
								<p class="perruqueria-cita-notas"><em><?php echo esc_html( $cita['notas'] ); ?></em></p>
							<?php endif; ?>
						</div>

						<?php if ( 'si' === $cancelacion ) : ?>
							<div class="perruqueria-cita-acciones">
								<button type="button" class="perruqueria-btn-sm perruqueria-btn-danger perruqueria-btn-cancelar-cita" data-cita-id="<?php echo esc_attr( $cita['id'] ); ?>">
									<?php esc_html_e( 'Cancelar cita', 'la-perruqueria' ); ?>
								</button>
							</div>
						<?php elseif ( 'plazo' === $cancelacion ) : ?>
							<div class="perruqueria-cita-acciones">
								<p class="perruqueria-cita-aviso-plazo">
									<?php
									printf(
										/* translators: %d: horas mínimas de anticipación para cancelar */
										esc_html__( 'Faltan menos de %d hora(s) para esta cita. Para cancelarla, comunícate directamente con el establecimiento.', 'la-perruqueria' ),
										(int) $horas_cancelar
									);
									?>
								</p>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $paginas > 1 ) : ?>
				<nav class="perruqueria-paginacion" aria-label="<?php esc_attr_e( 'Paginación del historial de citas', 'la-perruqueria' ); ?>">
					<?php if ( $pagina > 1 ) : ?>
						<a class="perruqueria-btn-sm perruqueria-btn-secondary" href="<?php echo esc_url( add_query_arg( 'pagina_citas', $pagina - 1, $destino ) . '#perruqueria-historial-card' ); ?>">
							&laquo; <?php esc_html_e( 'Anterior', 'la-perruqueria' ); ?>
						</a>
					<?php endif; ?>

					<span class="perruqueria-paginacion-texto">
						<?php
						printf(
							/* translators: 1: página actual, 2: total de páginas */
							esc_html__( 'Página %1$d de %2$d', 'la-perruqueria' ),
							(int) $pagina,
							(int) $paginas
						);
						?>
					</span>

					<?php if ( $pagina < $paginas ) : ?>
						<a class="perruqueria-btn-sm perruqueria-btn-secondary" href="<?php echo esc_url( add_query_arg( 'pagina_citas', $pagina + 1, $destino ) . '#perruqueria-historial-card' ); ?>">
							<?php esc_html_e( 'Siguiente', 'la-perruqueria' ); ?> &raquo;
						</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
