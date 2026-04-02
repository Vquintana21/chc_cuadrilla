<?php
session_start();
require_once('conexion.php');

// Validar que venimos del paso 1
if(!isset($_SESSION['chc_modalidad']) || !isset($_SESSION['chc_idcurso'])) {
    echo '<div class="alert alert-danger">Error: Debe iniciar desde el paso 1</div>';
    exit;
}

$idCurso = $_SESSION['chc_idcurso'];
$idModalidad = $_SESSION['chc_modalidad'];

// Obtener info de la modalidad seleccionada
$sqlModalidad = "SELECT modalidad, detalle FROM chc_modalidad WHERE idmodalidad = ?";
$stmtModalidad = mysqli_prepare($conn, $sqlModalidad);
mysqli_stmt_bind_param($stmtModalidad, "i", $idModalidad);
mysqli_stmt_execute($stmtModalidad);
$resultModalidad = mysqli_stmt_get_result($stmtModalidad);
$modalidadInfo = mysqli_fetch_assoc($resultModalidad);

if(!$modalidadInfo) {
    echo '<div class="alert alert-danger">Error: Modalidad no encontrada</div>';
    exit;
}
?>
<style>
    .toggle-switch {
        position: relative;
        width: 50px;
        height: 24px;
        background-color: #ccc;
        border-radius: 12px;
        cursor: pointer;
        display: inline-block;
        transition: background-color 0.3s;
    }
    
    .toggle-switch::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: white;
        top: 2px;
        left: 2px;
        transition: transform 0.3s;
    }
    
    input[type="checkbox"] {
        display: none;
    }
    
    input[type="checkbox"]:checked + .toggle-switch {
        background-color: #2196F3;
    }
    
    input[type="checkbox"]:checked + .toggle-switch::after {
        transform: translateX(26px);
    }
    
    /* ✅ NUEVO: Estilos para checkboxes deshabilitados */
    input[type="checkbox"]:disabled + .toggle-switch {
        background-color: #e0e0e0;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    tr.actividad-usada {
        background-color: #f8f9fa;
        opacity: 0.7;
    }
    
    tr.actividad-usada td {
        color: #6c757d;
    }
    
    .step-indicator {
        display: flex;
        justify-content: center;
        margin-bottom: 30px;
    }
    .step {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #dee2e6;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin: 0 10px;
        position: relative;
    }
    .step.active {
        background: #0d6efd;
        color: white;
    }
    .step.completed {
        background: #28a745;
        color: white;
    }
    .step::before {
        content: '';
        position: absolute;
        width: 40px;
        height: 2px;
        background: #dee2e6;
        right: 100%;
        top: 50%;
        transform: translateY(-50%);
    }
    .step:first-child::before {
        display: none;
    }
    #tablaActividadesCHC {
        border-collapse: collapse;
        width: 100%;
    }
    #tablaActividadesCHC th {
        background-color: #f8f9fa;
        padding: 12px;
        border-bottom: 2px solid #dee2e6;
    }
    #tablaActividadesCHC td {
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
    }
    #tablaActividadesCHC tr:hover:not(.actividad-usada) {
        background-color: #f8f9fa;
    }
</style>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- Indicador de pasos -->
            <div class="step-indicator mb-4">
                <div class="step completed">1</div>
                <div class="step active">2</div>
                <div class="step">3</div>
            </div>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-2-circle"></i> 
                        Paso 2: Seleccione las Actividades
                    </h4>
                </div>
                <div class="card-body">
                    
                    <!-- Resumen modalidad -->
                    <div class="alert alert-info mb-4">
                        <strong>Modalidad seleccionada:</strong> 
                        <?php echo $modalidadInfo['modalidad']; ?>
                        <br>
                        <small><?php echo $modalidadInfo['detalle']; ?></small>
                    </div>
                    
                    <p class="text-muted mb-3">
                        Seleccione las actividades CHC que se realizarán en esta modalidad. 
                        <strong>Por defecto están todas marcadas.</strong>
                    </p>
                    
                    <!-- ✅ LEYENDA ACTUALIZADA -->
<div class="alert alert-light border mb-3">
    <div class="row">
        <div class="col-md-4">
            <i class="bi bi-check-circle text-success"></i> 
            <small><strong>Disponibles:</strong> Puede seleccionarlas</small>
        </div>
        <div class="col-md-4">
            <i class="bi bi-arrow-clockwise text-warning"></i> 
            <small><strong>Canceladas:</strong> Disponibles nuevamente</small>
        </div>
        <div class="col-md-4">
            <i class="bi bi-lock-fill text-secondary"></i> 
            <small><strong>Con solicitud:</strong> Ya tienen solicitud activa</small>
        </div>
    </div>
</div>
                    
                    <form method="POST" id="formActividades">
					 <!-- ========== NUEVO: Botón Seleccionar/Deseleccionar Todas  text-end ========== -->
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnToggleAll" onclick="toggleAllActividades()">
                                <i class="bi bi-check-all" id="iconToggle"></i> <span id="txtToggle">Deseleccionar Todas</span>
                            </button>
                        </div>
                        <!-- ========== FIN NUEVO ========== -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="tablaActividadesCHC">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;" class="text-center"></th>
                                        <th>Fecha</th>
                                        <th>Día</th>
                                        <th>Hora Inicio</th>
                                        <th>Hora Término</th>
                                        <th>Actividad</th>
                                        <th>Tipo de Actividad</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyActividades">
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Cargando...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div id="contadorActividades" class="alert alert-light text-center mt-3" style="display: none;">
                            <strong><span id="numSeleccionadas">0</span></strong> actividades seleccionadas
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="volverPaso1CHC()">
                                <i class="bi bi-arrow-left"></i> Volver
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg" id="btnSiguiente" disabled>
                                Siguiente: Completar Agenda 
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</div>

