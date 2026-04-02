<?php
session_start();
require_once('conexion.php');

// Verificar que hay actividades seleccionadas
if(!isset($_SESSION['chc_actividades']) || empty($_SESSION['chc_actividades'])) {
    echo '<div class="alert alert-danger">No hay actividades seleccionadas. Por favor regrese al paso anterior.</div>';
    exit;
}

// Verificar que hay modalidad seleccionada
if(!isset($_SESSION['chc_modalidad'])) {
    echo '<div class="alert alert-danger">No hay modalidad seleccionada. Por favor regrese al paso anterior.</div>';
    exit;
}

$idModalidad = intval($_SESSION['chc_modalidad']);
$actividadesSeleccionadas = $_SESSION['chc_actividades'];

// ===== NUEVA LÓGICA: Verificar si alguna actividad es Fantoma =====
$tieneActividadFantoma = false;

if($idModalidad == 1) { // Solo verificar si es Presencial
    $idsActividades = implode(',', array_map('intval', $actividadesSeleccionadas));
    
    $sqlVerificarFantoma = "
        SELECT COUNT(*) as tiene_fantoma
        FROM planclases
        WHERE idplanclases IN ($idsActividades)
        AND pcl_SubTipoSesion = 'Simulación de alta fidelidad (Fantoma HAL)'
    ";
    
    $resultFantoma = mysqli_query($conn, $sqlVerificarFantoma);
    if($resultFantoma) {
        $rowFantoma = mysqli_fetch_assoc($resultFantoma);
        $tieneActividadFantoma = ($rowFantoma['tiene_fantoma'] > 0);
    }
    
    error_log("CHC Paso 3 - Modalidad: $idModalidad, Tiene Fantoma: " . ($tieneActividadFantoma ? 'SÍ' : 'NO'));
}

// Obtener información de la modalidad
$sqlModalidad = "SELECT * FROM chc_modalidad WHERE idmodalidad = ?";
$stmtModalidad = mysqli_prepare($conn, $sqlModalidad);
mysqli_stmt_bind_param($stmtModalidad, "i", $idModalidad);
mysqli_stmt_execute($stmtModalidad);
$resultModalidad = mysqli_stmt_get_result($stmtModalidad);
$modalidadInfo = mysqli_fetch_assoc($resultModalidad);

// Obtener actividades seleccionadas con sus detalles
$idsActividades = implode(',', array_map('intval', $actividadesSeleccionadas));
$sqlActividades = "
    SELECT 
        idplanclases,
        pcl_tituloActividad,
        DATE_FORMAT(pcl_Fecha, '%d/%m/%Y') as fecha_formateada,
        dia,
        pcl_Inicio,
        pcl_Termino,
        pcl_TipoSesion,
        pcl_SubTipoSesion
    FROM planclases
    WHERE idplanclases IN ($idsActividades)
    ORDER BY pcl_Fecha ASC, pcl_Inicio ASC
";

$resultActividades = mysqli_query($conn, $sqlActividades);
$actividades = array();
while($row = mysqli_fetch_assoc($resultActividades)) {
    $actividades[] = $row;
}

mysqli_close($conn);
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- Título -->
            <div class="text-center mb-4">
                <h3 class="mb-2">
                    <i class="bi bi-clipboard-check text-primary"></i>
                    Paso 3: Completar Información de la Agenda CHC
                </h3>
                <p class="text-muted">Complete los detalles de su solicitud</p>
            </div>

            <!-- Card principal -->
