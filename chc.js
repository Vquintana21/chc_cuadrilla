/**
 * ============================================================
 * MÓDULO CHC - Centro de Habilidades Clínicas
 * ============================================================
 * Sistema de gestión de solicitudes CHC para el calendario académico
 * 
 * Dependencias:
 * - Bootstrap 5.x
 * - SweetAlert2
 * - Variable global 'idCurso' (definida en PHP)
 * 
 * @author DPI - Facultad de Medicina
 * @version 2.0
 * @date Diciembre 2025
 * ============================================================
 */

// ===========================================
// VARIABLES GLOBALES CHC
// ===========================================

if (typeof chcPuedeEditar === 'undefined') var chcPuedeEditar = false;
if (typeof chcEnRevision === 'undefined') var chcEnRevision = false;

var chcModalidadSeleccionada = null;
var chcActividadesSeleccionadas = [];
var chcPasoActual = 1;

// ===========================================
// SECCIÓN 1: ESTADO DEL PERÍODO
// ===========================================

/**
 * Carga el estado del período CHC desde el servidor
 * Determina si el usuario puede editar o está en período de revisión
 * @returns {Promise} jQuery AJAX promise
 */
function cargarEstadoPeriodoCHC() {
    return $.ajax({
        url: 'chc_get_periodo.php',
        type: 'GET',
        dataType: 'json'
    }).done(function(response) {
        chcPuedeEditar = response.puedeEditar;
        chcEnRevision = response.enRevision;
        console.log('📅 Estado período CHC - Puede editar:', chcPuedeEditar, '| En revisión:', chcEnRevision);
    }).fail(function(error) {
        console.error('❌ Error al cargar estado del período CHC:', error);
    });
}

// ===========================================
// SECCIÓN 2: PASO 1 - SELECCIÓN DE MODALIDAD
// ===========================================

/**
 * Inicializa el Paso 1 del wizard - Selección de Modalidad
 * Configura event listeners para las tarjetas de modalidad
 */
function inicializarPaso1CHC() {
    console.log('🔵 Inicializando Paso 1 CHC - Modalidad');
    
    // Event listener para las tarjetas de modalidad
    var modalidadCards = document.querySelectorAll('.modalidad-card');
    modalidadCards.forEach(function(card) {
        card.addEventListener('click', function() {
            var radio = this.querySelector('input[type="radio"]');
            if(radio) {
                radio.checked = true;
                seleccionarModalidadCHC(parseInt(radio.value));
            }
        });
    });
    
    // Event listener para el formulario
    var formModalidad = document.getElementById('formModalidad');
    if(formModalidad) {
        formModalidad.addEventListener('submit', function(e) {
            e.preventDefault();
            var radioChecked = document.querySelector('input[name="idmodalidad"]:checked');
            if(radioChecked) {
                chcModalidadSeleccionada = parseInt(radioChecked.value);
                cargarPaso2CHC();
            } else {
                alert('Debe seleccionar una modalidad');
            }
        });
    }
    
    // Verificar si ya hay modalidad seleccionada (sesión)
    var radioChecked = document.querySelector('input[name="idmodalidad"]:checked');
    if(radioChecked) {
        var card = radioChecked.closest('.modalidad-card');
        if(card) {
            card.classList.add('selected');
        }
        var btnSiguiente = document.getElementById('btnSiguiente');
        if(btnSiguiente) {
            btnSiguiente.disabled = false;
        }
    }
}

/**
 * Marca visualmente la modalidad seleccionada
 * @param {number} idModalidad - ID de la modalidad (1=Presencial, 2=Virtual, 3=Exterior)
 */
function seleccionarModalidadCHC(idModalidad) {
    console.log('✅ Modalidad seleccionada:', idModalidad);
    
    // Actualizar estilos de las tarjetas
    var cards = document.querySelectorAll('.modalidad-card');
    cards.forEach(function(card) {
        card.classList.remove('selected');
    });
    
    var selectedCard = document.querySelector('#modalidad_' + idModalidad).closest('.modalidad-card');
    if(selectedCard) {
        selectedCard.classList.add('selected');
    }
    
    // Habilitar botón siguiente
    var btnSiguiente = document.getElementById('btnSiguiente');
    if(btnSiguiente) {
        btnSiguiente.disabled = false;
    }
    
    chcModalidadSeleccionada = idModalidad;
}

// ===========================================
// SECCIÓN 3: PASO 2 - SELECCIÓN DE ACTIVIDADES
// ===========================================

/**
 * Carga el Paso 2 del wizard - Selección de Actividades
 * Guarda la modalidad en sesión y carga la vista
 */
function cargarPaso2CHC() {
    console.log('🔵 Cargando Paso 2 CHC - Actividades');
    
    var chcList = document.getElementById('chc-list');
    chcList.innerHTML = '<div class="text-center p-5"><i class="bi bi-arrow-repeat spinner"></i><p>Cargando actividades...</p></div>';
    
    // Guardar modalidad en servidor
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_guardar_sesion.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4 && xhr.status === 200) {
            // Obtener ID del curso
            var urlParams = new URLSearchParams(window.location.search);
            var cursoId = urlParams.get('curso') || idCurso;
            
            // Cargar vista del paso 2
            fetch('chc_paso2_actividades.php?curso=' + cursoId)
                .then(function(response) { return response.text(); })
                .then(function(html) {
                    chcList.innerHTML = html;
                    inicializarPaso2CHC();
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    chcList.innerHTML = '<div class="alert alert-danger">Error al cargar las actividades</div>';
                });
        }
    };
    xhr.send('paso=guardar_modalidad&idmodalidad=' + chcModalidadSeleccionada);
}

/**
 * Inicializa el Paso 2 del wizard
 * Carga las actividades y configura el formulario
 */
function inicializarPaso2CHC() {
    console.log('🔵 Inicializando Paso 2 CHC - Actividades');
    
    // Cargar actividades CHC
    cargarActividadesCHCPaso2();
    
    // Event listener para el formulario
    var formActividades = document.getElementById('formActividades');
    if(formActividades) {
        formActividades.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarActividadesYContinuar();
        });
    }
}

/**
 * Carga las actividades CHC disponibles para el curso
 * Realiza consulta AJAX al servidor
 */
function cargarActividadesCHCPaso2() {
    var urlParams = new URLSearchParams(window.location.search);
    var cursoId = urlParams.get('curso') || idCurso;
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_actividades.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            if(xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if(response.success) {
                        mostrarActividadesPaso2(response.data);
                    } else {
                        document.getElementById('bodyActividades').innerHTML = 
                            '<tr><td colspan="7" class="text-center text-danger">' + response.message + '</td></tr>';
                    }
                } catch(error) {
                    console.error('Error al parsear JSON:', error);
                    document.getElementById('bodyActividades').innerHTML = 
                        '<tr><td colspan="7" class="text-center text-danger">Error al procesar respuesta</td></tr>';
                }
            }
        }
    };
    xhr.send('curso=' + cursoId);
}

/**
 * Muestra las actividades CHC en la tabla del Paso 2
 * @param {Array} actividades - Array de actividades CHC
 */
