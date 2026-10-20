<?php
/**
 * Pantalla del personal en wp-admin: listado de citas con filtros y paginación (solo lectura).
 *
 * Variables recibidas desde La_Perruqueria_Panel_Admin::render_pantalla_citas():
 * $filtros, $total_citas, $paginas, $pagina, $citas.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Acceso no autorizado.', 'la-perruqueria' ) );
}

$etiquetas_estado = array(
	'pendiente'    => __( 'Pendiente', 'la-perruqueria' ),
	'confirmada'   => __( 'Confirmada', 'la-perruqueria' ),
	'reprogramada' => __( 'Reprogramada', 'la-perruqueria' ),
	'cancelada'    => __( 'Cancelada', 'la-perruqueria' ),
);
$tz_sitio      = wp_timezone();
$rango_invalido = ( '' !== $filtros['desde'] && '' !== $filtros['hasta'] && $filtros['desde'] > $filtros['hasta'] );

// URL a la que se vuelve tras confirmar o cancelar (conserva filtros y página).
$url_volver = add_query_arg(
	array(
		'page'        => 'la-perruqueria-citas',
		'filtrar'     => 1,
		'fecha_desde' => $filtros['desde'],
		'fecha_hasta' => $filtros['hasta'],
		'estado'      => $filtros['estado'],
		'paged'       => (int) $pagina,
	),
	admin_url( 'admin.php' )
);

/**
 * Imprime un pequeño formulario POST para confirmar o cancelar una cita.
 */
$boton_estado = function ( $cita_id, $nuevo_estado, $texto, $clase, $confirmacion ) use ( $url_volver ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=la-perruqueria-citas' ) ); ?>" style="display:inline-block; margin: 0 4px 4px 0;"<?php echo '' !== $confirmacion ? ' onsubmit="return confirm(\'' . esc_js( $confirmacion ) . '\');"' : ''; ?>>
		<input type="hidden" name="perruqueria_cita_nonce" value="<?php echo esc_attr( wp_create_nonce( 'perruqueria_cita_estado_' . $cita_id ) ); ?>">
		<input type="hidden" name="perruqueria_admin_accion" value="cambiar_estado_cita">
		<input type="hidden" name="cita_id" value="<?php echo esc_attr( $cita_id ); ?>">
		<input type="hidden" name="nuevo_estado" value="<?php echo esc_attr( $nuevo_estado ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $url_volver ); ?>">
		<button type="submit" class="button button-small <?php echo esc_attr( $clase ); ?>"><?php echo esc_html( $texto ); ?></button>
	</form>
	<?php
};
?>