<div class="card shadow-sm">
    <div class="card-body">
        
        <!-- ========================================== -->
        <!-- SECCIÓN: DATOS PERSONALES DEL SOLICITANTE -->
        <!-- ========================================== -->
        <div class="card mb-4 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-circle text-primary"></i>
                        Mis datos de contacto
                    </h5>
                    <button type="button" class="btn btn-sm btn-primary" id="btnEditarDatosCHC">
                        <i class="bi bi-pencil-square"></i>
                        Editar
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="btnGuardarDatosCHC" style="display:none;">
                        <i class="bi bi-save"></i>
                        Guardar
                    </button>
                </div>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i>
                    <small>Mantén actualizados tus datos para recibir notificaciones importantes sobre confirmaciones y recordatorios del CHC</small>
                </div>

                <div id="mensajeResultadoDatosCHC" class="alert" style="display:none;"></div>

                <form id="formDatosPersonalesCHC">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-person"></i>
                                Nombre Completo
                            </label>
                            <input type="text" class="form-control" id="nombreCompletoCHC" readonly style="background-color: #e9ecef;">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="emailRealCHC" class="form-label fw-bold">
                                <i class="bi bi-envelope"></i>
                                Email Institucional <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" id="emailRealCHC" name="emailReal" required disabled>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="telefonoCHC" class="form-label fw-bold">
                                <i class="bi bi-telephone"></i>
                                Teléfono
                            </label>
                            <input type="text" class="form-control" id="telefonoCHC" name="telefono" maxlength="15" disabled>
                        </div>
                    </div>

                    <input type="hidden" id="rutUsuarioCHC" value="<?php echo isset($_SESSION['sesion_idLogin']) ? $_SESSION['sesion_idLogin'] : ''; ?>">
                </form>
            </div>
        </div>
        
        <hr class="my-4">
        
        <!-- Resumen de selección -->
        <div class="alert alert-success mb-4">
            <h6 class="alert-heading">Resumen de su selección:</h6>
                        <p class="mb-2">
                            <strong>Modalidad:</strong> <?php echo $modalidadInfo['modalidad']; ?>
                        </p>
                        <p class="mb-0">
                            <strong>Actividades seleccionadas:</strong> <?php echo count($actividades); ?>
                        </p>
                    </div>
                    
                    <form id="chcForm" method="POST">
                        
                        <!-- Campo oculto con modalidad -->
                        <input type="hidden" name="idmodalidad" id="idmodalidad" value="<?php echo $idModalidad; ?>">
                        
                        <!-- ✅ CAMPO OCULTO: Indica si tiene actividades Fantoma -->
                        <input type="hidden" name="tiene_actividad_fantoma" id="tiene_actividad_fantoma" value="<?php echo $tieneActividadFantoma ? '1' : '0'; ?>">
                        
						<!-- ✅ CAMPO OCULTO: Si tiene actividad Fantoma, uso_fantoma = 1 automáticamente -->
						<?php if($tieneActividadFantoma): ?>
							<input type="hidden" name="uso_fantoma" value="1">
						<?php endif; ?>

						<!-- ✅ SECCIÓN 1: Capacitación Fantoma (SOLO Presencial Y si tiene actividad Fantoma) -->
						<div class="form-section" id="section-capacitacion-fantoma" style="display: <?php echo ($idModalidad == 1 && $tieneActividadFantoma) ? 'block' : 'none'; ?>;">
							<label class="section-title">¿Está capacitado para usar el Fantoma de Alta Fidelidad (hal)? <span class="text-danger">*</span></label>
							<p class="text-muted mb-3">Esta actividad requiere uso de Fantoma. Indique si tiene la capacitación necesaria</p>
							
							<div class="row">
								<div class="col-md-6">
									<div class="form-check">
										<input class="form-check-input" type="radio" name="fantoma_capacitado" value="1" id="fantoma_capacitado_si">
										<label class="form-check-label" for="fantoma_capacitado_si">
											Sí, estoy capacitado
										</label>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-check">
										<input class="form-check-input" type="radio" name="fantoma_capacitado" value="0" id="fantoma_capacitado_no">
										<label class="form-check-label" for="fantoma_capacitado_no">
											No, necesito capacitación
										</label>
									</div>
								</div>
							</div>
						</div>

						<!-- ✅ SECCIÓN 2: Fechas de Capacitación (SOLO si NO está capacitado) -->
						<div class="form-section" id="section-fechas-capacitacion" style="display: none;">
							<label class="section-title">Proponga fecha y hora para su capacitación <span class="text-danger">*</span></label>
							<p class="text-muted mb-3">Indique su disponibilidad para recibir la capacitación en el uso del Fantoma</p>
							
							<div class="row">
								<div class="col-md-6 mb-3">
									<label for="fantoma_fecha_capacitacion" class="form-label fw-bold">
										<i class="bi bi-calendar"></i> Fecha disponible
									</label>
									<input type="date" class="form-control" name="fantoma_fecha_capacitacion" id="fantoma_fecha_capacitacion">
								</div>
								<div class="col-md-6 mb-3">
									<label for="fantoma_hora_capacitacion" class="form-label fw-bold">
										<i class="bi bi-clock"></i> Hora disponible
									</label>
									<input type="time" class="form-control" name="fantoma_hora_capacitacion" id="fantoma_hora_capacitacion">
								</div>
							</div>
						</div>

                        <!-- SECCIÓN 3: N° de Pacientes -->
                        <div class="form-section" id="section-npacientes">
                            <label class="section-title">N° de pacientes por sesión <span class="text-danger">*</span></label>
                            <p class="text-muted mb-3">Seleccione la cantidad de pacientes que atenderá por sesión</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <select class="form-select" name="npacientes" id="npacientes" required>
                                        <option value="">Seleccione una opción</option>
                                        <option value="1">1 paciente</option>
                                        <option value="2">2 pacientes</option>
                                        <option value="3">3 pacientes</option>
                                        <option value="4">4 pacientes</option>
                                        <option value="5">5 pacientes</option>
                                        <option value="6">6 pacientes</option>
                                        <option value="7">7 pacientes</option>
                                        <option value="8">8 pacientes</option>
                                        <option value="9">9 pacientes</option>
                                        <option value="10">10 pacientes</option>
                                        <option value="11">11 pacientes</option>
                                        <option value="12">12 pacientes</option>
                                        <option value="mayor_12">Más de 12 (especificar)</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="npacientes_otro_container" style="display: none;">
                                    <input type="number" class="form-control" name="npacientes_otro" id="npacientes_otro" 
                                           placeholder="Especifique la cantidad" min="13">
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 4: N° de Estudiantes -->
                        <div class="form-section" id="section-nestudiantesxsesion">
                            <label class="section-title">N° de estudiantes por sesión <span class="text-danger">*</span></label>
                            <p class="text-muted mb-3">Indique cuántos estudiantes participarán por sesión</p>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <input type="number" class="form-control" name="nestudiantesxsesion" 
                                           id="nestudiantesxsesion" placeholder="Ej: 10" min="1" required>
                                </div>
                                <div class="col-md-8">
                                    <span class="form-text text-muted">Ingrese un número entero positivo</span>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 5: N° de Boxes (SOLO Presencial) -->
                        <div class="form-section" id="section-nboxes" style="display: <?php echo $idModalidad == 1 ? 'block' : 'none'; ?>;">
                            <label class="section-title">N° de boxes requeridos <span class="text-danger">*</span></label>
                            <p class="text-muted mb-3">Seleccione cuántos boxes necesita para la actividad</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <select class="form-select" name="nboxes" id="nboxes">
                                        <option value="">Seleccione una opción</option>
                                        <option value="1">1 box</option>
                                        <option value="2">2 boxes</option>
                                        <option value="3">3 boxes</option>
                                        <option value="4">4 boxes</option>
                                        <option value="5">5 boxes</option>
                                        <option value="6">6 boxes</option>
                                        <option value="7">7 boxes</option>
                                        <option value="8">8 boxes</option>
                                        <option value="9">9 boxes</option>
                                        <option value="10">10 boxes</option>
                                        <option value="11">11 boxes</option>
                                        <option value="12">12 boxes</option>
                                        <option value="mayor_12">Más de 12 (especificar)</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="nboxes_otro_container" style="display: none;">
                                    <input type="number" class="form-control" name="nboxes_otro" id="nboxes_otro" 
                                           placeholder="Especifique la cantidad" min="13">
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 6: Espacio Requerido -->
                        <div class="form-section" id="section-espacio-requerido-2">
                            <label class="section-title">Espacio requerido <span class="text-danger">*</span></label>
                            
                            <?php if($idModalidad == 1): // Presencial ?>
                                <p class="text-muted mb-3">Seleccione los espacios que necesita (puede marcar varios)</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="espacio_salas_atencion" value="1" id="espacio_salas_atencion">
                                            <label class="form-check-label" for="espacio_salas_atencion">
                                                Salas de Atención
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="espacio_salas_procedimientos" value="1" id="espacio_salas_procedimientos">
                                            <label class="form-check-label" for="espacio_salas_procedimientos">
                                                Salas de Procedimientos
                                            </label>
                                        </div>
										<div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="espacio_mesa_buzon" value="1" id="espacio_mesa_buzon">
                                            <label class="form-check-label" for="espacio_mesa_buzon">
                                                Mesa Buzón
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="espacio_voz_off" value="1" id="espacio_voz_off">
                                            <label class="form-check-label" for="espacio_voz_off">
                                                Voz OFF
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="espacio_otros" value="1" id="espacio_otros">
                                            <label class="form-check-label" for="espacio_otros">
                                                Otros (especificar)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Campo adicional para "Otros" -->
                                <div class="mt-3" id="espacio_otros_container" style="display: none;">
                                    <input type="text" class="form-control" name="espacio_otros_detalle" 
                                           id="espacio_otros_detalle" placeholder="Especifique qué otros espacios necesita">
                                </div>
                                
                            <?php else: // Virtual o Exterior ?>
                                <p class="text-muted mb-3">Describa el espacio o ubicación donde se realizará la actividad</p>
                                <textarea class="form-control" name="espacio_requerido_otros" id="espacio_requerido_otros" 
                                          rows="3" placeholder="Ej: Plataforma Zoom, Sala 201, Hospital San José, etc."></textarea>
                            <?php endif; ?>
                        </div>

                        <!-- SECCIÓN 7: Uso de Debriefing (SOLO Presencial) -->
                        <div class="form-section" id="section-uso-debriefing" style="display: <?php echo $idModalidad == 1 ? 'block' : 'none'; ?>;">
                            <label class="section-title">¿Requiere uso de sala de debriefing? <span class="text-danger">*</span></label>
<p class="text-muted mb-3">Si necesita sala adicional, solicítela en la pestaña salas de calendario</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="uso_debriefing" value="1" id="uso_debriefing_si">
                                        <label class="form-check-label" for="uso_debriefing_si">
                                            Sí, requiere sala de debriefing
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="uso_debriefing" value="0" id="uso_debriefing_no">
                                        <label class="form-check-label" for="uso_debriefing_no">
                                            No requiere sala de debriefing
                                        </label>
                                    </div>
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
    </div>
</div>

<style>
.form-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border-left: 4px solid #0d6efd;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
}

.btn-submit {
    min-width: 200px;
    padding: 12px 24px;
    font-size: 1.1rem;
}
</style>