<?php
// Configuración de la base de datos - modifica según tu configuración
$host = 'localhost';
$dbname = 'dpimeduc_calendario';
$username = 'dpimeduchile';
$password = 'gD5T4)N1FDj1';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener modalidades de la base de datos
try {
    $stmt = $pdo->query("SELECT idmodalidad, modalidad, detalle FROM chc_modalidad ORDER BY idmodalidad");
    $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $modalidades = [];
}

// Procesar formulario si se envía
if ($_POST) {
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHC - Formulario de Solicitud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-section {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .form-section.disabled {
            opacity: 0.5;
            pointer-events: none;
            background: #f8f9fa;
        }
        .section-title {
            color: #212529;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .checkbox-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .text-muted-custom {
            color: #6c757d;
            font-size: 0.9rem;
            font-style: italic;
        }
        .btn-submit {
            background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }
        .header-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h4 class="mb-2">CHC</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <form id="chcForm" method="POST" action="">
                    
                    <!-- Modalidad -->
                    <div class="form-section">
                        <label class="section-title">Modalidad <span class="text-danger">*</span></label>
                        <p class="text-muted mb-3">Seleccione la modalidad de la actividad</p>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <select class="form-select" name="idmodalidad" id="idmodalidad" required>
                                    <option value="">Seleccionar modalidad</option>
                                    <?php foreach($modalidades as $modalidad): ?>
                                        <option value="<?php echo htmlspecialchars($modalidad['idmodalidad']); ?>">
                                            <?php echo htmlspecialchars($modalidad['modalidad']); ?> 
                                            <?php if(!empty($modalidad['detalle'])): ?>
                                                <small>(<?php echo htmlspecialchars($modalidad['detalle']); ?>)</small>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <hr>

                        <!-- Uso de Fantoma -->
                        <label class="section-title">¿Requiere uso de fantoma? <span class="text-danger">*</span></label>
                        <p class="text-muted mb-3">Indique si la actividad requiere el uso de fantomas o simuladores</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="uso_fantoma" value="1" id="uso_fantoma_si" required>
                                    <label class="form-check-label" for="uso_fantoma_si">
                                        Sí, requiere fantoma
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="uso_fantoma" value="0" id="uso_fantoma_no" required>
                                    <label class="form-check-label" for="uso_fantoma_no">
                                        No requiere fantoma
                                    </label>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="alert alert-primary" role="alert">
                            Estas 2 preguntas iniciales para chequear funcionalidad, pero deben venir desde el listado previo declarado en el calendario.
                        </div>
                    </div>

                    <!-- N° de pacientes simulados -->
                    <div class="form-section" id="section-npacientes">
                        <label class="section-title">N° de pacientes simulados (PS) requeridos 
                            <span class="text-muted-custom">(Se solicita la máxima certeza posible en la respuesta, ya que dependiendo de este número se estimará viabilidad):</span>
                        </label>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-select" name="npacientes" id="npacientes">
                                    <option value="">Seleccionar</option>
                                    <option value="0">0</option>
                                    <?php for($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                    <option value="mayor_12">Mayor de 12</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <input type="number" class="form-control" name="npacientes_otro" id="npacientes_otro" 
                                       placeholder="Ingrese número entre 13 y 16" min="13" max="16" disabled>
                            </div>
                        </div>
                    </div>

                    <!-- N° de estudiantes incluidos por sesión -->
                    <div class="form-section" id="section-nestudiantesxsesion">
                        <label class="section-title">N° de estudiantes incluidos por sesión (estimado):</label>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-select" name="nestudiantesxsesion" id="nestudiantesxsesion">
                                    <option value="">Seleccionar</option>
                                    <?php for($i = 1; $i <= 200; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <span class="form-text text-muted">//1 a 200</span>
                            </div>
                        </div>
                    </div>

                    <!-- Espacio requerido (1) - N° de boxes -->
                    <div class="form-section" id="section-nboxes">
                        <label class="section-title">Espacio requerido (1):</label>
                        <p class="text-muted mb-2">Indicar número de boxes o estaciones</p>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-select" name="nboxes" id="nboxes">
                                    <option value="">Seleccionar</option>
                                    <option value="0">0</option>
                                    <?php for($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                    <option value="mayor_12">Mayor de 12</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <input type="number" class="form-control" name="nboxes_otro" id="nboxes_otro" 
                                       placeholder="Ingrese número entre 13 y 16" min="13" max="16" disabled>
                            </div>
                        </div>
                    </div>

                    <!-- Espacio requerido (2) -->
                    <div class="form-section" id="section-espacio-requerido-2">
                        <label class="section-title">Espacio requerido (2):</label>
                        <p class="text-muted mb-3" id="espacio-descripcion">Detallar uso de espacios requeridos para la actividad.</p>
                        
                        <!-- Contenedor para modalidad exterior (modalidad 3) -->
                        <div id="espacio-exterior" style="display: none;">
                            <textarea class="form-control" name="espacio_exterior_detalle" id="espacio_exterior_detalle" rows="4" 
                                      placeholder="Describa detalladamente el espacio donde se realizará la actividad exterior al CHC..."></textarea>
                        </div>
                        
                        <!-- Contenedor para espacios dinámicos (otras modalidades) -->
                        <div id="espacios-dinamicos">
                            <p class="text-muted">Primero seleccione el número de espacios en la sección anterior para configurar el uso específico de cada espacio.</p>
                        </div>
                    </div>

                    <!-- Uso de salas de debriefing -->
                    <div class="form-section" id="section-uso-debriefing">
                        <label class="section-title">Uso de salas de debriefing 
                            <span class="text-muted-custom">(Sólo una sala por actividad)</span>
                        </label>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-select" name="uso_debriefing" id="uso_debriefing">
                                    <option value="">Seleccionar</option>
                                    <option value="1">Sí</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <span class="form-text text-muted">// Sí, No</span>
                            </div>
                        </div>
                    </div>

                    <!-- ¿Está capacitado para usar el fantoma? -->
                    <div class="form-section" id="section-capacitacion-fantoma" style="display: none;">
                        <label class="section-title">¿Está capacitado para usar el fantoma? <span class="text-danger">*</span></label>
                        <p class="text-muted mb-3">Esta pregunta aparece porque indicó que requiere uso de fantoma</p>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-select" name="fantoma_capacitado" id="fantoma_capacitado">
                                    <option value="">Seleccionar</option>
                                    <option value="1">Sí</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <span class="form-text text-muted">Indique si tiene la capacitación necesaria para operar el fantoma</span>
                            </div>
                        </div>
                    </div>

                    <!-- Comentarios adicionales -->
                    <div class="form-section" id="section-comentarios">
                        <label class="section-title">Comentarios adicionales</label>
                        <textarea class="form-control" name="comentarios" id="comentarios" rows="4" 
                                  placeholder="Ingrese cualquier comentario o información adicional relevante..."></textarea>
                    </div>

                    <!-- Botones -->
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-submit me-3">
                            Enviar Solicitud
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            Limpiar Formulario
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuración de activación de preguntas según modalidad
        const modalidadConfig = {
            1: { // Presencial
                npacientes: true,
                nestudiantesxsesion: true,
                nboxes: true,
                espacio_requerido: true,
                uso_debriefing: true,
                fantoma_capacitado: true,
                comentarios: true
            },
            2: { // Virtual
                npacientes: true,
                nestudiantesxsesion: true,
                nboxes: false,
                espacio_requerido: true,
                uso_debriefing: false,
                fantoma_capacitado: false,
                comentarios: true
            },
            3: { // Exterior a CHC
                npacientes: true,
                nestudiantesxsesion: true,
                nboxes: false,
                espacio_requerido: true,
                uso_debriefing: false,
                fantoma_capacitado: false,
                comentarios: true
            }
        };

        // Función para activar/desactivar secciones según modalidad
        function actualizarSeccionesSegunModalidad(modalidadId) {
            const config = modalidadConfig[modalidadId];
            
            if (!config) {
                // Si no hay modalidad seleccionada, ocultar todo
                document.getElementById('section-npacientes').style.display = 'none';
                document.getElementById('section-nestudiantesxsesion').style.display = 'none';
                document.getElementById('section-nboxes').style.display = 'none';
                document.getElementById('section-espacio-requerido-2').style.display = 'none';
                document.getElementById('section-uso-debriefing').style.display = 'none';
                document.getElementById('section-capacitacion-fantoma').style.display = 'none';
                document.getElementById('section-comentarios').style.display = 'none';
                return;
            }

            // N° de pacientes simulados
            const sectionNpacientes = document.getElementById('section-npacientes');
            if (config.npacientes) {
                sectionNpacientes.style.display = 'block';
                sectionNpacientes.classList.remove('disabled');
            } else {
                sectionNpacientes.style.display = 'none';
                document.getElementById('npacientes').value = '';
                document.getElementById('npacientes_otro').value = '';
            }

            // N° de estudiantes por sesión
            const sectionNestudiantes = document.getElementById('section-nestudiantesxsesion');
            if (config.nestudiantesxsesion) {
                sectionNestudiantes.style.display = 'block';
                sectionNestudiantes.classList.remove('disabled');
            } else {
                sectionNestudiantes.style.display = 'none';
                document.getElementById('nestudiantesxsesion').value = '';
            }

            // N° de boxes
            const sectionNboxes = document.getElementById('section-nboxes');
            if (config.nboxes) {
                sectionNboxes.style.display = 'block';
                sectionNboxes.classList.remove('disabled');
            } else {
                sectionNboxes.style.display = 'none';
                document.getElementById('nboxes').value = '';
                document.getElementById('nboxes_otro').value = '';
            }

            // Espacio requerido (2)
            const sectionEspacio = document.getElementById('section-espacio-requerido-2');
            if (config.espacio_requerido) {
                sectionEspacio.style.display = 'block';
                sectionEspacio.classList.remove('disabled');
            } else {
                sectionEspacio.style.display = 'none';
                document.getElementById('espacio_exterior_detalle').value = '';
            }

            // Uso de debriefing
            const sectionDebriefing = document.getElementById('section-uso-debriefing');
            if (config.uso_debriefing) {
                sectionDebriefing.style.display = 'block';
                sectionDebriefing.classList.remove('disabled');
            } else {
                sectionDebriefing.style.display = 'none';
                document.getElementById('uso_debriefing').value = '';
            }

            // Capacitación de fantoma (depende de uso_fantoma también)
            actualizarCapacitacionFantoma();

            // Comentarios
            const sectionComentarios = document.getElementById('section-comentarios');
            if (config.comentarios) {
                sectionComentarios.style.display = 'block';
                sectionComentarios.classList.remove('disabled');
            } else {
                sectionComentarios.style.display = 'none';
                document.getElementById('comentarios').value = '';
            }
        }

        // Función para actualizar la sección de capacitación de fantoma
        function actualizarCapacitacionFantoma() {
            const modalidadId = parseInt(document.getElementById('idmodalidad').value);
            const config = modalidadConfig[modalidadId];
            const usoFantomaChecked = document.querySelector('input[name="uso_fantoma"]:checked');
            const capacitacionSection = document.getElementById('section-capacitacion-fantoma');
            const capacitacionSelect = document.getElementById('fantoma_capacitado');

            if (config && config.fantoma_capacitado && usoFantomaChecked && usoFantomaChecked.value === '1') {
                capacitacionSection.style.display = 'block';
                capacitacionSelect.required = true;
            } else {
                capacitacionSection.style.display = 'none';
                capacitacionSelect.required = false;
                capacitacionSelect.value = '';
            }
        }

        // Evento al cambiar modalidad
        document.getElementById('idmodalidad').addEventListener('change', function() {
            const modalidadId = parseInt(this.value);
            actualizarSeccionesSegunModalidad(modalidadId);
            
            // Lógica específica para modalidad exterior (3)
            const espacioExterior = document.getElementById('espacio-exterior');
            const espaciosDinamicos = document.getElementById('espacios-dinamicos');
            const descripcion = document.getElementById('espacio-descripcion');
            const espacioExteriorDetalle = document.getElementById('espacio_exterior_detalle');
            
            if (modalidadId === 3) {
                espacioExterior.style.display = 'block';
                espaciosDinamicos.style.display = 'none';
                descripcion.innerHTML = 'Describa detalladamente el espacio donde se realizará la actividad <strong>exterior al CHC</strong>. Este campo es obligatorio.';
                espacioExteriorDetalle.required = true;
                espaciosDinamicos.innerHTML = '<p class="text-muted">No aplica para modalidad exterior.</p>';
            } else {
                espacioExterior.style.display = 'none';
                espaciosDinamicos.style.display = 'block';
                descripcion.innerHTML = 'Detallar uso de espacios requeridos para la actividad.';
                espacioExteriorDetalle.required = false;
                espaciosDinamicos.innerHTML = '<p class="text-muted">Primero seleccione el número de espacios en la sección anterior para configurar el uso específico de cada espacio.</p>';
            }
        });

        // Manejo del uso de fantoma
        document.querySelectorAll('input[name="uso_fantoma"]').forEach(radio => {
            radio.addEventListener('change', function() {
                actualizarCapacitacionFantoma();
            });
        });

        // Habilitar campo "otro" cuando se selecciona "mayor de 12" en pacientes
        document.getElementById('npacientes').addEventListener('change', function() {
            const otroField = document.getElementById('npacientes_otro');
            if (this.value === 'mayor_12') {
                otroField.disabled = false;
                otroField.required = true;
            } else {
                otroField.disabled = true;
                otroField.required = false;
                otroField.value = '';
            }
        });

        // Habilitar campo "otro" cuando se selecciona "mayor de 12" en boxes
        document.getElementById('nboxes').addEventListener('change', function() {
            const modalidad = parseInt(document.getElementById('idmodalidad').value);
            if (modalidad === 3) return;
            
            const otroField = document.getElementById('nboxes_otro');
            
            if (this.value === 'mayor_12') {
                otroField.disabled = false;
                otroField.required = true;
                generarEspaciosDinamicos(0);
            } else {
                otroField.disabled = true;
                otroField.required = false;
                otroField.value = '';
                
                const numEspacios = parseInt(this.value) || 0;
                generarEspaciosDinamicos(numEspacios);
            }
        });

        // Actualizar espacios cuando se cambie el campo "otro"
        document.getElementById('nboxes_otro').addEventListener('input', function() {
            const modalidad = parseInt(document.getElementById('idmodalidad').value);
            if (modalidad === 3) return;
            
            const numEspacios = parseInt(this.value) || 0;
            if (numEspacios >= 13 && numEspacios <= 16) {
                generarEspaciosDinamicos(numEspacios);
            } else if (this.value && (numEspacios < 13 || numEspacios > 16)) {
                const container = document.getElementById('espacios-dinamicos');
                if (container) {
                    container.innerHTML = '<p class="text-danger">El número debe estar entre 13 y 16.</p>';
                }
            }
        });

        function generarEspaciosDinamicos(numEspacios) {
            let container = document.getElementById('espacios-dinamicos');
            
            if (!container) {
                const formSections = document.querySelectorAll('.form-section');
                let targetSection = null;
                
                for (let section of formSections) {
                    const title = section.querySelector('.section-title');
                    if (title && title.textContent.includes('Espacio requerido (2)')) {
                        targetSection = section;
                        break;
                    }
                }
                
                if (targetSection) {
                    const existingCheckboxContainer = targetSection.querySelector('.checkbox-container');
                    if (existingCheckboxContainer) {
                        existingCheckboxContainer.remove();
                    }
                    
                    container = document.createElement('div');
                    container.id = 'espacios-dinamicos';
                    targetSection.appendChild(container);
                } else {
                    return;
                }
            }
            
            if (numEspacios === 0 || isNaN(numEspacios)) {
                container.innerHTML = '<p class="text-muted">Primero seleccione el número de espacios en la sección anterior para configurar el uso específico de cada espacio.</p>';
                return;
            }

            let html = '';
            for (let i = 1; i <= numEspacios; i++) {
                html += `
                    <div class="checkbox-container mb-3">
                        <h6 class="fw-bold mb-3">Espacio ${i} - Seleccione el tipo de uso:</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="espacio_${i}" value="salas_atencion" id="salas_atencion_${i}">
                                    <label class="form-check-label" for="salas_atencion_${i}">
                                        salas de atención clínica
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="espacio_${i}" value="salas_procedimientos" id="salas_procedimientos_${i}">
                                    <label class="form-check-label" for="salas_procedimientos_${i}">
                                        salas de procedimientos
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="espacio_${i}" value="voz_off" id="voz_off_${i}">
                                    <label class="form-check-label" for="voz_off_${i}">
                                        voz en off
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="espacio_${i}" value="otros" id="otros_${i}">
                                    <label class="form-check-label" for="otros_${i}">
                                        otros
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <input type="text" class="form-control" name="espacio_${i}_especificar" id="espacio_${i}_especificar" 
                                   placeholder="Especifique detalles para este espacio o descripción si seleccionó 'otros'">
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;

            setTimeout(() => {
                for (let i = 1; i <= numEspacios; i++) {
                    const radios = document.querySelectorAll(`input[name="espacio_${i}"]`);
                    const especificarField = document.getElementById(`espacio_${i}_especificar`);
                    
                    radios.forEach(radio => {
                        radio.addEventListener('change', function() {
                            if (this.value === 'otros' && this.checked) {
                                especificarField.required = true;
                                especificarField.placeholder = "Especifique el tipo de espacio";
                            } else {
                                especificarField.required = false;
                                especificarField.placeholder = "Especifique detalles para este espacio o descripción si seleccionó 'otros'";
                            }
                        });
                    });
                }
            }, 100);
        }

        // Inicializar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            actualizarSeccionesSegunModalidad(null);
        });
    </script>
</body>
</html>