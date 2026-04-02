<?php
session_start();
require_once('conexion.php');

// Obtener parámetros
if(isset($_GET['curso'])) {
    $idCurso = intval($_GET['curso']);
    $_SESSION['chc_idcurso'] = $idCurso;
} else {
    if(isset($_SESSION['chc_idcurso'])) {
        $idCurso = $_SESSION['chc_idcurso'];
    } else {
        $idCurso = 0;
    }
}

// Obtener modalidades
$sqlModalidades = "SELECT idmodalidad, modalidad, detalle FROM chc_modalidad ORDER BY idmodalidad";
$resultModalidades = mysqli_query($conn, $sqlModalidades);
$modalidades = array();
while($row = mysqli_fetch_assoc($resultModalidades)) {
    $modalidades[] = $row;
}

// Verificar si ya hay modalidad en sesión
$modalidadPreseleccionada = 0;
if(isset($_SESSION['chc_modalidad'])) {
    $modalidadPreseleccionada = $_SESSION['chc_modalidad'];
}
?>
<style>
    .modalidad-card {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .modalidad-card:hover {
        border-color: #0d6efd;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .modalidad-card.selected {
        border-color: #0d6efd;
        background-color: #e7f3ff;
    }
    .modalidad-card input[type="radio"] {
        transform: scale(1.3);
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
</style>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Indicador de pasos -->
            <div class="step-indicator mb-4">
                <div class="step active">1</div>
                <div class="step">2</div>
                <div class="step">3</div>
            </div>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-1-circle"></i> 
                        Paso 1: Seleccione la Modalidad
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Seleccione la modalidad en la que se realizarán las actividades CHC
                    </p>
                    
                    <form method="POST" id="formModalidad">
                        
                        <?php foreach($modalidades as $modalidad): ?>
                        <div class="modalidad-card">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" 
                                       name="idmodalidad" 
                                       id="modalidad_<?php echo $modalidad['idmodalidad']; ?>" 
                                       value="<?php echo $modalidad['idmodalidad']; ?>"
                                       <?php echo ($modalidadPreseleccionada == $modalidad['idmodalidad']) ? 'checked' : ''; ?>>
                                <label class="form-check-label w-100" for="modalidad_<?php echo $modalidad['idmodalidad']; ?>">
                                    <h5 class="mb-2"><?php echo $modalidad['modalidad']; ?></h5>
                                    <p class="text-muted mb-0">
                                        <small><?php echo $modalidad['detalle']; ?></small>
                                    </p>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="btnSiguiente" 
                                    <?php echo ($modalidadPreseleccionada == 0) ? 'disabled' : ''; ?>>
                                Siguiente: Seleccionar Actividades 
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</div>