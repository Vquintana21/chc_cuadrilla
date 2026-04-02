<?php
session_start();
require_once('conexion.php');

// Obtener ID del curso
if(isset($_GET['curso'])) {
    $idCurso = intval($_GET['curso']);
    $_SESSION['chc_idcurso'] = $idCurso;
} else {
    if(isset($_SESSION['chc_idcurso'])) {
        $idCurso = $_SESSION['chc_idcurso'];
    } else {
        echo '<div class="alert alert-danger">Error: No se especificó el curso</div>';
        exit;
    }
}

// Consultar período activo
$sqlPeriodo = "SELECT inicio, fin, fin_revision FROM chc_periodosys WHERE activo = 1 LIMIT 1";
$resultPeriodo = mysqli_query($conn, $sqlPeriodo);
$periodoActivo = mysqli_fetch_assoc($resultPeriodo);

$fechaActual = date('Y-m-d H:i:s');
$puedeEditar = false;
$enRevision = false;

if($periodoActivo) {
    if($fechaActual >= $periodoActivo['inicio'] && $fechaActual <= $periodoActivo['fin']) {
        $puedeEditar = true;
    } elseif($fechaActual > $periodoActivo['fin'] && $fechaActual <= $periodoActivo['fin_revision']) {
        $enRevision = true;
    }
}

// Consultar solicitudes CHC del curso
// ✅ Se agrega idestadocuadrilla y se hace LEFT JOIN con chc_p_cuadrilla para saber si ya tiene cuadrilla iniciada
$sqlSolicitudes = "
    SELECT 
        s.idsolicitud,
        s.nombrecurso,
        s.codigocurso,
        s.seccion,
        s.fecha_registro,
        s.idestadoagenda,
        s.idestadocuadrilla,
        s.comentario_agenda,
        s.uso_debriefing,
        m.modalidad,
        sm.idmodalidad,
        COUNT(DISTINCT sa.idplanclases) as total_actividades,
        CONCAT(DATE_FORMAT(MIN(p.pcl_Fecha), '%d/%m/%Y'), ' - ', DATE_FORMAT(MAX(p.pcl_Fecha), '%d/%m/%Y')) as rango_fechas,
        cq.idcuadrilla,
        cq.estado as estado_cuadrilla
    FROM chc_solicitud s
    LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
    LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
    LEFT JOIN chc_solicitud_actividad sa ON s.idsolicitud = sa.idsolicitud
    LEFT JOIN planclases_test p ON sa.idplanclases = p.idplanclases
    LEFT JOIN chc_p_cuadrilla cq ON s.idsolicitud = cq.idsolicitud
    WHERE s.idcurso = ?
    GROUP BY s.idsolicitud, s.nombrecurso, s.codigocurso, s.seccion, 
             s.fecha_registro, m.modalidad, sm.idmodalidad,
             s.idestadoagenda, s.idestadocuadrilla, s.comentario_agenda,
             s.uso_debriefing, cq.idcuadrilla, cq.estado
    ORDER BY s.fecha_registro DESC
";

$stmtSolicitudes = mysqli_prepare($conn, $sqlSolicitudes);
mysqli_stmt_bind_param($stmtSolicitudes, "i", $idCurso);
mysqli_stmt_execute($stmtSolicitudes);
$resultSolicitudes = mysqli_stmt_get_result($stmtSolicitudes);
$solicitudes = array();

while($row = mysqli_fetch_assoc($resultSolicitudes)) {
    $solicitudes[] = $row;
}

mysqli_stmt_close($stmtSolicitudes);
?>

