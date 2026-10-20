<?php
/**
 * Plantilla del catálogo público de servicios agrupados por categoría.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Los servicios agrupados son pasados desde La_Perruqueria_Servicios::render_catalogo_shortcode().
 * Estructura: array( 'Nombre Categoria' => array( servicios... ) )
 */
?>

<div class="perruqueria-catalogo-wrap">
	<div class="perruqueria-catalogo-header">
		<h2><?php esc_html_e( 'Nuestros Servicios de Peluquería Canina', 'la-perruqueria' ); ?></h2>
		<p class="perruqueria-text-muted">
			<?php esc_html_e( 'Servicios profesionales diseñados para el cuidado, higiene y bienestar de tu mascota.', 'la-perruqueria' ); ?>
		</p>
	</div>

	<?php if ( empty( $servicios_agrupados ) ) : ?>
		<div class="perruqueria-card">
			<p class="perruqueria-empty-text">
				<?php esc_html_e( 'Actualmente no hay servicios disponibles en el catálogo.', 'la-perruqueria' ); ?>
			</p>
		</div>
	<?php else : ?>
		<?php foreach ( $servicios_agrupados as $nombre_categoria => $items ) : ?>
			<div class="perruqueria-categoria-seccion">
				<h3 class="perruqueria-categoria-titulo">
					<?php echo esc_html( $nombre_categoria ); ?>
				</h3>

				<div class="perruqueria-grid perruqueria-grid-servicios">
					<?php foreach ( $items as $s ) : ?>
						<div class="perruqueria-card perruqueria-servicio-card">
							<div class="perruqueria-servicio-top">
								<h4 class="perruqueria-servicio-nombre"><?php echo esc_html( $s['nombre'] ); ?></h4>
								<span class="perruqueria-servicio-precio">
									$<?php echo esc_html( number_format( (float) $s['precio'], 2 ) ); ?>
								</span>
							</div>

							<?php if ( ! empty( $s['descripcion'] ) ) : ?>
								<p class="perruqueria-servicio-desc">
									<?php echo esc_html( $s['descripcion'] ); ?>
								</p>
							<?php endif; ?>

							<div class="perruqueria-servicio-meta">
								<span class="perruqueria-servicio-duracion">
									<strong><?php esc_html_e( 'Duración estimada:', 'la-perruqueria' ); ?></strong>
									<?php printf( esc_html__( '%d minutos', 'la-perruqueria' ), (int) $s['duracion_min'] ); ?>
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
