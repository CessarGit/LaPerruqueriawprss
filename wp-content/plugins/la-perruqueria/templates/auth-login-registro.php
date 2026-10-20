<?php
/**
 * Plantilla de formulario de inicio de sesión y registro de clientes.
 *
 * @package La_Perruqueria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$errores      = La_Perruqueria_Auth::obtener_errores();
$redirect_url = ! empty( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : get_permalink();
?>

<div class="perruqueria-auth-wrap">
	<?php if ( ! empty( $errores ) ) : ?>
		<div class="perruqueria-alert perruqueria-alert-error" role="alert">
			<?php foreach ( $errores as $err ) : ?>
				<p><?php echo esc_html( $err ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="perruqueria-grid perruqueria-grid-2">
		<!-- Formulario de Inicio de Sesión -->
		<div class="perruqueria-card">
			<h3><?php esc_html_e( 'Iniciar Sesión', 'la-perruqueria' ); ?></h3>
			<form method="post" action="" class="perruqueria-form">
				<?php wp_nonce_field( 'perruqueria_auth_formulario', 'perruqueria_auth_nonce' ); ?>
				<input type="hidden" name="perruqueria_auth_accion" value="login">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>">

				<!-- Campo Honeypot oculto contra spam -->
				<div style="display:none !important;" aria-hidden="true">
					<label for="perruqueria_hp_login"><?php esc_html_e( 'No llenar este campo', 'la-perruqueria' ); ?></label>
					<input type="text" name="perruqueria_hp" id="perruqueria_hp_login" tabindex="-1" autocomplete="off">
				</div>

				<div class="perruqueria-field">
					<label for="perruqueria_login_user"><?php esc_html_e( 'Usuario o correo electrónico *', 'la-perruqueria' ); ?></label>
					<input type="text" id="perruqueria_login_user" name="log" required class="perruqueria-input">
				</div>

				<div class="perruqueria-field">
					<label for="perruqueria_login_pwd"><?php esc_html_e( 'Contraseña *', 'la-perruqueria' ); ?></label>
					<input type="password" id="perruqueria_login_pwd" name="pwd" required class="perruqueria-input">
				</div>

				<div class="perruqueria-field perruqueria-checkbox">
					<label>
						<input type="checkbox" name="rememberme" value="forever">
						<?php esc_html_e( 'Recordarme', 'la-perruqueria' ); ?>
					</label>
				</div>

				<button type="submit" class="perruqueria-btn perruqueria-btn-primary">
					<?php esc_html_e( 'Entrar', 'la-perruqueria' ); ?>
				</button>
			</form>
		</div>

		<!-- Formulario de Registro de Clientes -->
		<div class="perruqueria-card">
			<h3><?php esc_html_e( 'Crear Cuenta de Cliente', 'la-perruqueria' ); ?></h3>
			<form method="post" action="" class="perruqueria-form">
				<?php wp_nonce_field( 'perruqueria_auth_formulario', 'perruqueria_auth_nonce' ); ?>
				<input type="hidden" name="perruqueria_auth_accion" value="registro">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>">

				<!-- Campo Honeypot oculto contra spam -->
				<div style="display:none !important;" aria-hidden="true">
					<label for="perruqueria_hp_reg"><?php esc_html_e( 'No llenar este campo', 'la-perruqueria' ); ?></label>
					<input type="text" name="perruqueria_hp" id="perruqueria_hp_reg" tabindex="-1" autocomplete="off">
				</div>

				<div class="perruqueria-field">
					<label for="perruqueria_reg_user"><?php esc_html_e( 'Nombre de usuario *', 'la-perruqueria' ); ?></label>
					<input type="text" id="perruqueria_reg_user" name="usuario" required class="perruqueria-input">
				</div>

				<div class="perruqueria-field">
					<label for="perruqueria_reg_email"><?php esc_html_e( 'Correo electrónico *', 'la-perruqueria' ); ?></label>
					<input type="email" id="perruqueria_reg_email" name="correo" required class="perruqueria-input">
				</div>

				<div class="perruqueria-field">
					<label for="perruqueria_reg_pwd"><?php esc_html_e( 'Contraseña (mín. 8 caracteres) *', 'la-perruqueria' ); ?></label>
					<input type="password" id="perruqueria_reg_pwd" name="password" minlength="8" required class="perruqueria-input">
				</div>

				<button type="submit" class="perruqueria-btn perruqueria-btn-secondary">
					<?php esc_html_e( 'Registrarme como Cliente', 'la-perruqueria' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>
