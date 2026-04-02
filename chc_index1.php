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

$fecha_inicio = new DateTime($periodoActivo['inicio']);
$fecha_fin = new DateTime($periodoActivo['fin']);
$fecha_fin_revision = new DateTime($periodoActivo['fin_revision']);

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
$sqlSolicitudes = "
    SELECT 
        s.idsolicitud,
        s.nombrecurso,
        s.codigocurso,
        s.seccion,
        s.fecha_registro,
        m.modalidad,
		s.idestadoagenda,
		s.comentario_agenda,
        COUNT(DISTINCT sa.idplanclases) as total_actividades,
        CONCAT(DATE_FORMAT(MIN(p.pcl_Fecha), '%d/%m/%Y'), ' - ', DATE_FORMAT(MAX(p.pcl_Fecha), '%d/%m/%Y')) as rango_fechas,
		(SELECT COUNT(*) FROM chc_cuadrilla_doc cd WHERE cd.idsolicitud = s.idsolicitud AND cd.activo = 1) as total_cuadrillas
    FROM chc_solicitud s
    LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
    LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
    LEFT JOIN chc_solicitud_actividad sa ON s.idsolicitud = sa.idsolicitud
    LEFT JOIN planclases p ON sa.idplanclases = p.idplanclases
    WHERE s.idcurso = ?
    GROUP BY s.idsolicitud, s.nombrecurso, s.codigocurso, s.seccion, s.fecha_registro, m.modalidad
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
        padding: 1rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
        background: white;
    }
    .chc-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #0d6efd;
    }
    .chc-header {
         background: #666666;
        color: white;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 2rem;
    }
    .btn-agendar-nueva {
        background: #0d6efd;
        border: none;
        padding: 1rem 2rem;
        font-size: 1rem;
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

</style>

<div class="container-fluid mt-4">
    
    <!-- Header -->
    <div class="chc-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-2">
                    <i class="bi bi-hospital"></i> 
                    Centro de Habilidades Clínicas (CHC)
                </h4>
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
			  <!--
			<div class="col-md-4 text-end">
			<?php //if($puedeEditar): ?>
				<button class="btn btn-primary btn-lg btn-agendar-nueva" onclick="iniciarAgendamientoCHC()">
					<i class="bi bi-plus-circle me-2"></i>
					Agendar Nueva Actividad
				</button>
			<?php //elseif($enRevision): ?>
				<button class="btn btn-warning btn-lg" disabled style="background-color: #FFC107 !important;">
					<i class="bi bi-lock me-2"></i>
					Período en Revisión
				</button>
			<?php //else: ?>
				<button class="btn btn-warning btn-lg" disabled style="background-color: #DC3741 !important;">
					<i class="bi bi-calendar-x me-2"></i>
					Fuera de Período
				</button>
			<?php //endif; ?>
		</div>  -->
        </div>
    </div>
	
<div class="accordion mb-4" id="accordionInstrucciones">
    <div class="accordion-item border-warning">
        <h2 class="accordion-header">
            <button class="accordion-button bg-warning bg-opacity-10 text-warning fw-bold d-flex justify-content-between align-items-center"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseInstrucciones"
                    aria-expanded="true"
                    aria-controls="collapseInstrucciones">
                <span>
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Instrucciones Importantes para Agenda CHC
                </span>
                <span class="badge bg-info ms-3">Pinche aquí</span>
            </button>
        </h2>

        <div id="collapseInstrucciones"
             class="accordion-collapse collapse show"
             data-bs-parent="#accordionInstrucciones">
            <div class="accordion-body">
                <ul class="list-group list-group-flush">
				  <li class="list-group-item">                       
                        <a class="nav-link" href="https://dpi.med.uchile.cl/calendario/capacitaciones.php" target="blank" >   <i class="fas fa-video"></i> Ver capacitación CHC </a>
                   </li>
                    <li class="list-group-item">
                        <i class="bi bi-clipboard-check text-primary me-2"></i>
                        Fechas en que podrá pedir, editar, agregar y eliminar agenda será desde
                        <b><?= $fecha_inicio->format('d/m/Y H:i'); ?></b> hasta
                        <b><?= $fecha_fin->format('d/m/Y H:i'); ?></b>
                    </li>
                    <li class="list-group-item">
                        <i class="bi bi-pencil-square text-success me-2"></i>
                        Fechas de revisión en que NO podrá editar y eliminar agendas ya en revisión,
                        será desde <b><?= $fecha_fin->format('d/m/Y H:i'); ?></b> hasta
                        <b><?= $fecha_fin_revision->format('d/m/Y H:i'); ?></b>
                    </li>
                    <li class="list-group-item">
                        <i class="bi bi-pencil text-success me-2"></i>
                        En las fechas de revisión podrá enviar más solicitudes nuevas,
                        pero serán gestionadas en plazos diferidos y marcadas como
                        <b>fuera de plazo</b>.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

	
	

    <?php if(empty($solicitudes)): ?>
        <!-- Estado vacío -->
        <div class="empty-state">
            <i class="bi bi-calendar-x me-2 fs-4"></i>
            <h5>No hay solicitudes CHC registradas</h5>
            <p class="text-muted mb-4">
                Comience creando su primera solicitud de actividades para el Centro de Habilidades Clínicas
            </p>
            <!-- <button class="btn btn-primary" onclick="iniciarAgendamientoCHC()">
                Crear Primera Solicitud
            </button> -->
			
			<button class="btn btn-primary btn-lg" onclick="iniciarAgendamientoCHC()">
					<i class="bi bi-plus-circle me-2 fs-5"></i>
					Crear Primera Solicitud
			</button>
			<!-- 
			<?php // if($puedeEditar): ?>
				<button class="btn btn-primary btn-lg" onclick="iniciarAgendamientoCHC()">
					<i class="bi bi-plus-circle me-2 fs-5"></i>
					Crear Primera Solicitud
				</button>
			<?php // else: ?>
				<button class="btn btn-secondary btn-lg" disabled>
					<i class="bi bi-lock me-2"></i>
					<?php // echo $enRevision ? 'Período en Revisión' : 'Fuera de Período'; ?>
				</button>
			<?php // endif; ?>
			-->
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
                    <!-- Badge de modalidad -->
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
							<!-- Estado Cancelado - Solo ver motivo, NO eliminar (queda para estadísticas) -->
							<?php if(!empty($solicitud['comentario_agenda'])): ?>
							<?php 
								$comentario_escapado = htmlspecialchars($solicitud['comentario_agenda'], ENT_QUOTES, 'UTF-8');
							?>
								<button class="btn btn-info btn-sm btn-ver-cancelacion" 
									data-idsolicitud="<?php echo $solicitud['idsolicitud']; ?>" 
									data-comentario="<?php echo $comentario_escapado; ?>">
									<i class="bi bi-chat-left-text"></i> Ver Motivo de Cancelación
								</button>
							<?php endif; ?>
							<span class="badge bg-secondary py-2">
								<i class="bi bi-archive"></i> Archivado para estadísticas
							</span>
						<?php else: ?>
							<!-- Estados Enviado o Confirmado -->
							<button class="btn btn-primary btn-sm" 
									onclick="verDetalleSolicitudCHC(<?php echo $solicitud['idsolicitud']; ?>)">
								<i class="bi bi-eye"></i> Ver Detalle
							</button>
							
							<?php if($solicitud['idestadoagenda'] == 1): ?>
								<!-- Estado Enviado -->
								<?php if(!$enRevision): ?>
									<!-- Período de edición - puede editar y eliminar -->
									<div class="btn-group" role="group">
										<button class="btn btn-info btn-sm" 
												onclick="editarSolicitudCHC(<?php echo $solicitud['idsolicitud']; ?>)">
											<i class="bi bi-pencil"></i> Editar
										</button>
										<button class="btn btn-warning btn-sm" 
												onclick="eliminarSolicitudCHC(<?php echo $solicitud['idsolicitud']; ?>)">
											<i class="bi bi-trash"></i> Eliminar
										</button>
									</div>
								<?php else: ?>
									<!-- Fuera de período de edición - mostrar si fue enviada dentro o fuera de plazo -->
									<?php 
										$enviadaDentroPlazo = ($solicitud['fecha_registro'] <= $periodoActivo['fin']);
									?>
									<?php if($enviadaDentroPlazo): ?>
										<span class="badge bg-info py-2">
											<i class="bi bi-clock-history"></i> En Revisión - Dentro del Plazo
										</span>
									<?php else: ?>
										<span class="badge bg-warning text-dark py-2">
											<i class="bi bi-exclamation-triangle"></i> En Revisión - Fuera de Plazo
										</span>
									<?php endif; ?>
								<?php endif; ?>
							<?php else: ?>
							<!-- Estado Confirmado (2) - Puede subir cuadrilla -->							
								<?php if($solicitud['total_cuadrillas'] > 0): ?>
									<div class="btn-group" role="group">
										<button class="btn btn-outline-success btn-sm" 
												onclick="subirCuadrillaCHC(<?php echo $solicitud['idsolicitud']; ?>)">
											<i class="bi bi-file-earmark-pdf-fill"></i> Ver Cuadrilla
											<span class="badge bg-success"><?php echo $solicitud['total_cuadrillas']; ?></span>
										</button>
										<button class="btn btn-success btn-sm" 
												onclick="subirNuevaCuadrillaCHC(<?php echo $solicitud['idsolicitud']; ?>)">
											<i class="bi bi-upload"></i> Subir Nueva Cuadrilla
										</button>
									</div>
								<?php else: ?>
								<div class="btn-group" role="group">
									<button class="btn btn-success btn-sm" 
											onclick="subirCuadrillaCHC(<?php echo $solicitud['idsolicitud']; ?>)">
										<i class="bi bi-file-earmark-pdf"></i> Subir Cuadrilla
									</button>
										<a href="uploads/descargar_cuadrilla.php"
										   class="btn btn-info btn-sm" download>
											<i class="bi bi-download"></i> Bajar Formato Cuadrilla
										</a>
									</div>
								<?php endif; ?>
							<?php endif; ?>
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


