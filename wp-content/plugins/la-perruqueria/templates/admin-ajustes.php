<?php
/**
 * Pantalla de configuración de horarios y disponibilidad en wp-admin.
 *
 * @package La_Perruqueria
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Acceso no autorizado.', 'la-perruqueria' ) );
}
 
$ajustes = La_Perruqueria_Horarios::obtener_ajustes();
 
$dias_semana = array(
	1 => __( 'Lunes', 'la-perruqueria' ),
	2 => __( 'Martes', 'la-perruqueria' ),
	3 => __( 'Miércoles', 'la-perruqueria' ),
	4 => __( 'Jueves', 'la-perruqueria' ),
	5 => __( 'Viernes', 'la-perruqueria' ),
	6 => __( 'Sábado', 'la-perruqueria' ),
	7 => __( 'Domingo', 'la-perruqueria' ),
);
 
$fechas_no_lab_texto = implode( "\n", $ajustes['dias_no_laborables'] );
 
// Mensajes de confirmación o error vía GET
$mensaje_tipo  = isset( $_GET['mensaje_tipo'] ) ? sanitize_text_field( wp_unslash( $_GET['mensaje_tipo'] ) ) : '';
$mensaje_texto = isset( $_GET['mensaje_texto'] ) ? sanitize_text_field( wp_unslash( rawurldecode( $_GET['mensaje_texto'] ) ) ) : '';
?>
 
<div class="wrap perruqueria-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Ajustes de Horarios y Disponibilidad - La Perruquería', 'la-perruqueria' ); ?></h1>
	<hr class="wp-header-end">
 
	<?php if ( ! empty( $mensaje_texto ) ) : ?>
		<div class="notice notice-<?php echo ( 'error' === $mensaje_tipo ) ? 'error' : 'success'; ?> is-dismissible">
			<p><?php echo esc_html( $mensaje_texto ); ?></p>
		</div>
	<?php endif; ?>
 
	<div class="postbox" style="max-width: 850px; margin-top: 15px;">
		<div class="postbox-header">
			<h2><?php esc_html_e( 'Configuración Comercial del Establecimiento', 'la-perruqueria' ); ?></h2>
		</div>
		<div class="inside" style="padding: 20px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=la-perruqueria-ajustes' ) ); ?>">
				<?php wp_nonce_field( 'perruqueria_guardar_ajustes_accion', 'perruqueria_ajustes_nonce' ); ?>
				<input type="hidden" name="perruqueria_admin_accion" value="guardar_ajustes_horarios">
 
				<table class="form-table">
					<!-- Días de atención -->
					<tr>
						<th scope="row"><strong><?php esc_html_e( 'Días de atención *', 'la-perruqueria' ); ?></strong></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Días de atención', 'la-perruqueria' ); ?></legend>
								<?php foreach ( $dias_semana as $num_dia => $nombre_dia ) : ?>
									<label style="display: inline-block; margin-right: 15px; margin-bottom: 8px;">
										<input type="checkbox" name="dias_laborales[]" value="<?php echo esc_attr( $num_dia ); ?>" <?php checked( in_array( $num_dia, $ajustes['dias_laborales'], true ) ); ?>>
										<?php echo esc_html( $nombre_dia ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description">
									<?php esc_html_e( 'Por defecto: lunes a sábado. Los días desmarcados no generarán turnos.', 'la-perruqueria' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>
 
					<!-- Horario de atención -->
					<tr>
						<th scope="row"><strong><?php esc_html_e( 'Horario de atención *', 'la-perruqueria' ); ?></strong></th>
						<td>
							<label for="hora_apertura" style="margin-right: 6px;"><?php esc_html_e( 'Apertura:', 'la-perruqueria' ); ?></label>
							<input type="time" id="hora_apertura" name="hora_apertura" value="<?php echo esc_attr( $ajustes['hora_apertura'] ); ?>" required>
 
							<span style="margin: 0 10px;">—</span>
 
							<label for="hora_cierre" style="margin-right: 6px;"><?php esc_html_e( 'Cierre:', 'la-perruqueria' ); ?></label>
							<input type="time" id="hora_cierre" name="hora_cierre" value="<?php echo esc_attr( $ajustes['hora_cierre'] ); ?>" required>
 
							<p class="description">
								<?php esc_html_e( 'Por defecto: 09:00 a 18:00. Ningún turno podrá terminar después de la hora de cierre.', 'la-perruqueria' ); ?>
							</p>
						</td>
					</tr>
 
					<!-- Intervalo entre turnos -->
					<tr>
						<th scope="row"><label for="intervalo_min"><strong><?php esc_html_e( 'Intervalo entre turnos *', 'la-perruqueria' ); ?></strong></label></th>
						<td>
							<select id="intervalo_min" name="intervalo_min">
								<option value="15" <?php selected( (int) $ajustes['intervalo_min'], 15 ); ?>><?php esc_html_e( '15 minutos', 'la-perruqueria' ); ?></option>
								<option value="30" <?php selected( (int) $ajustes['intervalo_min'], 30 ); ?>><?php esc_html_e( '30 minutos (recomendado)', 'la-perruqueria' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Define el paso de la cuadrícula horaria de inicio (15 o 30 minutos).', 'la-perruqueria' ); ?>
							</p>
						</td>
					</tr>
 
					<!-- Anticipación para reservar -->
					<tr>
						<th scope="row"><label for="anticipacion_reserva"><strong><?php esc_html_e( 'Anticipación para reservar *', 'la-perruqueria' ); ?></strong></label></th>
						<td>
							<input type="number" id="anticipacion_reserva" name="anticipacion_reserva" min="0" max="72" value="<?php echo esc_attr( $ajustes['anticipacion_reserva'] ); ?>" class="small-text" required>
							<span><?php esc_html_e( 'horas', 'la-perruqueria' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Horas de antelación requeridas para que un cliente pueda agendar (por defecto: 2 horas).', 'la-perruqueria' ); ?>
							</p>
						</td>
					</tr>
 
					<!-- Anticipación para cancelar -->
					<tr>
						<th scope="row"><label for="anticipacion_cancelar"><strong><?php esc_html_e( 'Anticipación para cancelar *', 'la-perruqueria' ); ?></strong></label></th>
						<td>
							<input type="number" id="anticipacion_cancelar" name="anticipacion_cancelar" min="0" max="72" value="<?php echo esc_attr( $ajustes['anticipacion_cancelar'] ); ?>" class="small-text" required>
							<span><?php esc_html_e( 'horas', 'la-perruqueria' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Horas mínimas de antelación para que un cliente pueda cancelar una cita propia (por defecto: 2 horas).', 'la-perruqueria' ); ?>
							</p>
						</td>
					</tr>
 
					<!-- Máximo de días de antelación para reservar -->
					<tr>
						<th scope="row"><label for="max_dias_reserva"><strong><?php esc_html_e( 'Máximo de días para reservar *', 'la-perruqueria' ); ?></strong></label></th>
						<td>
							<input type="number" id="max_dias_reserva" name="max_dias_reserva" min="1" max="365" value="<?php echo esc_attr( $ajustes['max_dias_reserva'] ); ?>" class="small-text" required>
							<span><?php esc_html_e( 'días', 'la-perruqueria' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Hasta cuántos días en el futuro puede reservar un cliente (por defecto: 60 días).', 'la-perruqueria' ); ?>
							</p>
						</td>
					</tr>
 
					<!-- Días no laborables -->
					<tr>
						<th scope="row"><label for="dias_no_laborables"><strong><?php esc_html_e( 'Días no laborables / Feriados', 'la-perruqueria' ); ?></strong></label></th>
						<td>
							<textarea id="dias_no_laborables" name="dias_no_laborables" rows="4" class="large-text" placeholder="2026-12-25&#10;2026-01-01"><?php echo esc_textarea( $fechas_no_lab_texto ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Fechas en formato YYYY-MM-DD (una por línea o separadas por comas). En estas fechas no se ofrecerán turnos.', 'la-perruqueria' ); ?>
							</p>
						</td>
					</tr>
				</table>
 
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar Ajustes de Horarios', 'la-perruqueria' ); ?>">
				</p>
			</form>
		</div>
	</div>
</div>
 