function mostrarActividadesPaso2(actividades) {
    var html = '';
    
    if(actividades.length === 0) {
		html = '<tr><td colspan="7" class="text-center text-muted">' +
			   '<i class="bi bi-calendar-x fs-3 d-block mb-2"></i>' +
			   'No hay actividades CHC programadas a partir de hoy.<br>' +
			   '<small>Solo se muestran actividades con fecha igual o posterior a la actual.</small>' +
			   '</td></tr>';
		} else {
        actividades.forEach(function(actividad) {
            // Verificar si tiene solicitud activa o cancelada
            var tieneSolicitud = actividad.tiene_solicitud === 1 || actividad.tiene_solicitud === '1';
            var solicitudCancelada = actividad.solicitud_cancelada === 1 || actividad.solicitud_cancelada === '1';
            
            // Si tiene solicitud cancelada, se considera disponible
            var estaDisponible = !tieneSolicitud || solicitudCancelada;
            
            var claseFilaDeshabilitada = !estaDisponible ? 'actividad-usada' : '';
            var disabled = !estaDisponible ? 'disabled' : '';
            var checked = estaDisponible ? 'checked' : '';
            
            // Iconos según el estado
            var iconoEstado = '';
            if (!estaDisponible) {
                iconoEstado = '<i class="bi bi-lock-fill text-secondary ms-2" title="Ya tiene una solicitud CHC activa"></i>';
            } else if (solicitudCancelada) {
                iconoEstado = '<i class="bi bi-arrow-clockwise text-warning ms-2" title="Solicitud cancelada - Disponible nuevamente"></i>';
            }
            
            html += '<tr class="' + claseFilaDeshabilitada + '">';
            html += '<td class="text-center">';
            html += '<label>';
            html += '<input type="checkbox" class="chk-actividad-chc" name="actividades[]" value="' + actividad.idplanclases + '" ' + checked + ' ' + disabled + ' onchange="actualizarContadorCHC()">';
            html += '<span class="toggle-switch"></span>';
            html += '</label>';
            html += '</td>';
            html += '<td>' + actividad.fecha + '</td>';
            html += '<td>' + actividad.dia + '</td>';
            html += '<td>' + actividad.hora_inicio + '</td>';
            html += '<td>' + actividad.hora_termino + '</td>';
            html += '<td>' + actividad.titulo_actividad + iconoEstado + '</td>';
            html += '<td>' + actividad.tipo_actividad + '</td>';
            html += '</tr>';
        });
    }
    
    document.getElementById('bodyActividades').innerHTML = html;
    actualizarContadorCHC();
}

// ============================================================
// TOGGLE SELECCIONAR/DESELECCIONAR TODAS LAS ACTIVIDADES
// ============================================================
var todasSeleccionadas = true; // Por defecto vienen todas marcadas

function toggleAllActividades() {
    var checkboxes = document.querySelectorAll("#bodyActividades input[type='checkbox']:not(:disabled)");
    var btnTxt = document.getElementById("txtToggle");
    var btnIcon = document.getElementById("iconToggle");
    
    // Cambiar al estado opuesto
    todasSeleccionadas = !todasSeleccionadas;
    
    // Aplicar a todos los checkboxes disponibles (ignora los bloqueados)
    checkboxes.forEach(function(cb) {
        cb.checked = todasSeleccionadas;
    });
    
    // Actualizar texto del botón
    if (todasSeleccionadas) {
        btnTxt.textContent = "Deseleccionar Todas";
        btnIcon.className = "bi bi-check-all";
    } else {
        btnTxt.textContent = "Seleccionar Todas";
        btnIcon.className = "bi bi-square";
    }
    
    // Actualizar contador y botón siguiente
    actualizarContadorCHC();
}

/**
 * Actualiza el contador de actividades seleccionadas
 * Habilita/deshabilita el botón siguiente según la selección
 */
function actualizarContadorCHC() {
    var checkboxes = document.querySelectorAll('.chk-actividad-chc:checked:not(:disabled)');
    var numSeleccionadas = checkboxes.length;
    
    var spanContador = document.getElementById('numSeleccionadas');
    if(spanContador) {
        spanContador.textContent = numSeleccionadas;
    }
    
    var divContador = document.getElementById('contadorActividades');
    if(divContador) {
        divContador.style.display = 'block';
    }
    
    // Habilitar/deshabilitar botón siguiente
    var btnSiguiente = document.getElementById('btnSiguiente');
    if(btnSiguiente) {
        btnSiguiente.disabled = (numSeleccionadas === 0);
    }
}

/**
 * Guarda las actividades seleccionadas y continúa al Paso 3
 */
function guardarActividadesYContinuar() {
    var checkboxes = document.querySelectorAll('.chk-actividad-chc:checked:not(:disabled)');
    chcActividadesSeleccionadas = [];
    
    checkboxes.forEach(function(checkbox) {
        chcActividadesSeleccionadas.push(parseInt(checkbox.value));
    });
    
    if(chcActividadesSeleccionadas.length === 0) {
        alert('Debe seleccionar al menos una actividad disponible');
        return;
    }
    
    console.log('✅ Actividades seleccionadas:', chcActividadesSeleccionadas);
    
    // Guardar en sesión y cargar paso 3
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_guardar_sesion.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4 && xhr.status === 200) {
            cargarPaso3CHC();
        }
    };
    xhr.send('paso=guardar_actividades&actividades=' + JSON.stringify(chcActividadesSeleccionadas));
}

/**
 * Regresa al Paso 1 del wizard
 */
function volverPaso1CHC() {
    var urlParams = new URLSearchParams(window.location.search);
    var cursoId = urlParams.get('curso') || idCurso;
    
    var chcList = document.getElementById('chc-list');
    chcList.innerHTML = '<div class="text-center p-5"><i class="bi bi-arrow-repeat spinner"></i><p>Cargando...</p></div>';
    
    fetch('chc_paso1_modalidad.php?curso=' + cursoId)
        .then(function(response) { return response.text(); })
        .then(function(html) {
            chcList.innerHTML = html;
            inicializarPaso1CHC();
        });
}

// ===========================================
// SECCIÓN 4: PASO 3 - COMPLETAR AGENDA
// ===========================================

/**
 * Carga el Paso 3 del wizard - Completar Agenda
 */
function cargarPaso3CHC() {
    console.log('🔵 Cargando Paso 3 CHC - Agenda');
    
    var chcList = document.getElementById('chc-list');
    chcList.innerHTML = '<div class="text-center p-5"><i class="bi bi-arrow-repeat spinner"></i><p>Cargando formulario...</p></div>';
    
    fetch('chc_paso3_agenda.php')
        .then(function(response) { return response.text(); })
        .then(function(html) {
            chcList.innerHTML = html;
            inicializarPaso3CHC();
        })
        .catch(function(error) {
            console.error('Error:', error);
            chcList.innerHTML = '<div class="alert alert-danger">Error al cargar el formulario</div>';
        });
}

/**
 * Inicializa el Paso 3 del wizard
 * Configura el formulario según la modalidad y si tiene actividades Fantoma
 */
function inicializarPaso3CHC() {
    console.log('🔵 Inicializando Paso 3 CHC - Agenda');
    
    // Obtener si tiene actividades Fantoma
    var tieneActividadFantoma = document.getElementById('tiene_actividad_fantoma');
    var mostrarFantoma = tieneActividadFantoma && tieneActividadFantoma.value === '1';
    
    console.log('🔍 Tiene actividad Fantoma:', mostrarFantoma);
    
    // Configurar evento del formulario
    var formAgenda = document.getElementById('chcForm');
    if(formAgenda) {
        formAgenda.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarSolicitudCHC();
        });
    }
    
    // Inicializar datos personales
    inicializarDatosPersonalesCHC();
    
    // Inicializar lógica del formulario
    configurarFormularioCHC(mostrarFantoma);
}

/**
 * Configura el formulario del Paso 3 según la modalidad
 * @param {boolean} mostrarFantoma - Si debe mostrar campos de Fantoma
 */