<div class="wrap perruqueria-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Gestión de Citas - La Perruquería', 'la-perruqueria' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( $rango_invalido ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'La fecha "Desde" es posterior a la fecha "Hasta", por eso no hay resultados.', 'la-perruqueria' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="perruqueria-filtros-citas">
		<input type="hidden" name="page" value="la-perruqueria-citas">
		<input type="hidden" name="filtrar" value="1">

		<label for="perruqueria_fecha_desde"><?php esc_html_e( 'Desde', 'la-perruqueria' ); ?></label>
		<input type="date" id="perruqueria_fecha_desde" name="fecha_desde" value="<?php echo esc_attr( $filtros['desde'] ); ?>">

		<label for="perruqueria_fecha_hasta"><?php esc_html_e( 'Hasta', 'la-perruqueria' ); ?></label>
		<input type="date" id="perruqueria_fecha_hasta" name="fecha_hasta" value="<?php echo esc_attr( $filtros['hasta'] ); ?>">

		<label for="perruqueria_filtro_estado"><?php esc_html_e( 'Estado', 'la-perruqueria' ); ?></label>
		<select id="perruqueria_filtro_estado" name="estado">
			<option value=""><?php esc_html_e( 'Todos', 'la-perruqueria' ); ?></option>
			<?php foreach ( $etiquetas_estado as $clave => $etiqueta ) : ?>
				<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( $filtros['estado'], $clave ); ?>><?php echo esc_html( $etiqueta ); ?></option>
			<?php endforeach; ?>
		</select>

		<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Filtrar', 'la-perruqueria' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=la-perruqueria-citas' ) ); ?>" class="button">
			<?php esc_html_e( 'Restablecer', 'la-perruqueria' ); ?>
		</a>
	</form>

	<p class="description perruqueria-resumen-citas">
		<?php
		printf(
			/* translators: %d: cantidad de citas que cumplen los filtros */
			esc_html( _n( '%d cita encontrada.', '%d citas encontradas.', (int) $total_citas, 'la-perruqueria' ) ),
			(int) $total_citas
		);
		?>
		<?php if ( '' === $filtros['desde'] && '' === $filtros['hasta'] && '' === $filtros['estado'] ) : ?>
			<?php esc_html_e( 'Mostrando todas las citas.', 'la-perruqueria' ); ?>
		<?php endif; ?>
	</p>

	<?php if ( empty( $citas ) ) : ?>
		<div class="postbox">
			<div class="inside">
				<p><?php esc_html_e( 'No hay citas que coincidan con los filtros seleccionados.', 'la-perruqueria' ); ?></p>
			</div>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 50px;"><?php esc_html_e( 'ID', 'la-perruqueria' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Fecha y hora', 'la-perruqueria' ); ?></th>
					<th><?php esc_html_e( 'Cliente', 'la-perruqueria' ); ?></th>
					<th><?php esc_html_e( 'Mascota', 'la-perruqueria' ); ?></th>
					<th><?php esc_html_e( 'Servicio', 'la-perruqueria' ); ?></th>
					<th style="width: 110px;"><?php esc_html_e( 'Estado', 'la-perruqueria' ); ?></th>
					<th style="width: 230px;"><?php esc_html_e( 'Acciones', 'la-perruqueria' ); ?></th>
				</tr>
			</thead>
			<tbody>
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
					?>
					<tr>
						<td>#<?php echo esc_html( $cita['id'] ); ?></td>
						<td>
							<strong><?php echo esc_html( $fecha_txt ); ?></strong><br>
							<?php echo esc_html( $hora_txt ); ?>
						</td>
						<td>
							<strong><?php echo esc_html( $cita['cliente_nombre'] ); ?></strong><br>
							<span class="description"><?php echo esc_html( $cita['cliente_correo'] ); ?></span>
							<?php if ( ! empty( $cita['cliente_telefono'] ) ) : ?>
								<br><span class="description"><?php echo esc_html( $cita['cliente_telefono'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $cita['mascota_nombre'] ); ?>
							<br><span class="description"><?php echo esc_html( $cita['mascota_especie'] ); ?></span>
						</td>
						<td>
							<?php echo esc_html( $cita['servicio_nombre'] ); ?>
							<?php if ( ! empty( $cita['notas'] ) ) : ?>
								<p class="description"><?php echo esc_html( wp_trim_words( $cita['notas'], 12 ) ); ?></p>
							<?php endif; ?>
						</td>
						<td>
							<span class="perruqueria-badge perruqueria-badge-estado-<?php echo esc_attr( $estado_clave ); ?>">
								<?php echo esc_html( $etiquetas_estado[ $estado_clave ] ); ?>
							</span>
						</td>
						<td>
							<?php
							$puede_confirmar   = La_Perruqueria_Reservas::validar_transicion( $cita['estado'], 'confirmada' );
							$puede_reprogramar = La_Perruqueria_Reservas::validar_transicion( $cita['estado'], 'reprogramada' );
							$puede_cancelar    = La_Perruqueria_Reservas::validar_transicion( $cita['estado'], 'cancelada' );

							if ( $puede_confirmar ) {
								$boton_estado( $cita['id'], 'confirmada', __( 'Confirmar', 'la-perruqueria' ), 'button-primary', '' );
							}

							if ( $puede_reprogramar ) {
								$url_reprogramar = add_query_arg(
									array(
										'page'        => 'la-perruqueria-citas',
										'reprogramar' => (int) $cita['id'],
									),
									admin_url( 'admin.php' )
								);
								?>
								<a href="<?php echo esc_url( $url_reprogramar ); ?>" class="button button-small" style="margin: 0 4px 4px 0;">
									<?php esc_html_e( 'Reprogramar', 'la-perruqueria' ); ?>
								</a>
								<?php
							}

							if ( $puede_cancelar ) {
								$boton_estado( $cita['id'], 'cancelada', __( 'Cancelar', 'la-perruqueria' ), 'button-link-delete', __( '¿Confirmas que deseas cancelar esta cita? Esta acción no se puede deshacer.', 'la-perruqueria' ) );
							}

							if ( ! $puede_confirmar && ! $puede_reprogramar && ! $puede_cancelar ) {
								echo '<span class="description">&mdash;</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $paginas > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => (int) $paginas,
								'current'   => (int) $pagina,
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
