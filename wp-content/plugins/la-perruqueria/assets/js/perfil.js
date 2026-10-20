/**
 * JavaScript del perfil del cliente: cancelación de citas desde el historial.
 * Usa el endpoint REST POST /perruqueria/v1/citas/{id}/cancelar, que valida en el servidor
 * la propiedad de la cita, la transición de estado y el plazo de anticipación.
 *
 * @package La_Perruqueria
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var lista = document.getElementById('perruqueria-historial');
		var aviso = document.getElementById('perruqueria-historial-aviso');

		if (!lista || !window.perruqueriaSettings) {
			return;
		}

		function mostrarAviso(texto, tipo) {
			if (!aviso) {
				return;
			}
			aviso.className = 'perruqueria-alerta perruqueria-alerta-' + tipo;
			aviso.textContent = texto;
			aviso.style.display = 'block';
		}

		lista.addEventListener('click', function (e) {
			var boton = e.target.closest('.perruqueria-btn-cancelar-cita');
			if (!boton || !lista.contains(boton)) {
				return;
			}

			var citaId = boton.getAttribute('data-cita-id');
			if (!citaId) {
				return;
			}

			if (!window.confirm('¿Confirmas que deseas cancelar esta cita?')) {
				return;
			}

			var textoOriginal = boton.textContent;
			boton.disabled = true;
			boton.textContent = 'Cancelando...';

			fetch(perruqueriaSettings.root + 'perruqueria/v1/citas/' + encodeURIComponent(citaId) + '/cancelar', {
				method: 'POST',
				headers: {
					'X-WP-Nonce': perruqueriaSettings.nonce,
					'Accept': 'application/json'
				}
			})
				.then(function (res) {
					return res.json().then(function (data) {
						return { ok: res.ok, data: data };
					});
				})
				.then(function (resultado) {
					if (!resultado.ok) {
						mostrarAviso(
							(resultado.data && resultado.data.message) ? resultado.data.message : 'No se pudo cancelar la cita.',
							'error'
						);
						boton.disabled = false;
						boton.textContent = textoOriginal;
						return;
					}

					// Actualizar la fila sin recargar la página.
					var fila = boton.closest('.perruqueria-cita-item');
					if (fila) {
						var etiqueta = fila.querySelector('.perruqueria-estado');
						if (etiqueta) {
							etiqueta.className = 'perruqueria-estado perruqueria-estado-cancelada';
							etiqueta.textContent = 'Cancelada';
						}
						var acciones = fila.querySelector('.perruqueria-cita-acciones');
						if (acciones) {
							acciones.parentNode.removeChild(acciones);
						}
					}

					mostrarAviso(
						(resultado.data && resultado.data.mensaje) ? resultado.data.mensaje : 'La cita ha sido cancelada.',
						'exito'
					);
				})
				.catch(function () {
					mostrarAviso('Error de red al intentar cancelar la cita. Por favor, reintenta.', 'error');
					boton.disabled = false;
					boton.textContent = textoOriginal;
				});
		});
	});
})();