function configurarFormularioCHC(mostrarFantoma) {
    var modalidad = parseInt(document.getElementById('idmodalidad').value);
    
    console.log('🔧 Configurando formulario - Modalidad:', modalidad, 'Mostrar Fantoma:', mostrarFantoma);
    
    // ===== LÓGICA DE FANTOMA =====
    var sectionCapacitacionFantoma = document.getElementById('section-capacitacion-fantoma');
    var sectionFechasCapacitacion = document.getElementById('section-fechas-capacitacion');
    
    if(modalidad === 1 && mostrarFantoma) {
        // Mostrar pregunta de capacitación
        if(sectionCapacitacionFantoma) {
            sectionCapacitacionFantoma.style.display = 'block';
        }
        
        // Event listener para mostrar/ocultar fechas de capacitación
        var radiosCapacitado = document.querySelectorAll('input[name="fantoma_capacitado"]');
        radiosCapacitado.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if(this.value === '0') {
                    // NO está capacitado → Mostrar fechas
                    if(sectionFechasCapacitacion) {
                        sectionFechasCapacitacion.style.display = 'block';
                        document.getElementById('fantoma_fecha_capacitacion').required = true;
                        document.getElementById('fantoma_hora_capacitacion').required = true;
                    }
                } else {
                    // SÍ está capacitado → Ocultar fechas
                    if(sectionFechasCapacitacion) {
                        sectionFechasCapacitacion.style.display = 'none';
                        document.getElementById('fantoma_fecha_capacitacion').required = false;
                        document.getElementById('fantoma_hora_capacitacion').required = false;
                        document.getElementById('fantoma_fecha_capacitacion').value = '';
                        document.getElementById('fantoma_hora_capacitacion').value = '';
                    }
                }
            });
            
            // Hacer requeridos los radios
            radio.required = true;
        });
        
    } else {
        // NO mostrar preguntas de Fantoma
        if(sectionCapacitacionFantoma) sectionCapacitacionFantoma.style.display = 'none';
        if(sectionFechasCapacitacion) sectionFechasCapacitacion.style.display = 'none';
        
        // Limpiar valores
        var radiosCapacitado = document.querySelectorAll('input[name="fantoma_capacitado"]');
        radiosCapacitado.forEach(function(radio) {
            radio.checked = false;
            radio.required = false;
        });
        
        console.log('✅ Preguntas de Fantoma OCULTAS');
    }
    
    // ===== LÓGICA DE N° PACIENTES (mayor a 12) =====
    var selectPacientes = document.getElementById('npacientes');
    var containerPacientesOtro = document.getElementById('npacientes_otro_container');
    var inputPacientesOtro = document.getElementById('npacientes_otro');
    
    if(selectPacientes) {
        selectPacientes.addEventListener('change', function() {
            if(this.value === 'mayor_12') {
                containerPacientesOtro.style.display = 'block';
                inputPacientesOtro.required = true;
            } else {
                containerPacientesOtro.style.display = 'none';
                inputPacientesOtro.required = false;
                inputPacientesOtro.value = '';
            }
        });
    }
    
    // ===== LÓGICA DE N° BOXES (mayor a 12) =====
    var selectBoxes = document.getElementById('nboxes');
    var containerBoxesOtro = document.getElementById('nboxes_otro_container');
    var inputBoxesOtro = document.getElementById('nboxes_otro');
    
    if(selectBoxes) {
        selectBoxes.addEventListener('change', function() {
            if(this.value === 'mayor_12') {
                containerBoxesOtro.style.display = 'block';
                inputBoxesOtro.required = true;
            } else {
                containerBoxesOtro.style.display = 'none';
                inputBoxesOtro.required = false;
                inputBoxesOtro.value = '';
            }
        });
    }
    
    // ===== LÓGICA DE ESPACIO OTROS (Presencial) =====
    var checkboxEspacioOtros = document.getElementById('espacio_otros');
    var containerEspacioOtros = document.getElementById('espacio_otros_container');
    var inputEspacioOtros = document.getElementById('espacio_otros_detalle');
    
    if(checkboxEspacioOtros) {
        checkboxEspacioOtros.addEventListener('change', function() {
            if(this.checked) {
                containerEspacioOtros.style.display = 'block';
                inputEspacioOtros.required = true;
            } else {
                containerEspacioOtros.style.display = 'none';
                inputEspacioOtros.required = false;
                inputEspacioOtros.value = '';
            }
        });
    }
    
    // ===== REQUERIDOS SEGÚN MODALIDAD =====
    if(modalidad === 1) { // Presencial
        // N° de Boxes requerido
        if(selectBoxes) selectBoxes.required = true;
        
        // Al menos un espacio debe estar marcado
        var checkboxesEspacio = document.querySelectorAll('input[name^="espacio_"]');
        if(checkboxesEspacio.length > 0) {
            var validarEspacios = function() {
                var algunoMarcado = false;
                checkboxesEspacio.forEach(function(cb) {
                    if(cb.checked) algunoMarcado = true;
                });
                
                checkboxesEspacio.forEach(function(cb) {
                    cb.required = !algunoMarcado;
                });
            };
            
            checkboxesEspacio.forEach(function(cb) {
                cb.addEventListener('change', validarEspacios);
            });
        }
        
        // Uso de debriefing requerido
        var radiosDebriefing = document.querySelectorAll('input[name="uso_debriefing"]');
        radiosDebriefing.forEach(function(r) { r.required = true; });
        
    } else { // Virtual o Exterior
        // Campo de texto espacio_requerido_otros
        var textareaEspacio = document.getElementById('espacio_requerido_otros');
        if(textareaEspacio) textareaEspacio.required = true;
    }
    
    console.log('✅ Formulario configurado correctamente');
}

// ===========================================
// SECCIÓN 5: GUARDAR SOLICITUD
// ===========================================

/**
 * Guarda la solicitud CHC completa
 * Envía todos los datos del formulario al servidor
 */
function guardarSolicitudCHC() {
    console.log('💾 Enviando solicitud CHC...');
    
    var form = document.getElementById('chcForm');
    var formData = new FormData(form);
    
    // Si no hay actividad Fantoma, NO enviar campos de Fantoma
    var tieneActividadFantoma = document.getElementById('tiene_actividad_fantoma');
    if(!tieneActividadFantoma || tieneActividadFantoma.value !== '1') {
        formData.delete('uso_fantoma');
        formData.delete('fantoma_capacitado');
        console.log('⚠️ Campos de Fantoma eliminados del envío (no aplican)');
    }
    
    // Mostrar indicador de carga
    var submitBtn = form.querySelector('button[type="submit"]');
    var btnTextOriginal = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_guardar_solicitud.php', true);
    
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = btnTextOriginal;
            
            if(xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if(response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Solicitud enviada!',
                            html: '<p>Su solicitud CHC ha sido enviada correctamente.</p>' +
                                  '<p><strong>ID Solicitud:</strong> ' + response.idsolicitud + '</p>',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#28a745'
                        }).then(function() {
                            // Volver al tab principal
                            document.getElementById('chc-tab').click();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                } catch(error) {
                    console.error('Error al parsear respuesta:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al procesar la respuesta del servidor',
                        confirmButtonColor: '#dc3545'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Comunicación',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonColor: '#dc3545'
                });
            }
        }
    };
    
    xhr.send(formData);
}

/**
 * Regresa al listado de solicitudes CHC
 */
function volverAListadoCHC() {
    var urlParams = new URLSearchParams(window.location.search);
    var cursoId = urlParams.get('curso') || idCurso;
    
    var chcList = document.getElementById('chc-list');
    chcList.innerHTML = '<div class="text-center p-5"><i class="bi bi-arrow-repeat spinner"></i><p>Cargando...</p></div>';
    
    fetch('chc_index.php?curso=' + cursoId)
        .then(function(response) { return response.text(); })
        .then(function(html) {
            chcList.innerHTML = html;
            if(typeof inicializarListadoCHC === 'function') {
                inicializarListadoCHC();
            }
        });
}

// ===========================================
// SECCIÓN 6: DATOS PERSONALES
// ===========================================

/**
 * Inicializa la sección de datos personales en el Paso 3
 */
function inicializarDatosPersonalesCHC() {
    console.log('📋 Inicializando datos personales CHC');
    
    // Verificar que existen los elementos
    var btnEditar = document.getElementById('btnEditarDatosCHC');
    var btnGuardar = document.getElementById('btnGuardarDatosCHC');
    
    if(!btnEditar || !btnGuardar) {
        console.log('⚠️ Elementos de datos personales no encontrados');
        return;
    }
    
    // Cargar datos automáticamente
    cargarDatosPersonalesCHC();
    
    // Event listener para botón Editar
    btnEditar.addEventListener('click', function() {
        document.getElementById('emailRealCHC').disabled = false;
        document.getElementById('telefonoCHC').disabled = false;
        this.style.display = 'none';
        btnGuardar.style.display = 'inline-block';
    });
    
    // Event listener para botón Guardar
    btnGuardar.addEventListener('click', function() {
        guardarDatosPersonalesCHC();
    });
}

/**
 * Carga los datos personales del profesor desde el servidor
 */
