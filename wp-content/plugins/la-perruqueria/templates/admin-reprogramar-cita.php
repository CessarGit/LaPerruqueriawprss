<?php
/**
 * Pantalla del personal en wp-admin: reprogramar una cita eligiendo fecha y turno disponible.
 *
 * Variables recibidas desde La_Perruqueria_Panel_Admin::render_pantalla_reprogramar():
 * $cita_id, $cita (array|null), $cliente (WP_User|false), $fecha (string), $turnos (array|null), $error (string).
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Acceso no autorizado.', 'la-perruqueria' ) );
}

$url_listado = admin_url( 'admin.php?page=la-perruqueria-citas' );
$tz_sitio    = wp_timezone();
$hoy         = ( new DateTimeImmutable( 'now', $tz_sitio ) )->format( 'Y-m-d' );

$fecha_actual_txt = '';
$hora_actual_txt  = '';
if ( $cita ) {
	$dt_inicio = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $cita['inicio'], $tz_sitio );
	$dt_fin    = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $cita['fin'], $tz_sitio );
	if ( $dt_inicio ) {
		$fecha_actual_txt = wp_date( 'd/m/Y', $dt_inicio->getTimestamp() );
		$hora_actual_txt  = wp_date( 'H:i', $dt_inicio->getTimestamp() );
	}
	if ( $dt_fin ) {
		$hora_actual_txt .= ' - ' . wp_date( 'H:i', $dt_fin->getTimestamp() );
	}
}
?>

<div class="wrap perruqueria-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Reprogramar cita - La Perruquería', 'la-perruqueria' ); ?></h1>
	<a href="<?php echo esc_url( $url_listado ); ?>" class="page-title-action"><?php esc_html_e( 'Volver al listado', 'la-perruqueria' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( '' !== $error ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $cita ) : ?>
		<div class="postbox" style="max-width: 850px; margin-top: 15px;">
			<div class="postbox-header">
				<h2><?php printf( /* translators: %d: ID de la cita */ esc_html__( 'Cita #%d', 'la-perruqueria' ), (int) $cita['id'] ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Cliente', 'la-perruqueria' ); ?></th>
						<td><?php echo $cliente ? esc_html( $cliente->display_name . ' (' . $cliente->user_email . ')' ) : '&mdash;'; ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mascota', 'la-perruqueria' ); ?></th>
						<td><?php echo esc_html( $cita['mascota_nombre'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Servicio', 'la-perruqueria' ); ?></th>
						<td>
							<?php echo esc_html( $cita['servicio_nombre'] ); ?>
							(<?php printf( esc_html__( '%d min', 'la-perruqueria' ), (int) $cita['servicio_duracion'] ); ?>)
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Horario actual', 'la-perruqueria' ); ?></th>
						<td>
							<?php echo esc_html( $fecha_actual_txt . ' · ' . $hora_actual_txt ); ?>
							&mdash; <strong><?php echo esc_html( ucfirst( $cita['estado'] ) ); ?></strong>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<?php if ( '' === $error || null !== $turnos ) : ?>
			<?php if ( La_Perruqueria_Reservas::validar_transicion( $cita['estado'], 'reprogramada' ) ) : ?>

				<!-- Paso 1: elegir la fecha (consulta de solo lectura) -->
				<div class="postbox" style="max-width: 850px; margin-top: 15px;">
					<div class="postbox-header">
						<h2><?php esc_html_e( '1. Elige la nueva fecha', 'la-perruqueria' ); ?></h2>
					</div>
					<div class="inside">
						<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
							<input type="hidden" name="page" value="la-perruqueria-citas">
							<input type="hidden" name="reprogramar" value="<?php echo esc_attr( $cita['id'] ); ?>">
							<label for="perruqueria_fecha_nueva"><?php esc_html_e( 'Fecha', 'la-perruqueria' ); ?></label>
							<input type="date" id="perruqueria_fecha_nueva" name="fecha" value="<?php echo esc_attr( $fecha ); ?>" min="<?php echo esc_attr( $hoy ); ?>" required>
							<input type="submit" class="button" value="<?php esc_attr_e( 'Ver turnos disponibles', 'la-perruqueria' ); ?>">
						</form>
					</div>
				</div>

				<!-- Paso 2: elegir el turno y confirmar -->
				<?php if ( null !== $turnos ) : ?>
					<div class="postbox" style="max-width: 850px; margin-top: 15px;">
						<div class="postbox-header">
							<h2>
								<?php
								$dt_fecha_sel = DateTimeImmutable::createFromFormat( 'Y-m-d', $fecha, $tz_sitio );
								printf(
									/* translators: %s: fecha elegida */
									esc_html__( '2. Turnos disponibles el %s', 'la-perruqueria' ),
									esc_html( $dt_fecha_sel ? wp_date( 'd/m/Y', $dt_fecha_sel->getTimestamp() ) : $fecha )
								);
								?>
							</h2>
						</div>
						<div class="inside">
							<?php if ( empty( $turnos ) ) : ?>
								<p><?php esc_html_e( 'No hay turnos disponibles para esa fecha. Prueba con otro día.', 'la-perruqueria' ); ?></p>
							<?php else : ?>
								<form method="post" action="<?php echo esc_url( $url_listado ); ?>">
									<input type="hidden" name="perruqueria_cita_nonce" value="<?php echo esc_attr( wp_create_nonce( 'perruqueria_cita_reprogramar_' . $cita['id'] ) ); ?>">
									<input type="hidden" name="perruqueria_admin_accion" value="reprogramar_cita">
									<input type="hidden" name="cita_id" value="<?php echo esc_attr( $cita['id'] ); ?>">
									<input type="hidden" name="redirect_to" value="<?php echo esc_url( $url_listado ); ?>">

									<fieldset class="perruqueria-turnos-admin">
										<legend class="screen-reader-text"><?php esc_html_e( 'Turnos disponibles', 'la-perruqueria' ); ?></legend>
										<?php foreach ( $turnos as $turno ) : ?>
											<label class="perruqueria-turno-admin">
												<input type="radio" name="inicio" value="<?php echo esc_attr( $turno['inicio'] ); ?>" required>
												<?php echo esc_html( $turno['hora'] ); ?>
											</label>
										<?php endforeach; ?>
									</fieldset>

									<p class="description">
										<?php esc_html_e( 'Al confirmar, la cita pasará a estado "Reprogramada". Para dejarla firme, confírmala después desde el listado.', 'la-perruqueria' ); ?>
									</p>

									<p class="submit">
										<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Reprogramar cita', 'la-perruqueria' ); ?>">
										<a href="<?php echo esc_url( $url_listado ); ?>" class="button"><?php esc_html_e( 'Cancelar', 'la-perruqueria' ); ?></a>
									</p>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