<style>
    .chc-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
        background: white;
    }
    .chc-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #0d6efd;
    }
    .chc-header {
        background: #0d6efd;
        color: white;
        padding: 2rem;
        border-radius: 8px;
        margin-bottom: 2rem;
    }
    .btn-agendar-nueva {
        background: #FFC107;
        border: none;
        padding: 1rem 2rem;
        font-size: 1.1rem;
        font-weight: 600;
        transition: transform 0.2s;
    }
    .btn-agendar-nueva:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    .badge-modalidad {
        font-size: 0.9rem;
        padding: 0.4rem 0.8rem;
    }
    .empty-state {
        text-align: center;
        padding: 3rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }
    .empty-state i {
        font-size: 4rem;
        color: #adb5bd;
        margin-bottom: 1rem;
    }

    .swal-wide {
        font-size: 0.9rem !important;
    }

    .actividad-item {
        background: #f8f9fa;
        transition: all 0.2s;
    }

    .actividad-item:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }

    #formEditarCHC .form-label {
        margin-bottom: 0.3rem;
    }

    #formEditarCHC .form-check {
        margin-bottom: 0.5rem;
    }

    #formEditarCHC .alert {
        font-size: 0.9rem;
    }

    .swal2-html-container {
        max-height: 60vh;
        overflow-y: auto;
    }

    .chc-card.border-danger {
        background: #f8d7da !important;
    }

    /* ✅ Estilos para el badge de cuadrilla */
    .badge-cuadrilla-creacion  { background-color: #fd7e14; }
    .badge-cuadrilla-verificacion { background-color: #6f42c1; }
    .badge-cuadrilla-enviada   { background-color: #198754; }

    /* ✅ Botón completar cuadrilla */
    .btn-cuadrilla {
        background-color: #198754;
        color: white;
        border: none;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-cuadrilla:hover {
        background-color: #146c43;
        color: white;
        transform: translateY(-1px);
    }
    .btn-cuadrilla-continuar {
        background-color: #6f42c1;
        color: white;
        border: none;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-cuadrilla-continuar:hover {
        background-color: #59359a;
        color: white;
        transform: translateY(-1px);
    }
</style>

<div class="container-fluid mt-4">
    
    <!-- Header -->
    <div class="chc-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="bi bi-hospital"></i> 
                    Centro de Habilidades Clínicas (CHC)
                </h2>
                <p class="mb-0 opacity-75">
                    Gestión de solicitudes de actividades clínicas
                </p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-light btn-lg btn-agendar-nueva" onclick="iniciarAgendamientoCHC()">
                    <i class="bi bi-plus-circle me-2"></i>
                    Agendar Nueva Actividad
                </button>
            </div>
        </div>
    </div>

    <?php if(empty($solicitudes)): ?>
        <!-- Estado vacío -->
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h4>No hay solicitudes CHC registradas</h4>
            <p class="text-muted mb-4">
                Comience creando su primera solicitud de actividades para el Centro de Habilidades Clínicas
            </p>
            <button class="btn btn-primary btn-lg" onclick="iniciarAgendamientoCHC()">
                <i class="bi bi-plus-circle me-2"></i>
                Crear Primera Solicitud
            </button>
        </div>
        
    <?php else: ?>
        <!-- Listado de solicitudes -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-list-check"></i> 
                        Solicitudes Registradas (<?php echo count($solicitudes); ?>)
                    </h5>
                </div>
            </div>
        </div>

        <div class="row">
            <?php foreach($solicitudes as $solicitud): ?>
            <div class="col-md-6 col-lg-4">
                <div class="chc-card bg-light <?php echo ($solicitud['idestadoagenda'] == 3) ? 'opacity-75 border-danger' : ''; ?>">
                    
                    <!-- Badges: modalidad + fecha + estado agenda -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge bg-success badge-modalidad">
                            <?php echo $solicitud['modalidad']; ?> 
                        </span>
                        <span class="text-muted small">
                            <i class="bi bi-calendar-event"></i>
                            <?php echo date('d/m/Y', strtotime($solicitud['fecha_registro'])); ?>
                        </span>
                        <span class="badge <?php 
                            if($solicitud['idestadoagenda'] == 1) echo 'bg-primary';
                            elseif($solicitud['idestadoagenda'] == 2) echo 'bg-success';
                            elseif($solicitud['idestadoagenda'] == 3) echo 'bg-danger';
                        ?>">
                            <?php 
                                if($solicitud['idestadoagenda'] == 1) {
                                    echo '<i class="bi bi-clock-history"></i> Enviado';
                                } elseif($solicitud['idestadoagenda'] == 2) {
                                    echo '<i class="bi bi-check-circle-fill"></i> Confirmado';
                                } elseif($solicitud['idestadoagenda'] == 3) {
                                    echo '<i class="bi bi-x-circle-fill"></i> Cancelado';
                                }
                            ?>
                        </span>
                    </div>

                    <!-- ✅ Badge estado cuadrilla (solo si agenda está confirmada) -->
                    <?php if($solicitud['idestadoagenda'] == 2 && !empty($solicitud['idcuadrilla'])): ?>
                    <div class="mb-2">
                        <?php
                            $estadoCuad = $solicitud['estado_cuadrilla'];
                            if($estadoCuad == 1) {
                                echo '<span class="badge badge-cuadrilla-creacion"><i class="bi bi-pencil-fill me-1"></i>Cuadrilla en creación</span>';
                            } elseif($estadoCuad == 2) {
                                echo '<span class="badge badge-cuadrilla-verificacion"><i class="bi bi-eye-fill me-1"></i>Cuadrilla en verificación</span>';
                            } elseif($estadoCuad == 3) {
                                echo '<span class="badge badge-cuadrilla-enviada"><i class="bi bi-send-fill me-1"></i>Cuadrilla enviada</span>';
                            }
                        ?>
                    </div>
                    <?php endif; ?>

                    <!-- Información de la solicitud -->
                    <h6 class="mb-2">
                        <strong>Solicitud #<?php echo $solicitud['idsolicitud']; ?></strong>
                    </h6>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">
                            <i class="bi bi-book"></i>
                            <?php echo $solicitud['codigocurso']; ?>-<?php echo $solicitud['seccion']; ?>
                        </small>
                        <small class="text-muted d-block">
                            <i class="bi bi-activity"></i>
                            <?php echo $solicitud['total_actividades']; ?> actividad(es)
                        </small>
                        <small class="text-muted d-block">
                            <i class="bi bi-calendar-range"></i>
                            <?php echo $solicitud['rango_fechas']; ?>
                        </small>
                    </div>

                    <!-- Acciones -->
                    <div class="d-grid gap-2">
                        <?php if($solicitud['idestadoagenda'] == 3): ?>
                            <!-- Cancelado: ver motivo + eliminar -->
                            <?php if(!empty($solicitud['comentario_agenda'])): ?>
                            <?php $comentario_escapado = htmlspecialchars($solicitud['comentario_agenda'], ENT_QUOTES, 'UTF-8'); ?>
                                <button class="btn btn-info btn-sm btn-ver-cancelacion" 
                                    data-idsolicitud="<?php echo $solicitud['idsolicitud']; ?>" 
                                    data-comentario="<?php echo $comentario_escapado; ?>">
                                    <i class="bi bi-chat-left-text"></i> Ver Motivo de Cancelación
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="eliminarSolicitudCHC(<?php echo $solicitud['idsolicitud']; ?>)">
                                <i class="bi bi-trash"></i> Eliminar Solicitud Cancelada
                            </button>

                        <?php elseif($solicitud['idestadoagenda'] == 2): ?>
                            <!-- ✅ CONFIRMADO: Ver detalle + botón cuadrilla -->
                            <button class="btn btn-primary btn-sm" 
                                    onclick="verDetalleSolicitudCHC(<?php echo $solicitud['idsolicitud']; ?>)">
                                <i class="bi bi-eye"></i> Ver Detalle
                            </button>

                            <?php
                                // Determinar qué botón de cuadrilla mostrar
                                $idcuad = $solicitud['idcuadrilla'];
                                $estadoCuad = $solicitud['estado_cuadrilla'];

                                if(empty($idcuad)) {
                                    // No tiene cuadrilla aún → Crear
                                    echo '<button class="btn btn-cuadrilla btn-sm" 
                                            onclick="irACuadrilla(' . $solicitud['idsolicitud'] . ', 0)">
                                            <i class="bi bi-clipboard-plus me-1"></i> Completar Cuadrilla
                                          </button>';
                                } elseif($estadoCuad == 1) {
                                    // En creación → Continuar
                                    echo '<button class="btn btn-cuadrilla-continuar btn-sm" 
                                            onclick="irACuadrilla(' . $solicitud['idsolicitud'] . ', ' . $idcuad . ')">
                                            <i class="bi bi-pencil-fill me-1"></i> Continuar Cuadrilla
                                          </button>';
                                } elseif($estadoCuad == 2) {
                                    // En verificación → Ver/Verificar
                                    echo '<button class="btn btn-cuadrilla-continuar btn-sm" 
                                            onclick="irAVerificarCuadrilla(' . $idcuad . ')">
                                            <i class="bi bi-eye-fill me-1"></i> Verificar Cuadrilla
                                          </button>';
                                } elseif($estadoCuad == 3) {
                                    // Enviada → Solo ver
                                    echo '<button class="btn btn-secondary btn-sm" 
                                            onclick="irAVerificarCuadrilla(' . $idcuad . ')">
                                            <i class="bi bi-send-check-fill me-1"></i> Ver Cuadrilla Enviada
                                          </button>';
                                }
                            ?>

                        <?php else: ?>
                            <!-- ENVIADO (estado 1): opciones normales -->
                            <button class="btn btn-primary btn-sm" 
                                    onclick="verDetalleSolicitudCHC(<?php echo $solicitud['idsolicitud']; ?>)">
                                <i class="bi bi-eye"></i> Ver Detalle
                            </button>
                            <div class="btn-group" role="group">
                                <?php if($solicitud['idestadoagenda'] == 1 && $puedeEditar): ?>
                                    <button class="btn btn-info btn-sm" 
                                            onclick="editarSolicitudCHC(<?php echo $solicitud['idsolicitud']; ?>)">
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                <?php elseif($solicitud['idestadoagenda'] == 1 && $enRevision): ?>
                                    <button class="btn btn-secondary btn-sm">
                                        <i class="bi bi-lock"></i> En Revisión
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-warning btn-sm" 
                                        onclick="eliminarSolicitudCHC(<?php echo $solicitud['idsolicitud']; ?>)">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal para ver comentario de cancelación -->
    <div class="modal fade" id="modalComentarioCancelacion" tabindex="-1" aria-labelledby="modalComentarioCancelacionLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalComentarioCancelacionLabel">
                        <i class="bi bi-x-circle-fill"></i> Motivo de Cancelación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-info-circle"></i> <strong>Solicitud #<span id="idSolicitudCancelada"></span></strong>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Comentario:</h6>
                            <p class="card-text" id="textComentarioCancelacion"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Las funciones irACuadrilla() e irAVerificarCuadrilla() están definidas
     en chc.js (Sección 10) para que estén disponibles globalmente,
     ya que este HTML se inyecta dinámicamente con fetch() en #chc-list. -->