function cargarDatosPersonalesCHC() {
    var rutElement = document.getElementById('rutUsuarioCHC');
    
    if(!rutElement) {
        console.error('❌ Elemento rutUsuarioCHC no encontrado');
        return;
    }
    
    var rut = rutElement.value;
    
    if(!rut) {
        mostrarMensajeDatosCHC('danger', 'Error: No se pudo obtener el RUT del usuario');
        return;
    }
    
    console.log('📡 Cargando datos personales para RUT:', rut);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_obtener_datos_personales.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            if(xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if(response.success) {
                        document.getElementById('nombreCompletoCHC').value = response.data.nombreCompleto;
                        document.getElementById('emailRealCHC').value = response.data.EmailReal;
                        document.getElementById('telefonoCHC').value = response.data.Telefono || '';
                        console.log('✅ Datos personales cargados correctamente');
                    } else {
                        console.error('❌ Error en respuesta:', response.message);
                        mostrarMensajeDatosCHC('danger', 'Error al cargar los datos: ' + response.message);
                    }
                } catch(e) {
                    console.error('❌ Error al parsear respuesta:', e);
                    mostrarMensajeDatosCHC('danger', 'Error al procesar la respuesta del servidor');
                }
            } else {
                console.error('❌ Error HTTP:', xhr.status);
                mostrarMensajeDatosCHC('danger', 'Error de conexión al cargar los datos');
            }
        }
    };
    
    xhr.send('rut=' + encodeURIComponent(rut));
}

/**
 * Guarda los datos personales actualizados
 */
function guardarDatosPersonalesCHC() {
    var rut = document.getElementById('rutUsuarioCHC').value;
    var email = document.getElementById('emailRealCHC').value.trim();
    var telefono = document.getElementById('telefonoCHC').value.trim();
    var btnGuardar = document.getElementById('btnGuardarDatosCHC');

    // Validación básica
    if(email === '') {
        mostrarMensajeDatosCHC('warning', 'El email es obligatorio');
        return;
    }

    // Validación de formato de email
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if(!emailRegex.test(email)) {
        mostrarMensajeDatosCHC('warning', 'Por favor ingrese un email válido');
        return;
    }

    console.log('💾 Guardando datos personales...');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_guardar_datos_personales.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
    
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="bi bi-save"></i> Guardar';
            
            if(xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if(response.success) {
                        console.log('✅ Datos guardados correctamente');
                        mostrarMensajeDatosCHC('success', response.message);
                        document.getElementById('emailRealCHC').disabled = true;
                        document.getElementById('telefonoCHC').disabled = true;
                        btnGuardar.style.display = 'none';
                        document.getElementById('btnEditarDatosCHC').style.display = 'inline-block';
                    } else {
                        console.error('❌ Error al guardar:', response.message);
                        mostrarMensajeDatosCHC('danger', 'Error: ' + response.message);
                    }
                } catch(e) {
                    console.error('❌ Error al parsear respuesta:', e);
                    mostrarMensajeDatosCHC('danger', 'Error al procesar la respuesta del servidor');
                }
            } else {
                console.error('❌ Error HTTP:', xhr.status);
                mostrarMensajeDatosCHC('danger', 'Error de conexión al guardar los datos');
            }
        }
    };
    
    xhr.send('rut=' + encodeURIComponent(rut) + 
             '&email=' + encodeURIComponent(email) + 
             '&telefono=' + encodeURIComponent(telefono));
}

/**
 * Muestra un mensaje en la sección de datos personales
 * @param {string} tipo - Tipo de alerta (success, warning, danger)
 * @param {string} mensaje - Mensaje a mostrar
 */
function mostrarMensajeDatosCHC(tipo, mensaje) {
    var mensajeDiv = document.getElementById('mensajeResultadoDatosCHC');
    
    if(!mensajeDiv) {
        console.warn('⚠️ Elemento mensajeResultadoDatosCHC no encontrado');
        return;
    }
    
    mensajeDiv.className = 'alert alert-' + tipo;
    
    var icono = tipo === 'success' ? 'check-circle' : 
                tipo === 'warning' ? 'exclamation-triangle' : 
                'x-circle';
    
    mensajeDiv.innerHTML = '<i class="bi bi-' + icono + '"></i> ' + mensaje;
    mensajeDiv.style.display = 'block';
    
    if(tipo === 'success') {
        setTimeout(function() {
            mensajeDiv.style.display = 'none';
        }, 3000);
    }
}

// ===========================================
// SECCIÓN 7: RESUMEN / LISTADO CHC
// ===========================================

/**
 * Inicia el proceso de agendamiento CHC
 * Carga el Paso 1 del wizard
 */
function iniciarAgendamientoCHC() {
    console.log('🟢 Iniciando agendamiento CHC');
    
    var urlParams = new URLSearchParams(window.location.search);
    var cursoId = urlParams.get('curso') || idCurso;
    var chcList = document.getElementById('chc-list');
    
    chcList.innerHTML = '<div class="text-center p-5"><i class="bi bi-arrow-repeat spinner"></i><p>Cargando...</p></div>';
    
    fetch('chc_paso1_modalidad.php?curso=' + cursoId)
        .then(function(response) { return response.text(); })
        .then(function(html) {
            chcList.innerHTML = html;
            inicializarPaso1CHC();
        })
        .catch(function(error) {
            console.error('Error:', error);
            chcList.innerHTML = '<div class="alert alert-danger">Error al cargar el formulario</div>';
        });
}

// ===========================================
// SECCIÓN 8: VER DETALLE DE SOLICITUD
// ===========================================

/**
 * Muestra el detalle de una solicitud CHC en un modal
 * @param {number} idSolicitud - ID de la solicitud
 */
