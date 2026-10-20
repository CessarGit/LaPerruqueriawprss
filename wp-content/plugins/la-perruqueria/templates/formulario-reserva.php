<?php
/**
 * Vista de formulario de reserva de citas para clientes.
 *
 * Variables disponibles:
 * - $mascotas: array con las mascotas activas del cliente.
 * - $servicios: array con los servicios activos disponibles.
 * - $citas_activas: int cantidad de citas activas futuras.
 * - $limite_max: int límite máximo de citas permitidas (3).
 * - $limite_alcanzo: bool si el cliente ya alcanzó el límite.
 * - $hoy: string fecha actual YYYY-MM-DD según wp_timezone().
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="perruqueria-contenedor perruqueria-reserva-contenedor">
	<div class="perruqueria-card perruqueria-reserva-card">
		<div class="perruqueria-card-header">
			<h2 class="perruqueria-titulo"><?php esc_html_e( 'Reserva de Cita', 'la-perruqueria' ); ?></h2>
			<p class="perruqueria-subtitulo">
				<?php esc_html_e( 'Selecciona a tu mascota, el servicio deseado y elige la fecha y hora que mejor te acomode.', 'la-perruqueria' ); ?>
			</p>
		</div>

		<?php if ( $limite_alcanzo ) : ?>
			<div class="perruqueria-alerta perruqueria-alerta-aviso">
				<strong><?php esc_html_e( 'Límite de citas alcanzado', 'la-perruqueria' ); ?></strong>
				<p>
					<?php
					printf(
						/* translators: %d: límite máximo de citas */
						esc_html__( 'Actualmente tienes %1$d cita(s) activa(s) programada(s). Por política de atención, el máximo permitido es de %2$d citas simultáneas por cliente. Podrás agendar un nuevo turno una vez que se complete o cancele alguna de tus citas actuales.', 'la-perruqueria' ),
						esc_html( $citas_activas ),
						esc_html( $limite_max )
					);
					?>
				</p>
			</div>

		<?php elseif ( empty( $mascotas ) ) : ?>
			<div class="perruqueria-alerta perruqueria-alerta-aviso">
				<strong><?php esc_html_e( 'No tienes mascotas registradas', 'la-perruqueria' ); ?></strong>
				<p>
					<?php esc_html_e( 'Para agendar una cita necesitas tener al menos una mascota registrada y activa en tu perfil.', 'la-perruqueria' ); ?>
				</p>
			</div>

		<?php elseif ( empty( $servicios ) ) : ?>
			<div class="perruqueria-alerta perruqueria-alerta-aviso">
				<strong><?php esc_html_e( 'Servicios no disponibles', 'la-perruqueria' ); ?></strong>
				<p>
					<?php esc_html_e( 'En este momento no hay servicios disponibles para reserva. Por favor, consulta más tarde.', 'la-perruqueria' ); ?>
				</p>
			</div>

		<?php else : ?>

			<form id="perruqueria-form-reserva" class="perruqueria-form perruqueria-booking-form" novalidate>
				<!-- 1. Selección de Mascota -->
				<div class="perruqueria-campo">
					<label for="perruqueria-reserva-mascota" class="perruqueria-label">
						<?php esc_html_e( 'Mascota:', 'la-perruqueria' ); ?> <span class="perruqueria-requerido">*</span>
					</label>
					<select id="perruqueria-reserva-mascota" name="mascota_id" class="perruqueria-input" required>
						<option value=""><?php esc_html_e( '-- Selecciona tu mascota --', 'la-perruqueria' ); ?></option>
						<?php foreach ( $mascotas as $m ) : ?>
							<option value="<?php echo esc_attr( $m['id'] ); ?>">
								<?php
								$detalle_raza = ! empty( $m['raza'] ) ? ' - ' . $m['raza'] : '';
								echo esc_html( sprintf( '%s (%s%s)', $m['nombre'], ucfirst( $m['especie'] ), $detalle_raza ) );
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- 2. Selección de Servicio -->
				<div class="perruqueria-campo">
					<label for="perruqueria-reserva-servicio" class="perruqueria-label">
						<?php esc_html_e( 'Servicio:', 'la-perruqueria' ); ?> <span class="perruqueria-requerido">*</span>
					</label>
					<select id="perruqueria-reserva-servicio" name="servicio_id" class="perruqueria-input" required>
						<option value=""><?php esc_html_e( '-- Selecciona el servicio --', 'la-perruqueria' ); ?></option>
						<?php foreach ( $servicios as $s ) : ?>
							<option value="<?php echo esc_attr( $s['id'] ); ?>" data-duracion="<?php echo esc_attr( $s['duracion_min'] ); ?>" data-precio="<?php echo esc_attr( $s['precio'] ); ?>">
								<?php
								echo esc_html(
									sprintf(
										'%1$s — %2$d min — $%3$s',
										$s['nombre'],
										absint( $s['duracion_min'] ),
										number_format_i18n( (float) $s['precio'], 2 )
									)
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- 3. Selección de Fecha -->
				<div class="perruqueria-campo">
					<label for="perruqueria-reserva-fecha" class="perruqueria-label">
						<?php esc_html_e( 'Fecha deseada:', 'la-perruqueria' ); ?> <span class="perruqueria-requerido">*</span>
					</label>
					<input
						type="date"
						id="perruqueria-reserva-fecha"
						name="fecha"
						class="perruqueria-input"
						min="<?php echo esc_attr( $hoy ); ?>"
						value="<?php echo esc_attr( $hoy ); ?>"
						required
					/>
				</div>

				<!-- 4. Contenedor de Turnos Disponibles -->
				<div id="perruqueria-seccion-turnos" class="perruqueria-campo perruqueria-turnos-wrapper">
					<label class="perruqueria-label">
						<?php esc_html_e( 'Turnos disponibles:', 'la-perruqueria' ); ?> <span class="perruqueria-requerido">*</span>
					</label>
					<div id="perruqueria-turnos-feedback" class="perruqueria-turnos-info">
						<?php esc_html_e( 'Selecciona un servicio y una fecha para ver los horarios disponibles.', 'la-perruqueria' ); ?>
					</div>
					<div id="perruqueria-slots-grid" class="perruqueria-slots-grid" style="display:none;"></div>
					<input type="hidden" id="perruqueria-reserva-inicio" name="inicio" value="" required />
				</div>

				<!-- 5. Notas opcionales -->
				<div class="perruqueria-campo">
					<label for="perruqueria-reserva-notas" class="perruqueria-label">
						<?php esc_html_e( 'Notas o cuidados especiales (opcional):', 'la-perruqueria' ); ?>
					</label>
					<textarea
						id="perruqueria-reserva-notas"
						name="notas"
						rows="3"
						class="perruqueria-textarea"
						placeholder="<?php esc_attr_e( 'Ejemplo: sensible a ruidos fuertes, alergia a ciertos champús, corte específico...', 'la-perruqueria' ); ?>"
					></textarea>
				</div>

				<!-- Mensaje de respuesta y alertas -->
				<div id="perruqueria-mensaje-reserva" class="perruqueria-alerta" style="display:none;" role="alert"></div>

				<!-- Botón de Envío -->
				<div class="perruqueria-acciones-form">
					<button type="submit" id="perruqueria-btn-reservar" class="perruqueria-btn perruqueria-btn-primary" disabled>
						<span class="perruqueria-btn-texto"><?php esc_html_e( 'Confirmar Reserva', 'la-perruqueria' ); ?></span>
					</button>
				</div>
			</form>

		<?php endif; ?>
	</div>
</div>
