/**
 * JavaScript interactivo para el frontend de La Perruquería.
 * Gestión de turnos dinámicos y envío asíncrono de reservas.
 *
 * @package La_Perruqueria
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('perruqueria-form-reserva');
		if (!form) {
			return;
		}

		var selectMascota   = document.getElementById('perruqueria-reserva-mascota');
		var selectServicio  = document.getElementById('perruqueria-reserva-servicio');
		var inputFecha      = document.getElementById('perruqueria-reserva-fecha');
		var turnosFeedback  = document.getElementById('perruqueria-turnos-feedback');
		var slotsGrid       = document.getElementById('perruqueria-slots-grid');
		var inputInicio     = document.getElementById('perruqueria-reserva-inicio');
		var inputNotas      = document.getElementById('perruqueria-reserva-notas');
		var mensajeReserva  = document.getElementById('perruqueria-mensaje-reserva');
		var btnReservar     = document.getElementById('perruqueria-btn-reservar');
		var btnTexto        = btnReservar ? btnReservar.querySelector('.perruqueria-btn-texto') : null;

		var textoOriginalBoton = btnTexto ? btnTexto.textContent : 'Confirmar Reserva';

		/**
		 * Muestra un mensaje en el contenedor de alertas del formulario.
		 *
		 * @param {string} texto Mensaje a mostrar.
		 * @param {string} tipo  Clase de alerta ('exito' o 'error').
		 */
		function mostrarAlerta(texto, tipo) {
			if (!mensajeReserva) {
				return;
			}
			mensajeReserva.className = 'perruqueria-alerta perruqueria-alerta-' + tipo;
			mensajeReserva.textContent = texto;
			mensajeReserva.style.display = 'block';
		}

		/**
		 * Oculta el contenedor de alertas.
		 */
		function ocultarAlerta() {
			if (mensajeReserva) {
				mensajeReserva.style.display = 'none';
				mensajeReserva.textContent = '';
			}
		}

		/**
		 * Consulta los turnos disponibles para el servicio y fecha seleccionados.
		 */
		function consultarDisponibilidad() {
			var servicioId = selectServicio ? selectServicio.value : '';
			var fecha      = inputFecha ? inputFecha.value : '';

			inputInicio.value = '';
			if (btnReservar) {
				btnReservar.disabled = true;
			}

			if (!servicioId || !fecha) {
				slotsGrid.style.display = 'none';
				slotsGrid.innerHTML = '';
				turnosFeedback.textContent = 'Selecciona un servicio y una fecha para ver los horarios disponibles.';
				turnosFeedback.style.display = 'block';
				return;
			}

			// Estado de carga
			slotsGrid.style.display = 'none';
			slotsGrid.innerHTML = '';
			turnosFeedback.textContent = (window.perruqueriaSettings && perruqueriaSettings.i18n.cargando_turnos)
				? perruqueriaSettings.i18n.cargando_turnos
				: 'Consultando turnos disponibles...';
			turnosFeedback.style.display = 'block';

			var url = perruqueriaSettings.root + 'perruqueria/v1/disponibilidad?servicio_id=' +
				encodeURIComponent(servicioId) + '&fecha=' + encodeURIComponent(fecha);

			fetch(url, {
				method: 'GET',
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
						turnosFeedback.textContent = (resultado.data && resultado.data.message)
							? resultado.data.message
							: 'No se pudo obtener la disponibilidad.';
						turnosFeedback.style.display = 'block';
						slotsGrid.style.display = 'none';
						return;
					}

					var turnos = (resultado.data && resultado.data.turnos) ? resultado.data.turnos : [];

					if (turnos.length === 0) {
						turnosFeedback.textContent = (window.perruqueriaSettings && perruqueriaSettings.i18n.sin_turnos)
							? perruqueriaSettings.i18n.sin_turnos
							: 'No hay turnos disponibles para esta fecha.';
						turnosFeedback.style.display = 'block';
						slotsGrid.style.display = 'none';
						return;
					}

					// Renderizar botones de turnos
					turnosFeedback.style.display = 'none';
					slotsGrid.innerHTML = '';

					turnos.forEach(function (turno) {
						var botonTurno = document.createElement('button');
						botonTurno.type = 'button';
						botonTurno.className = 'perruqueria-slot-btn';
						botonTurno.textContent = turno.hora;
						botonTurno.setAttribute('data-inicio', turno.inicio);
						botonTurno.setAttribute('data-fin', turno.fin);

						botonTurno.addEventListener('click', function () {
							var activos = slotsGrid.querySelectorAll('.perruqueria-slot-btn.seleccionado');
							activos.forEach(function (btn) {
								btn.classList.remove('seleccionado');
							});

							botonTurno.classList.add('seleccionado');
							inputInicio.value = turno.inicio;
							if (btnReservar) {
								btnReservar.disabled = false;
							}
							ocultarAlerta();
						});

						slotsGrid.appendChild(botonTurno);
					});

					slotsGrid.style.display = 'grid';
				})
				.catch(function () {
					turnosFeedback.textContent = 'Error de conexión al consultar turnos.';
					turnosFeedback.style.display = 'block';
					slotsGrid.style.display = 'none';
				});
		}

		if (selectServicio) {
			selectServicio.addEventListener('change', function () {
				ocultarAlerta();
				consultarDisponibilidad();
			});
		}

		if (inputFecha) {
			inputFecha.addEventListener('change', function () {
				ocultarAlerta();
				consultarDisponibilidad();
			});
		}

		// Enviar formulario de reserva
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			ocultarAlerta();

			var mascotaId  = selectMascota ? selectMascota.value : '';
			var servicioId = selectServicio ? selectServicio.value : '';
			var inicio     = inputInicio ? inputInicio.value : '';
			var notas      = inputNotas ? inputNotas.value : '';

			if (!mascotaId) {
				mostrarAlerta('Por favor, selecciona una mascota.', 'error');
				if (selectMascota) {
					selectMascota.focus();
				}
				return;
			}

			if (!servicioId) {
				mostrarAlerta('Por favor, selecciona un servicio.', 'error');
				if (selectServicio) {
					selectServicio.focus();
				}
				return;
			}

			if (!inicio) {
				mostrarAlerta(
					(window.perruqueriaSettings && perruqueriaSettings.i18n.selecciona_turno)
						? perruqueriaSettings.i18n.selecciona_turno
						: 'Por favor, selecciona un turno disponible.',
					'error'
				);
				return;
			}

			// Bloquear botón durante el procesamiento
			if (btnReservar) {
				btnReservar.disabled = true;
			}
			if (btnTexto) {
				btnTexto.textContent = (window.perruqueriaSettings && perruqueriaSettings.i18n.confirmando_reserva)
					? perruqueriaSettings.i18n.confirmando_reserva
					: 'Procesando reserva...';
			}

			var payload = {
				mascota_id: parseInt(mascotaId, 10),
				servicio_id: parseInt(servicioId, 10),
				inicio: inicio,
				notas: notas
			};

			fetch(perruqueriaSettings.root + 'perruqueria/v1/citas', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': perruqueriaSettings.nonce,
					'Accept': 'application/json'
				},
				body: JSON.stringify(payload)
			})
				.then(function (res) {
					return res.json().then(function (data) {
						return { ok: res.ok, status: res.status, data: data };
					});
				})
				.then(function (resultado) {
					if (!resultado.ok) {
						var mensajeError = (resultado.data && resultado.data.message)
							? resultado.data.message
							: ((window.perruqueriaSettings && perruqueriaSettings.i18n.error_generico)
								? perruqueriaSettings.i18n.error_generico
								: 'Error al reservar la cita.');

						mostrarAlerta(mensajeError, 'error');
						if (btnReservar) {
							btnReservar.disabled = false;
						}
						if (btnTexto) {
							btnTexto.textContent = textoOriginalBoton;
						}
						return;
					}

					// Reserva exitosa
					var mensajeExito = (resultado.data && resultado.data.mensaje)
						? resultado.data.mensaje
						: ((window.perruqueriaSettings && perruqueriaSettings.i18n.reserva_exitosa)
							? perruqueriaSettings.i18n.reserva_exitosa
							: '¡Reserva confirmada con éxito!');

					mostrarAlerta(mensajeExito, 'exito');

					// Limpiar selección de turno y notas
					inputInicio.value = '';
					if (inputNotas) {
						inputNotas.value = '';
					}
					if (btnTexto) {
						btnTexto.textContent = textoOriginalBoton;
					}

					// Actualizar la grilla de turnos para que el turno reservado desaparezca inmediatamente
					consultarDisponibilidad();
				})
				.catch(function () {
					mostrarAlerta('Error de red al intentar procesar la reserva. Por favor, reintenta.', 'error');
					if (btnReservar) {
						btnReservar.disabled = false;
					}
					if (btnTexto) {
						btnTexto.textContent = textoOriginalBoton;
					}
				});
		});

		// Si servicio y fecha ya estuviesen seleccionados inicialmente, consultar disponibilidad
		if (selectServicio && selectServicio.value && inputFecha && inputFecha.value) {
			consultarDisponibilidad();
		}
	});
})();