function verDetalleSolicitudCHC(idSolicitud) {
    console.log('👁️ Ver detalle solicitud:', idSolicitud);
    
    // Mostrar loading
    Swal.fire({
        title: 'Cargando detalle...',
        html: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_obtener_detalle.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            if(xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if(response.success) {
                        mostrarModalDetalleCHC(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                } catch(error) {
                    console.error('Error al parsear respuesta:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al procesar la respuesta del servidor',
                        confirmButtonColor: '#dc3545'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Comunicación',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonColor: '#dc3545'
                });
            }
        }
    };
    
    xhr.send('idsolicitud=' + idSolicitud);
}

/**
 * Construye y muestra el modal con el detalle de la solicitud
 * @param {Object} data - Datos de la solicitud
 */
function mostrarModalDetalleCHC(data) {
    var general = data.general;
    var actividades = data.actividades;
    var espacios = data.espacios;
    var modalidad = general.idmodalidad;
    
    // ===== CONSTRUIR HTML DE ACTIVIDADES =====
    var htmlActividades = '';
    if(actividades.length === 0) {
        htmlActividades = '<p class="text-muted">No hay actividades registradas</p>';
    } else {
        actividades.forEach(function(act, index) {
            htmlActividades += 
                '<div class="actividad-item mb-3 p-3 border rounded">' +
                    '<div class="d-flex align-items-start">' +
                        '<div class="me-3">' +
                            '<span class="badge bg-primary rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">' + (index + 1) + '</span>' +
                        '</div>' +
                        '<div class="flex-grow-1">' +
                            '<h6 class="mb-1">' +
                                '<i class="bi bi-calendar-event text-primary"></i> ' +
                                act.dia + ' ' + act.fecha +
                            '</h6>' +
                            '<p class="mb-1">' +
                                '<i class="bi bi-clock text-muted"></i> ' +
                                act.hora_inicio + ' - ' + act.hora_termino +
                            '</p>' +
                            '<p class="mb-1">' +
                                '<strong>' + act.titulo + '</strong>' +
                            '</p>' +
                            '<small class="text-muted">' +
                                '<i class="bi bi-tag"></i> ' + act.tipo +
                                (act.subtipo ? ' (' + act.subtipo + ')' : '') +
                            '</small>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        });
    }
    
    // ===== CONSTRUIR HTML DE ESPACIOS =====
    var htmlEspacios = '';
    if(modalidad == 1) {
        // Modalidad Presencial
        if(espacios.length === 0) {
            htmlEspacios = '<p class="text-muted">No se especificaron espacios</p>';
        } else {
            htmlEspacios = '<ul class="list-unstyled mb-2">';
            espacios.forEach(function(esp) {
                htmlEspacios += 
                    '<li class="mb-1">' +
                        '<i class="bi bi-check-circle text-success"></i> ' +
                        esp.nombre;
                        
                if(esp.detalle && esp.detalle.trim() !== '') {
                    htmlEspacios += '<br><small class="text-muted ms-4">→ ' + esp.detalle + '</small>';
                }
                
                htmlEspacios += '</li>';
            });
            htmlEspacios += '</ul>';
        }
    } else {
        // Modalidad Virtual o Exterior
        if(general.espacio_requerido_otros && general.espacio_requerido_otros.trim() !== '') {
            htmlEspacios = 
                '<div class="alert alert-light">' +
                    '<i class="bi bi-info-circle text-primary"></i> ' +
                    general.espacio_requerido_otros +
                '</div>';
        } else {
            htmlEspacios = '<p class="text-muted">No se especificó información de espacios</p>';
        }
    }
    
    // ===== CONSTRUIR HTML COMPLETO DEL MODAL =====
    var htmlModal = 
        '<div style="text-align: left; max-height: 70vh; overflow-y: auto;">' +
            
            // INFORMACIÓN GENERAL
            '<div class="card mb-3">' +
                '<div class="card-header bg-primary text-white">' +
                    '<h6 class="mb-0"><i class="bi bi-info-circle"></i> Información General</h6>' +
                '</div>' +
                '<div class="card-body">' +
                    '<div class="row">' +
                        '<div class="col-md-6 mb-2">' +
                            '<strong>Modalidad:</strong><br>' +
                            '<span class="badge bg-info">' + general.modalidad + '</span>' +
                        '</div>' +
                        '<div class="col-md-6 mb-2">' +
                            '<strong>Fecha de registro:</strong><br>' +
                            new Date(general.fecha_registro).toLocaleDateString('es-CL') +
                        '</div>' +
                        '<div class="col-12 mb-2">' +
                            '<strong>Curso:</strong><br>' +
                            general.codigocurso + '-' + general.seccion + ' - ' + general.nombrecurso +
                        '</div>' +
                        '<div class="col-12">' +
                            '<strong>Solicitante:</strong><br>' +
                            (general.nombrepec ? general.rutpec + ' - ' + general.nombrepec : general.rutpec) +
                            (general.correopec ? '<br><small class="text-muted"><i class="bi bi-envelope"></i> ' + general.correopec + '</small>' : '') +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            
            // ACTIVIDADES
            '<div class="card mb-3">' +
                '<div class="card-header bg-success text-white">' +
                    '<h6 class="mb-0">' +
                        '<i class="bi bi-calendar-check"></i> ' +
                        'Actividades Agendadas (' + actividades.length + ')' +
                    '</h6>' +
                '</div>' +
                '<div class="card-body">' +
                    htmlActividades +
                '</div>' +
            '</div>' +
            
            // DETALLES DE LA SOLICITUD
            '<div class="card mb-3">' +
                '<div class="card-header bg-warning">' +
                    '<h6 class="mb-0"><i class="bi bi-question-circle"></i> Información de la Solicitud</h6>' +
                '</div>' +
                '<div class="card-body">' +
                    '<div class="row">';
    
    // Uso de fantoma (solo Presencial)
    if(modalidad == 1) {
        htmlModal += 
            '<div class="col-md-6 mb-3">' +
                '<strong>Uso de Fantoma de Alta Fidelidad (hal):</strong><br>' +
                '<span class="badge ' + (general.uso_fantoma == 1 ? 'bg-success' : 'bg-secondary') + '">' +
                    (general.uso_fantoma == 1 ? 'Sí' : 'No') +
                '</span>' +
            '</div>';
        
        if(general.uso_fantoma == 1) {
            htmlModal += 
                '<div class="col-md-6 mb-3">' +
                    '<strong>Capacitado para usar:</strong><br>' +
                    '<span class="badge ' + (general.fantoma_capacitado == 1 ? 'bg-success' : 'bg-warning') + '">' +
                        (general.fantoma_capacitado == 1 ? 'Sí' : 'No') +
                    '</span>' +
                '</div>';
        }
    }
    
    // Capacitación Fantoma
    if(general.uso_fantoma == 1 && general.fantoma_capacitado == 0) {
        htmlModal += 
            '<div class="col-md-6 mb-3">' +
                '<strong>Capacitación Fantoma:</strong><br>' +
                '<span class="badge bg-warning text-dark">Requiere capacitación</span>' +
            '</div>';
        
        if(general.fantoma_fecha_capacitacion && general.fantoma_hora_capacitacion) {
            htmlModal += 
                '<div class="col-12 mb-3">' +
                    '<strong>Fecha/Hora propuesta para capacitación:</strong><br>' +
                    '<i class="bi bi-calendar-event text-primary"></i> ' + general.fantoma_fecha_capacitacion + 
                    ' <i class="bi bi-clock text-primary"></i> ' + general.fantoma_hora_capacitacion +
                '</div>';
        }
    } else if(general.uso_fantoma == 1 && general.fantoma_capacitado == 1) {
        htmlModal += 
            '<div class="col-md-6 mb-3">' +
                '<strong>Capacitación Fantoma:</strong><br>' +
                '<span class="badge bg-success">Ya está capacitado</span>' +
            '</div>';
    }
    
    // Pacientes simulados
    htmlModal += 
        '<div class="col-md-6 mb-3">' +
            '<strong>N° pacientes simulados:</strong><br>' +
            (general.npacientes || 'No especificado') +
        '</div>' +
        '<div class="col-md-6 mb-3">' +
            '<strong>N° estudiantes por sesión:</strong><br>' +
            (general.nestudiantesxsesion || 'No especificado') +
        '</div>';
    
    // Boxes (solo Presencial)
    if(modalidad == 1) {
        htmlModal += 
            '<div class="col-md-6 mb-3">' +
                '<strong>N° de boxes:</strong><br>' +
                (general.nboxes || 'No especificado') +
            '</div>';
    }
    
    // Espacios requeridos
    htmlModal += 
        '<div class="col-12 mb-3">' +
            '<strong>Espacios requeridos:</strong><br>' +
            htmlEspacios +
        '</div>';
    
    // Debriefing (solo Presencial)
    if(modalidad == 1) {
        htmlModal += 
            '<div class="col-md-6 mb-3">' +
                '<strong>Uso de debriefing:</strong><br>' +
                '<span class="badge ' + (general.uso_debriefing == 1 ? 'bg-success' : 'bg-secondary') + '">' +
                    (general.uso_debriefing == 1 ? 'Sí' : 'No') +
                '</span>' +
            '</div>';
    }
    
    // Comentarios
    if(general.comentarios && general.comentarios.trim() !== '') {
        htmlModal += 
            '<div class="col-12 mb-3">' +
                '<strong>Comentarios adicionales:</strong><br>' +
                '<div class="alert alert-light mt-2">' +
                    general.comentarios +
                '</div>' +
            '</div>';
    }
    
    htmlModal += 
                    '</div>' + // row
                '</div>' + // card-body
            '</div>' + // card
        '</div>'; // container
    
    // ===== MOSTRAR MODAL CON SWAL =====
    Swal.fire({
        title: '<i class="bi bi-hospital"></i> Detalle de Solicitud CHC #' + general.idsolicitud,
        html: htmlModal,
        width: '900px',
        confirmButtonText: '<i class="bi bi-x-circle"></i> Cerrar',
        confirmButtonColor: '#6c757d',
        customClass: {
            popup: 'swal-wide',
            htmlContainer: 'text-start'
        }
    });
}

// ===========================================
// SECCIÓN 9: ELIMINAR SOLICITUD
// ===========================================

/**
 * Muestra confirmación para eliminar una solicitud CHC
 * @param {number} idSolicitud - ID de la solicitud a eliminar
 */
function eliminarSolicitudCHC(idSolicitud) {
    console.log('🗑️ Eliminar solicitud:', idSolicitud);
    
    Swal.fire({
        title: '¿Eliminar solicitud CHC?',
        html: '<p>Esta acción eliminará:</p>' +
              '<ul class="text-start">' +
              '<li>La solicitud principal</li>' +
              '<li>Todas las actividades asociadas</li>' +
              '<li>Los espacios requeridos</li>' +
              '<li>La modalidad seleccionada</li>' +
              '</ul>' +
              '<p class="text-danger"><strong>Esta acción no se puede deshacer</strong></p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar',
        focusCancel: true
    }).then(function(result) {
        if (result.isConfirmed) {
            ejecutarEliminacionCHC(idSolicitud);
        }
    });
}

/**
 * Ejecuta la eliminación de la solicitud en el servidor
 * @param {number} idSolicitud - ID de la solicitud a eliminar
 */
function ejecutarEliminacionCHC(idSolicitud) {
    // Mostrar loading
    Swal.fire({
        title: 'Eliminando...',
        html: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_eliminar_solicitud.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            if(xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if(response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Eliminado!',
                            text: response.message,
                            confirmButtonColor: '#28a745'
                        }).then(function() {
                            // Recargar la vista de resumen
                            document.getElementById('chc-tab').click();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                } catch(error) {
                    console.error('Error al parsear respuesta:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al procesar la respuesta del servidor',
                        confirmButtonColor: '#dc3545'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Comunicación',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonColor: '#dc3545'
                });
            }
        }
    };
    
    xhr.send('idsolicitud=' + idSolicitud);
}

// ===========================================
// SECCIÓN 10: EDITAR SOLICITUD
// ===========================================

/**
 * Carga los datos de una solicitud para edición
 * @param {number} idSolicitud - ID de la solicitud a editar
 */
function editarSolicitudCHC(idSolicitud) {
    console.log('✏️ Editar solicitud:', idSolicitud);
    
    // Mostrar loading
    Swal.fire({
        title: 'Cargando datos...',
        html: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_obtener_datos_edicion.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            if(xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if(response.success) {
                        mostrarFormularioEdicionCHC(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                } catch(error) {
                    console.error('Error al parsear respuesta:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al procesar la respuesta del servidor',
                        confirmButtonColor: '#dc3545'
                    });
                }
            }
        }
    };
    
    xhr.send('idsolicitud=' + idSolicitud);
}

/**
 * Construye y muestra el formulario de edición en un modal
 * @param {Object} data - Datos de la solicitud para editar
 */
function mostrarFormularioEdicionCHC(data) {
    var solicitud = data.solicitud;
    var espaciosSeleccionados = data.espacios_seleccionados;
    var modalidad = parseInt(solicitud.idmodalidad);
    
    console.log('🔍 Datos para edición:', data);
    
    // ===== CONSTRUIR HTML DEL FORMULARIO =====
    var htmlForm = '<form id="formEditarCHC" style="text-align: left;">';
    
    // Información no editable
    htmlForm += 
        '<div class="alert alert-info mb-3">' +
            '<h6 class="mb-2"><i class="bi bi-info-circle"></i> Información no editable:</h6>' +
            '<p class="mb-1"><strong>Modalidad:</strong> ' + solicitud.nombre_modalidad + '</p>' +
            '<p class="mb-0"><strong>Actividades:</strong> ' + data.actividades.length + ' actividad(es) asociada(s)</p>' +
        '</div>' +
        '<hr>' +
        '<h6 class="mb-3"><i class="bi bi-pencil"></i> Información editable</h6>';
    
    // Campo oculto con ID de solicitud
    htmlForm += '<input type="hidden" name="idsolicitud" value="' + solicitud.idsolicitud + '">';
    
    // ===== CAMPOS SEGÚN MODALIDAD =====
    
    // USO DE FANTOMA (solo Presencial)
    if(modalidad === 1) {
        htmlForm += 
            '<div class="mb-3">' +
                '<label class="form-label fw-bold">Uso de Fantoma de Alta Fidelidad (hal):</label><br>' +
                '<div class="form-check form-check-inline">' +
                    '<input class="form-check-input" type="radio" name="uso_fantoma" id="edit_uso_fantoma_si" value="1" ' + (solicitud.uso_fantoma == 1 ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_uso_fantoma_si">Sí</label>' +
                '</div>' +
                '<div class="form-check form-check-inline">' +
                    '<input class="form-check-input" type="radio" name="uso_fantoma" id="edit_uso_fantoma_no" value="0" ' + (solicitud.uso_fantoma == 0 ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_uso_fantoma_no">No</label>' +
                '</div>' +
            '</div>';
        
        // Capacitación fantoma (condicional)
        var displayCapacitacion = solicitud.uso_fantoma == 1 ? 'block' : 'none';
        htmlForm += 
            '<div class="mb-3" id="edit_section_capacitacion" style="display: ' + displayCapacitacion + ';">' +
                '<label class="form-label fw-bold">¿Está capacitado para usar el Fantoma de Alta Fidelidad (hal)?</label><br>' +
                '<div class="form-check form-check-inline">' +
                    '<input class="form-check-input" type="radio" name="fantoma_capacitado" id="edit_fantoma_capacitado_si" value="1" ' + (solicitud.fantoma_capacitado == 1 ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_fantoma_capacitado_si">Sí</label>' +
                '</div>' +
                '<div class="form-check form-check-inline">' +
                    '<input class="form-check-input" type="radio" name="fantoma_capacitado" id="edit_fantoma_capacitado_no" value="0" ' + (solicitud.fantoma_capacitado == 0 ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_fantoma_capacitado_no">No</label>' +
                '</div>' +
            '</div>';
        
        // Fechas de capacitación
        var displayFechasCapacitacion = (solicitud.uso_fantoma == 1 && solicitud.fantoma_capacitado == 0) ? 'block' : 'none';
        htmlForm += 
            '<div class="mb-3" id="edit_section_fechas_capacitacion" style="display: ' + displayFechasCapacitacion + ';">' +
                '<label class="form-label fw-bold">Fecha y hora propuesta para capacitación:</label>' +
                '<div class="row">' +
                    '<div class="col-md-6">' +
                        '<label class="form-label">Fecha:</label>' +
                        '<input type="date" class="form-control" name="fantoma_fecha_capacitacion" id="edit_fantoma_fecha_capacitacion" value="' + (solicitud.fantoma_fecha_capacitacion || '') + '">' +
                    '</div>' +
                    '<div class="col-md-6">' +
                        '<label class="form-label">Hora:</label>' +
                        '<input type="time" class="form-control" name="fantoma_hora_capacitacion" id="edit_fantoma_hora_capacitacion" value="' + (solicitud.fantoma_hora_capacitacion || '') + '">' +
                    '</div>' +
                '</div>' +
            '</div>';
    }
    
    // NPACIENTES
    var nPacientesValor = solicitud.npacientes || '';
    var esMayor12 = !['1','2','3','4','5','6','7','8','9','10','11','12'].includes(nPacientesValor);
    
    htmlForm += 
        '<div class="mb-3">' +
            '<label class="form-label fw-bold">Número de pacientes simulados:</label>' +
            '<select class="form-select" name="npacientes" id="edit_npacientes">' +
                '<option value="">Seleccione...</option>';
    
    for(var i = 1; i <= 12; i++) {
        var selected = nPacientesValor == i ? 'selected' : '';
        htmlForm += '<option value="' + i + '" ' + selected + '>' + i + '</option>';
    }
    
    htmlForm += 
                '<option value="mayor_12" ' + (esMayor12 ? 'selected' : '') + '>Más de 12</option>' +
            '</select>' +
        '</div>';
    
    // Campo "otro" para npacientes
    var displayNPacientesOtro = esMayor12 ? 'block' : 'none';
    htmlForm += 
        '<div class="mb-3" id="edit_campo_npacientes_otro" style="display: ' + displayNPacientesOtro + ';">' +
            '<label class="form-label">Especifique cantidad:</label>' +
            '<input type="number" class="form-control" name="npacientes_otro" id="edit_npacientes_otro" min="13" value="' + (esMayor12 ? nPacientesValor : '') + '">' +
        '</div>';
    
    // ESTUDIANTES POR SESIÓN
    htmlForm += 
        '<div class="mb-3">' +
            '<label class="form-label fw-bold">Número de estudiantes por sesión:</label>' +
            '<input type="number" class="form-control" name="nestudiantesxsesion" value="' + (solicitud.nestudiantesxsesion || '') + '" min="1" required>' +
        '</div>';
    
    // NBOXES (solo Presencial)
    if(modalidad === 1) {
        var nBoxesValor = solicitud.nboxes || '';
        var esMayor12Boxes = !['1','2','3','4','5','6','7','8','9','10','11','12'].includes(nBoxesValor);
        
        htmlForm += 
            '<div class="mb-3">' +
                '<label class="form-label fw-bold">Número de boxes o estaciones:</label>' +
                '<select class="form-select" name="nboxes" id="edit_nboxes">' +
                    '<option value="">Seleccione...</option>';
        
        for(var j = 1; j <= 12; j++) {
            var selectedBox = nBoxesValor == j ? 'selected' : '';
            htmlForm += '<option value="' + j + '" ' + selectedBox + '>' + j + '</option>';
        }
        
        htmlForm += 
                    '<option value="mayor_12" ' + (esMayor12Boxes ? 'selected' : '') + '>Más de 12</option>' +
                '</select>' +
            '</div>';
        
        // Campo "otro" para nboxes
        var displayNBoxesOtro = esMayor12Boxes ? 'block' : 'none';
        htmlForm += 
            '<div class="mb-3" id="edit_campo_nboxes_otro" style="display: ' + displayNBoxesOtro + ';">' +
                '<label class="form-label">Especifique cantidad:</label>' +
                '<input type="number" class="form-control" name="nboxes_otro" id="edit_nboxes_otro" min="13" value="' + (esMayor12Boxes ? nBoxesValor : '') + '">' +
            '</div>';
    }
    
    // ESPACIOS REQUERIDOS
    if(modalidad === 1) {
        // Presencial: Checkboxes
        htmlForm += 
            '<div class="mb-3">' +
                '<label class="form-label fw-bold">Espacios requeridos:</label>';
        
        var espaciosIds = espaciosSeleccionados.map(function(e) { return e.idespacio; });
        var espacioOtroDetalle = '';
        
        espaciosSeleccionados.forEach(function(esp) {
            if(esp.idespacio == 5 && esp.otro) {
                espacioOtroDetalle = esp.otro;
            }
        });
        
        htmlForm += 
                '<div class="form-check">' +
                    '<input class="form-check-input" type="checkbox" name="espacio_salas_atencion" value="1" id="edit_espacio_salas_atencion" ' + (espaciosIds.includes(1) ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_espacio_salas_atencion">Salas de atención clínica</label>' +
                '</div>' +
                '<div class="form-check">' +
                    '<input class="form-check-input" type="checkbox" name="espacio_salas_procedimientos" value="1" id="edit_espacio_salas_procedimientos" ' + (espaciosIds.includes(2) ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_espacio_salas_procedimientos">Salas de procedimientos</label>' +
                '</div>' +
                '<div class="form-check">' +
                    '<input class="form-check-input" type="checkbox" name="espacio_mesa_buzon" value="1" id="edit_espacio_mesa_buzon" ' + (espaciosIds.includes(3) ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_espacio_mesa_buzon">Mesa-Buzón</label>' +
                '</div>' +
                '<div class="form-check">' +
                    '<input class="form-check-input" type="checkbox" name="espacio_voz_off" value="1" id="edit_espacio_voz_off" ' + (espaciosIds.includes(4) ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_espacio_voz_off">Voz en off</label>' +
                '</div>' +
                '<div class="form-check">' +
                    '<input class="form-check-input" type="checkbox" name="espacio_otros" value="1" id="edit_espacio_otros" ' + (espaciosIds.includes(5) ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_espacio_otros">Otros</label>' +
                '</div>' +
            '</div>';
        
        // Campo detalle "Otros"
        var displayOtros = espaciosIds.includes(5) ? 'block' : 'none';
        htmlForm += 
            '<div class="mb-3" id="edit_campo_espacio_otros" style="display: ' + displayOtros + ';">' +
                '<label class="form-label">Especifique:</label>' +
                '<textarea class="form-control" name="espacio_otros_detalle" id="edit_espacio_otros_detalle" rows="2">' + espacioOtroDetalle + '</textarea>' +
            '</div>';
        
    } else {
        // Virtual o Exterior: Textarea
        var placeholderTexto = modalidad === 2 ? 
            'Describa los recursos virtuales requeridos...' : 
            'Describa el espacio exterior requerido...';
        
        htmlForm += 
            '<div class="mb-3">' +
                '<label class="form-label fw-bold">Espacio requerido:</label>' +
                '<textarea class="form-control" name="espacio_requerido_otros" rows="4" placeholder="' + placeholderTexto + '">' + (solicitud.espacio_requerido_otros || '') + '</textarea>' +
            '</div>';
    }
    
    // USO DE DEBRIEFING (solo Presencial)
    if(modalidad === 1) {
        htmlForm += 
            '<div class="mb-3">' +
                '<label class="form-label fw-bold">Uso de debriefing:</label><br>' +
                '<div class="form-check form-check-inline">' +
                    '<input class="form-check-input" type="radio" name="uso_debriefing" id="edit_uso_debriefing_si" value="1" ' + (solicitud.uso_debriefing == 1 ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_uso_debriefing_si">Sí</label>' +
                '</div>' +
                '<div class="form-check form-check-inline">' +
                    '<input class="form-check-input" type="radio" name="uso_debriefing" id="edit_uso_debriefing_no" value="0" ' + (solicitud.uso_debriefing == 0 ? 'checked' : '') + '>' +
                    '<label class="form-check-label" for="edit_uso_debriefing_no">No</label>' +
                '</div>' +
            '</div>';
    }
    
    // COMENTARIOS
    htmlForm += 
        '<div class="mb-3">' +
            '<label class="form-label fw-bold">Comentarios adicionales:</label>' +
            '<textarea class="form-control" name="comentarios" rows="3" placeholder="Información adicional relevante...">' + (solicitud.comentarios || '') + '</textarea>' +
        '</div>';
    
    htmlForm += '</form>';
    
    // ===== MOSTRAR MODAL CON FORMULARIO =====
    Swal.fire({
        title: '<i class="bi bi-pencil-square"></i> Editar Solicitud CHC #' + solicitud.idsolicitud,
        html: htmlForm,
        width: '700px',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-save"></i> Guardar Cambios',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        customClass: {
            htmlContainer: 'text-start'
        },
        didOpen: function() {
            // ===== EVENT LISTENERS DEL FORMULARIO =====
            
            // Fantoma (solo si existe)
            var radioFantomaSi = document.getElementById('edit_uso_fantoma_si');
            var radioFantomaNo = document.getElementById('edit_uso_fantoma_no');
            var seccionCapacitacion = document.getElementById('edit_section_capacitacion');
            
            if(radioFantomaSi) {
                radioFantomaSi.addEventListener('change', function() {
                    if(this.checked) {
                        seccionCapacitacion.style.display = 'block';
                    }
                });
            }
            
            if(radioFantomaNo) {
                radioFantomaNo.addEventListener('change', function() {
                    if(this.checked) {
                        seccionCapacitacion.style.display = 'none';
                    }
                });
            }
            
            // Fechas de capacitación
            var radioCapacitadoSi = document.getElementById('edit_fantoma_capacitado_si');
            var radioCapacitadoNo = document.getElementById('edit_fantoma_capacitado_no');
            var seccionFechasCapacitacion = document.getElementById('edit_section_fechas_capacitacion');
            var inputFecha = document.getElementById('edit_fantoma_fecha_capacitacion');
            var inputHora = document.getElementById('edit_fantoma_hora_capacitacion');
            
            if(radioCapacitadoSi && seccionFechasCapacitacion) {
                radioCapacitadoSi.addEventListener('change', function() {
                    if(this.checked) {
                        seccionFechasCapacitacion.style.display = 'none';
                        if(inputFecha) inputFecha.required = false;
                        if(inputHora) inputHora.required = false;
                    }
                });
            }
            
            if(radioCapacitadoNo && seccionFechasCapacitacion) {
                radioCapacitadoNo.addEventListener('change', function() {
                    if(this.checked) {
                        seccionFechasCapacitacion.style.display = 'block';
                        if(inputFecha) inputFecha.required = true;
                        if(inputHora) inputHora.required = true;
                    }
                });
            }
            
            // NPacientes
            document.getElementById('edit_npacientes').addEventListener('change', function() {
                var otro = document.getElementById('edit_campo_npacientes_otro');
                if(this.value === 'mayor_12') {
                    otro.style.display = 'block';
                    document.getElementById('edit_npacientes_otro').required = true;
                } else {
                    otro.style.display = 'none';
                    document.getElementById('edit_npacientes_otro').required = false;
                }
            });
            
            // NBoxes (solo si existe)
            var nboxesSelect = document.getElementById('edit_nboxes');
            if(nboxesSelect) {
                nboxesSelect.addEventListener('change', function() {
                    var otro = document.getElementById('edit_campo_nboxes_otro');
                    if(this.value === 'mayor_12') {
                        otro.style.display = 'block';
                        document.getElementById('edit_nboxes_otro').required = true;
                    } else {
                        otro.style.display = 'none';
                        document.getElementById('edit_nboxes_otro').required = false;
                    }
                });
            }
            
            // Checkbox "Otros" en espacios
            var checkboxOtros = document.getElementById('edit_espacio_otros');
            if(checkboxOtros) {
                checkboxOtros.addEventListener('change', function() {
                    var campoOtros = document.getElementById('edit_campo_espacio_otros');
                    var textareaOtros = document.getElementById('edit_espacio_otros_detalle');
                    
                    if(this.checked) {
                        campoOtros.style.display = 'block';
                        textareaOtros.required = true;
                    } else {
                        campoOtros.style.display = 'none';
                        textareaOtros.required = false;
                        textareaOtros.value = '';
                    }
                });
            }
        },
        preConfirm: function() {
            // Validar formulario antes de enviar
            var form = document.getElementById('formEditarCHC');
            
            if(!form.checkValidity()) {
                Swal.showValidationMessage('Por favor complete todos los campos requeridos');
                return false;
            }
            
            return new FormData(form);
        }
    }).then(function(result) {
        if(result.isConfirmed) {
            enviarActualizacionCHC(result.value);
        }
    });
}

/**
 * Envía la actualización de la solicitud al servidor
 * @param {FormData} formData - Datos del formulario a enviar
 */
function enviarActualizacionCHC(formData) {
    // Mostrar loading
    Swal.fire({
        title: 'Guardando cambios...',
        html: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chc_actualizar_solicitud.php', true);
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            if(xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if(response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: response.message,
                            confirmButtonColor: '#28a745'
                        }).then(function() {
                            // Recargar la vista de resumen
                            document.getElementById('chc-tab').click();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                } catch(error) {
                    console.error('Error al parsear respuesta:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al procesar la respuesta del servidor',
                        confirmButtonColor: '#dc3545'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Comunicación',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonColor: '#dc3545'
                });
            }
        }
    };
    
    xhr.send(formData);
}

// ===========================================
// SECCIÓN 11: COMENTARIO DE CANCELACIÓN
// ===========================================

/**
 * Muestra el comentario de cancelación en un modal Bootstrap
 * @param {number} idSolicitud - ID de la solicitud cancelada
 * @param {string} comentario - Comentario/motivo de la cancelación
 */
function verComentarioCancelacion(idSolicitud, comentario) {
    // Decodificar entidades HTML
    var textarea = document.createElement('textarea');
    textarea.innerHTML = comentario;
    var comentarioDecodificado = textarea.value;
    
    // Reemplazar </br> y <br> por saltos de línea reales
    comentarioDecodificado = comentarioDecodificado.replace(/<br\s*\/?>/gi, '\n');
    
    // Llenar el modal con la información
    document.getElementById('idSolicitudCancelada').textContent = idSolicitud;
    
    var comentarioElement = document.getElementById('textComentarioCancelacion');
    comentarioElement.style.whiteSpace = 'pre-wrap';
    comentarioElement.textContent = comentarioDecodificado;
    
    var modal = new bootstrap.Modal(document.getElementById('modalComentarioCancelacion'));
    modal.show();
}

// =============================================
// FUNCIÓN PARA SUBIR CUADRILLA (Estado Confirmado)
// =============================================
// function subirCuadrillaCHC(idSolicitud) {
//     console.log('📄 Subir cuadrilla para solicitud:', idSolicitud);
//     
//     Swal.fire({
//         title: '<i class="bi bi-file-earmark-pdf text-success"></i> Subir Cuadrilla',
//         html: '<iframe id="iframeCuadrilla" src="chc_cuadrilla.php?id=' + idSolicitud + '" ' +
//               'style="width: 100%; height: 500px; border: none;"></iframe>',
//         width: '900px',
//         showConfirmButton: false,
//         showCloseButton: true,
//         customClass: {
//             popup: 'swal-cuadrilla-popup'
//         },
//         didOpen: () => {
//             // Escuchar mensajes del iframe para cerrar el modal si es necesario
//             window.addEventListener('message', function(event) {
//                 if (event.data === 'cuadrilla_subida') {
//                     Swal.close();
//                     // Recargar la vista de CHC
//                     if (typeof cargarVistaCHC === 'function') {
//                         cargarVistaCHC();
//                     }
//                 }
//             });
//         }
//     });
// }



// Función para subir nueva cuadrilla (reemplazo)
//function subirNuevaCuadrillaCHC(idSolicitud) {
//    console.log('📄 Subir NUEVA cuadrilla para solicitud:', idSolicitud);
//    
//    Swal.fire({
//        title: '<i class="bi bi-upload text-warning"></i> Subir Nueva Cuadrilla',
//        html: '<iframe id="iframeCuadrilla" src="chc_cuadrilla.php?id=' + idSolicitud + '&accion=subir" ' +
//              'style="width: 100%; height: 500px; border: none;"></iframe>',
//        width: '900px',
//        showConfirmButton: false,
//        showCloseButton: true,
//        customClass: {
//            popup: 'swal-cuadrilla-popup'
//        },
//        didOpen: () => {
//            // Escuchar mensajes del iframe para cerrar el modal si es necesario
//            window.addEventListener('message', function(event) {
//                if (event.data === 'cuadrilla_subida') {
//                    Swal.close();
//                    // Recargar la vista de CHC
//                    if (typeof cargarVistaCHC === 'function') {
//                        cargarVistaCHC();
//                    }
//                }
//            });
//        }
//    });
//}

// =============================================
// FUNCIÓN PARA SUBIR CUADRILLA (Estado Confirmado)
// =============================================
function subirCuadrillaCHC(idSolicitud) {
    console.log('📄 Subir cuadrilla para solicitud:', idSolicitud);
    
    Swal.fire({
        title: '<i class="bi bi-file-earmark-pdf text-success"></i> Subir Cuadrilla',
        html: '<iframe id="iframeCuadrilla" src="chc_cuadrilla.php?id=' + idSolicitud + '" ' +
              'style="width: 100%; height: 500px; border: none;"></iframe>',
        width: '900px',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            popup: 'swal-cuadrilla-popup'
        },
        didOpen: function() {
            // Escuchar mensajes del iframe para cerrar el modal si es necesario
            window.addEventListener('message', function(event) {
                if (event.data === 'cuadrilla_subida') {
                    Swal.close();
                    // Recargar la vista de CHC haciendo click en el tab
                    var chcTab = document.getElementById('chc-tab');
                    if (chcTab) {
                        chcTab.click();
                    }
                }
            });
        }
    });
}

// Función para subir nueva cuadrilla (reemplazo)
function subirNuevaCuadrillaCHC(idSolicitud) {
    console.log('📄 Subir NUEVA cuadrilla para solicitud:', idSolicitud);
    
    Swal.fire({
        title: '<i class="bi bi-upload text-warning"></i> Subir Nueva Cuadrilla',
        html: '<iframe id="iframeCuadrilla" src="chc_cuadrilla.php?id=' + idSolicitud + '&accion=subir" ' +
              'style="width: 100%; height: 500px; border: none;"></iframe>',
        width: '900px',
		height: '900px',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            popup: 'swal-cuadrilla-popup'
        },
        didOpen: function() {
            // Escuchar mensajes del iframe para cerrar el modal si es necesario
            window.addEventListener('message', function(event) {
                if (event.data === 'cuadrilla_subida') {
                    Swal.close();
                    // Recargar la vista de CHC haciendo click en el tab
                    var chcTab = document.getElementById('chc-tab');
                    if (chcTab) {
                        chcTab.click();
                    }
                }
            });
        }
    });
}

// ===========================================
// SECCIÓN 12: INICIALIZACIÓN Y EVENTOS
// ===========================================

/**
 * Inicializa eventos delegados para el módulo CHC
 * Se ejecuta cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Módulo CHC cargado correctamente');
    
    // Event listener delegado para botones de ver cancelación
    document.body.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-ver-cancelacion');
        if(btn) {
            e.preventDefault();
            var idsolicitud = btn.getAttribute('data-idsolicitud');
            var comentario = btn.getAttribute('data-comentario');
            verComentarioCancelacion(idsolicitud, comentario);
        }
    });
    
    // Cargar estado del período CHC al inicio
    //cargarEstadoPeriodoCHC();
});



// ===========================================
// FIN DEL MÓDULO CHC
// ===========================================
