<?php
/**
 * CHC - Gestión de Cuadrillas
 * Permite subir documentos PDF de cuadrilla para solicitudes confirmadas (estado 2)
 */
session_start();
require_once('conexion.php');
require_once('chc_correo_cuadrilla.php');

// Configuración de subida
define('UPLOAD_DIR', 'uploads/cuadrillas/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB

// Verificar que existe el directorio de uploads
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Obtener RUT del usuario de sesión

$rutPEC = $_SESSION['sesion_idLogin'];

$rutUsuario = str_pad($rutPEC, 10, "0", STR_PAD_LEFT);

// ==========================================
// PROCESAR SUBIDA VÍA AJAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_cuadrilla'])) {
    header('Content-Type: application/json');
    
    $idSolicitud = isset($_POST['idsolicitud']) ? intval($_POST['idsolicitud']) : 0;
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
    $archivo = $_FILES['pdf_cuadrilla'];
    
    // Validar solicitud
    if ($idSolicitud <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de solicitud inválido']);
        exit;
    }
    
    // Validar que la solicitud existe y está en estado 2
    $sqlVerificar = "SELECT idestadoagenda FROM chc_solicitud WHERE idsolicitud = ?";
    $stmtVerificar = mysqli_prepare($conn, $sqlVerificar);
    mysqli_stmt_bind_param($stmtVerificar, "i", $idSolicitud);
    mysqli_stmt_execute($stmtVerificar);
    $resultVerificar = mysqli_stmt_get_result($stmtVerificar);
    $solicitud = mysqli_fetch_assoc($resultVerificar);
    mysqli_stmt_close($stmtVerificar);
    
    if (!$solicitud || $solicitud['idestadoagenda'] != 2) {
        echo json_encode(['success' => false, 'message' => 'Solicitud no válida o no está confirmada']);
        exit;
    }
    
    // Validar archivo
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $mensajesError = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo del servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir en disco',
        ];
        $mensaje = isset($mensajesError[$archivo['error']]) ? $mensajesError[$archivo['error']] : 'Error desconocido';
        echo json_encode(['success' => false, 'message' => $mensaje]);
        exit;
    }
    
    // Validar tamaño
    if ($archivo['size'] > MAX_FILE_SIZE) {
        echo json_encode(['success' => false, 'message' => 'El archivo excede el tamaño máximo (5 MB)']);
        exit;
    }
    
    // Validar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    if ($mimeType !== 'application/pdf') {
        echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF']);
        exit;
    }
    
    // Generar nombre único
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        echo json_encode(['success' => false, 'message' => 'La extensión debe ser .pdf']);
        exit;
    }
    
    $nombreArchivo = 'cuadrilla_' . $idSolicitud . '_' . date('Ymd_His') . '.pdf';
    $rutaCompleta = UPLOAD_DIR . $nombreArchivo;
    
    // Mover archivo
    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo en el servidor']);
        exit;
    }
    
    // Desactivar cuadrillas anteriores de esta solicitud (la nueva será la válida)
    $sqlDesactivar = "UPDATE chc_cuadrilla_doc SET activo = 0 WHERE idsolicitud = ? AND activo = 1";
    $stmtDesactivar = mysqli_prepare($conn, $sqlDesactivar);
    mysqli_stmt_bind_param($stmtDesactivar, "i", $idSolicitud);
    mysqli_stmt_execute($stmtDesactivar);
    $cuadrillasDesactivadas = mysqli_affected_rows($conn);
    mysqli_stmt_close($stmtDesactivar);
    
    if ($cuadrillasDesactivadas > 0) {
        error_log("CHC Cuadrilla: Se desactivaron $cuadrillasDesactivadas cuadrilla(s) anterior(es) para solicitud $idSolicitud");
    }
    
    // Insertar en base de datos (estado 5 = Subido/OK, activo = 1)
    $sqlInsert = "INSERT INTO chc_cuadrilla_doc 
                  (idsolicitud, ruta_pdf, nombre_archivo, comentario, rut_usuario, fecha_subida, idestadocuadrilla, activo) 
                  VALUES (?, ?, ?, ?, ?, NOW(), 5, 1)";
    $stmtInsert = mysqli_prepare($conn, $sqlInsert);
    $nombreOriginal = $archivo['name'];
    mysqli_stmt_bind_param($stmtInsert, "issss", $idSolicitud, $rutaCompleta, $nombreOriginal, $comentario, $rutUsuario);
    
    if (mysqli_stmt_execute($stmtInsert)) {
        $idCuadrilla = mysqli_insert_id($conn);
        mysqli_stmt_close($stmtInsert);
        
        // Actualizar estado de cuadrilla en la solicitud
        $sqlUpdateSolicitud = "UPDATE chc_solicitud SET idestadocuadrilla = 5 WHERE idsolicitud = ?";
        $stmtUpdate = mysqli_prepare($conn, $sqlUpdateSolicitud);
        mysqli_stmt_bind_param($stmtUpdate, "i", $idSolicitud);
        mysqli_stmt_execute($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        
        // Enviar correo de notificación con el PDF adjunto
        //$correoEnviado = enviarCorreoCuadrillaCHC($conn, $idSolicitud, $rutaCompleta, $nombreOriginal, $comentario, $rutUsuario);
        //
        //echo json_encode([
        //    'success' => true, 
        //    'message' => 'Cuadrilla subida exitosamente',
        //    'idcuadrilla' => $idCuadrilla,
        //    'nombre_archivo' => $nombreOriginal,
        //    'fecha' => date('d/m/Y H:i'),
        //    'correo_enviado' => $correoEnviado
        //]);
		
		// Enviar correo de notificación a gestores/admins CHC
		$correoEnviadoAdmin = enviarCorreoCuadrillaCHC($conn, $idSolicitud, $rutaCompleta, $nombreOriginal, $comentario, $rutUsuario);

		// Enviar correo de notificación al PEC (profesor)
		$nombreGestor = isset($_SESSION['sesion_usuario']) ? $_SESSION['sesion_usuario'] : 'Gestor CHC';
		$correoEnviadoPEC = enviarCorreoCuadrillaPEC($conn, $idSolicitud, $rutaCompleta, $nombreOriginal, $comentario, $nombreGestor);

		// Log de envío de correos
		if ($correoEnviadoAdmin) {
			error_log("CHC Cuadrilla: Correo enviado a administradores - Solicitud $idSolicitud");
		}
		if ($correoEnviadoPEC) {
			error_log("CHC Cuadrilla: Correo enviado al PEC - Solicitud $idSolicitud");
		}

		echo json_encode([
			'success' => true, 
			'message' => 'Cuadrilla subida exitosamente',
			'idcuadrilla' => $idCuadrilla,
			'nombre_archivo' => $nombreOriginal,
			'fecha' => date('d/m/Y H:i'),
			'correo_admin' => $correoEnviadoAdmin,
			'correo_pec' => $correoEnviadoPEC
		]);
		
    } else {
        mysqli_stmt_close($stmtInsert);
        unlink($rutaCompleta); // Eliminar archivo si falló BD
        echo json_encode(['success' => false, 'message' => 'Error al guardar en base de datos']);
    }
    exit;
}

// ==========================================
// MOSTRAR INTERFAZ (GET)
// ==========================================
$idSolicitud = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idSolicitud <= 0) {
    echo '<div style="padding:20px;color:red;">Error: No se especificó la solicitud</div>';
    exit;
}

// Verificar que la solicitud existe y está en estado 2 (Confirmado)
$sqlVerificar = "SELECT s.idsolicitud, s.nombrecurso, s.codigocurso, s.seccion, s.idestadoagenda,
                        m.modalidad
                 FROM chc_solicitud s
                 LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
                 LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
                 WHERE s.idsolicitud = ?";
$stmtVerificar = mysqli_prepare($conn, $sqlVerificar);
mysqli_stmt_bind_param($stmtVerificar, "i", $idSolicitud);
mysqli_stmt_execute($stmtVerificar);
$resultVerificar = mysqli_stmt_get_result($stmtVerificar);
$solicitud = mysqli_fetch_assoc($resultVerificar);
mysqli_stmt_close($stmtVerificar);

if (!$solicitud) {
    echo '<div style="padding:20px;color:red;">Error: Solicitud no encontrada</div>';
    exit;
}

if ($solicitud['idestadoagenda'] != 2) {
    echo '<div style="padding:20px;color:orange;">Esta función solo está disponible para solicitudes confirmadas</div>';
    exit;
}

// Verificar si ya existe una cuadrilla para esta solicitud
$yaTieneCuadrilla = false;
$cuadrillaExistente = null;

// Obtener historial de cuadrillas
$sqlHistorial = "SELECT c.*, e.estado_cuadrilla 
                 FROM chc_cuadrilla_doc c
                 LEFT JOIN chc_estado_cuadrilla e ON c.idestadocuadrilla = e.idestadocuadrilla
                 WHERE c.idsolicitud = ? AND c.activo = 1
                 ORDER BY c.fecha_subida DESC";
$stmtHistorial = mysqli_prepare($conn, $sqlHistorial);
mysqli_stmt_bind_param($stmtHistorial, "i", $idSolicitud);
mysqli_stmt_execute($stmtHistorial);
$resultHistorial = mysqli_stmt_get_result($stmtHistorial);
$cuadrillas = array();
while ($row = mysqli_fetch_assoc($resultHistorial)) {
    $cuadrillas[] = $row;
}
mysqli_stmt_close($stmtHistorial);

// Si ya existe al menos una cuadrilla activa
if (count($cuadrillas) > 0) {
    $yaTieneCuadrilla = true;
    $cuadrillaExistente = $cuadrillas[0]; // La más reciente
}

// Verificar si viene con acción de subir nueva (para reemplazo)
$accionSubir = isset($_GET['accion']) && $_GET['accion'] === 'subir';
$mostrarFormulario = !$yaTieneCuadrilla || $accionSubir;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Cuadrilla</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 15px;
        }
        
        .header-info {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
		
		.header-info2 {
            background: linear-gradient(to right, #6db3f2 0%,#3690f0 44%,#3690f0 44%,#54a3ee 50%,#6db3f2 64%,#6db3f2 67%,#1e69de 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .header-info h5 {
            margin: 0 0 5px 0;
        }
        
        .header-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            background: #fff;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-zone:hover {
            border-color: #28a745;
            background: #f0fff4;
        }
        
        .upload-zone.dragover {
            border-color: #28a745;
            background: #d4edda;
            transform: scale(1.02);
        }
        
        .upload-zone.has-file {
            border-color: #28a745;
            border-style: solid;
            background: #d4edda;
        }
        
        .upload-icon {
            font-size: 4rem;
            color: #adb5bd;
            margin-bottom: 15px;
        }
        
        .upload-zone.has-file .upload-icon {
            color: #28a745;
        }
        
        .file-preview {
            display: none;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .file-preview.show {
            display: block;
        }
        
        .file-preview .file-icon {
            font-size: 2.5rem;
            color: #dc3545;
        }
        
        .historial-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        
        .historial-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .historial-icon {
            font-size: 2rem;
            color: #dc3545;
        }
        
        .btn-upload {
            background: #28a745;
            border: none;
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .btn-upload:hover {
            background: #218838;
        }
        
        .btn-upload:disabled {
            background: #6c757d;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .loading-overlay.show {
            display: flex;
        }
        
        .alert-resultado {
            display: none;
        }
        
        .alert-resultado.show {
            display: block;
        }
    </style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Subiendo...</span>
    </div>
    <p class="mt-3 text-muted">Subiendo archivo...</p>
</div>

<!-- Header -->
<div class="header-info">
    <h5><i class="bi bi-file-earmark-pdf-fill"></i> Cuadrilla - Solicitud #<?php echo $idSolicitud; ?></h5>
    <p><?php echo htmlspecialchars($solicitud['codigocurso'], ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars($solicitud['seccion'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($solicitud['modalidad'], ENT_QUOTES, 'UTF-8'); ?></p>
</div>

<div class="card text-white bg-info ">
    <h5>Solo se admite formato PDF.   </h5> <p>AL guardar el formato de cuadrilla desde Word seleccion PDF o utilice este <a href="https://www.adobe.com/acrobat/online/word-to-pdf.html"
   class="btn btn-primary btn-sm" target="_blank"> 
   <i class="bi bi-upload"></i> Conversor Word a PDF
</a></p>
</div>
</br>
<!-- Mensaje de resultado -->
<div class="alert alert-resultado" id="alertResultado" role="alert"></div>

<?php if ($yaTieneCuadrilla && !$mostrarFormulario): ?>
<!-- ========================================== -->
<!-- VISTA: Ya tiene cuadrilla - Solo visualizar -->
<!-- ========================================== -->
<div class="alert alert-success mb-4">
    <i class="bi bi-check-circle-fill"></i> 
    <strong>Cuadrilla registrada correctamente</strong>
</div>

<div class="card">
    <div class="card-header bg-success text-white">
        <h6 class="mb-0">
            <i class="bi bi-file-earmark-pdf-fill"></i> Documento de Cuadrilla
        </h6>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center">
            <i class="bi bi-file-earmark-pdf-fill me-3" style="font-size: 3rem; color: #dc3545;"></i>
            <div class="flex-grow-1">
                <h6 class="mb-1"><?php echo htmlspecialchars($cuadrillaExistente['nombre_archivo']); ?></h6>
                <small class="text-muted d-block">
                    <i class="bi bi-calendar3"></i> Subido el <?php echo date('d/m/Y', strtotime($cuadrillaExistente['fecha_subida'])); ?> 
                    a las <?php echo date('H:i', strtotime($cuadrillaExistente['fecha_subida'])); ?>
                </small>
                <small class="text-muted d-block">
                    <i class="bi bi-person"></i> Por: <?php echo htmlspecialchars($cuadrillaExistente['rut_usuario']); ?>
                </small>
                <?php if (!empty($cuadrillaExistente['comentario'])): ?>
                <div class="mt-2 p-2 bg-light rounded">
                    <small class="text-muted">
                        <i class="bi bi-chat-left-text"></i> 
                        <em><?php echo htmlspecialchars($cuadrillaExistente['comentario']); ?></em>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <div class="ms-3">
                <span class="badge bg-success mb-2 d-block">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($cuadrillaExistente['estado_cuadrilla']); ?>
                </span>
            </div>
        </div>
        
        <hr>
        
       <div class="d-flex gap-2">
            <a href="<?php echo htmlspecialchars($cuadrillaExistente['ruta_pdf']); ?>" 
               target="_blank" class="btn btn-primary">
                <i class="bi bi-eye"></i> Ver Documento
            </a>
            <a href="<?php echo htmlspecialchars($cuadrillaExistente['ruta_pdf']); ?>" 
               download class="btn btn-outline-success">
                <i class="bi bi-download"></i> Descargar
            </a>
            <a href="chc_cuadrilla.php?id=<?php echo $idSolicitud; ?>&accion=subir" 
               class="btn btn-warning">
                <i class="bi bi-upload"></i> Subir Nueva
            </a>
            <button type="button" class="btn btn-outline-secondary ms-auto" id="btnCancelar">
                <i class="bi bi-x-lg"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ========================================== -->
<!-- VISTA: Sin cuadrilla - Formulario de subida -->
<!-- ========================================== -->

<?php if ($yaTieneCuadrilla && $accionSubir): ?>
<!-- Advertencia de reemplazo -->
<div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i> 
    <strong>Atención:</strong> Ya existe una cuadrilla para esta solicitud. 
    Al subir una nueva, la anterior quedará como histórica y esta será la oficial.
</div>
<?php endif; ?>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Subiendo...</span>
    </div>
    <p class="mt-3 text-muted">Subiendo archivo...</p>
</div>

<!-- Zona de subida -->
<div class="upload-zone" id="uploadZone">
    <i class="bi bi-cloud-arrow-up upload-icon" id="uploadIcon"></i>
    <h5 id="uploadTitle">Arrastra tu archivo PDF aquí</h5>
    <p class="text-muted mb-3" id="uploadSubtitle">o haz clic para seleccionar</p>
    <input type="file" id="inputFile" accept=".pdf,application/pdf" style="display:none;">
    <button type="button" class="btn btn-outline-success" id="btnSeleccionar">
        <i class="bi bi-folder2-open"></i> Seleccionar archivo
    </button>
    <p class="text-muted mt-3 mb-0">
        <small><i class="bi bi-info-circle"></i> Máximo 5 MB, solo archivos PDF</small>
    </p>
</div>

<!-- Preview del archivo -->
<div class="file-preview" id="filePreview">
    <div class="d-flex align-items-center">
        <i class="bi bi-file-earmark-pdf-fill file-icon me-3"></i>
        <div class="flex-grow-1">
            <strong id="fileName">documento.pdf</strong>
            <br>
            <small class="text-muted" id="fileSize">0 KB</small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveFile">
            <i class="bi bi-x-lg"></i> Quitar
        </button>
    </div>
</div>

<!-- Comentario -->
<div class="mt-3">
    <label for="comentario" class="form-label">
        <i class="bi bi-chat-left-text"></i> Comentario (opcional)
    </label>
    <textarea id="comentario" class="form-control" rows="2" 
              placeholder="Agregue observaciones sobre este documento..."></textarea>
</div>

<!-- Botones -->
<div class="d-flex gap-2 mt-3">
    <button type="button" class="btn btn-success btn-upload" id="btnSubir" disabled>
        <i class="bi bi-cloud-upload"></i> Subir Cuadrilla
    </button>
    <button type="button" class="btn btn-outline-secondary" id="btnCancelar">
        <i class="bi bi-x-lg"></i> Cerrar
    </button>
</div>

<?php endif; ?>

<script>
(function() {
    var btnCancelar = document.getElementById('btnCancelar');
    
    // Evento para cerrar (siempre disponible)
    if (btnCancelar) {
        btnCancelar.addEventListener('click', function() {
            if (window.parent && window.parent.Swal) {
                window.parent.Swal.close();
            } else if (typeof Swal !== 'undefined') {
                Swal.close();
            } else {
                window.close();
            }
        });
    }
    
<?php if ($mostrarFormulario): ?>
    // ==========================================
    // CÓDIGO DE SUBIDA (solo si NO tiene cuadrilla)
    // ==========================================
    var uploadZone = document.getElementById('uploadZone');
    var inputFile = document.getElementById('inputFile');
    var filePreview = document.getElementById('filePreview');
    var fileName = document.getElementById('fileName');
    var fileSize = document.getElementById('fileSize');
    var btnSeleccionar = document.getElementById('btnSeleccionar');
    var btnRemoveFile = document.getElementById('btnRemoveFile');
    var btnSubir = document.getElementById('btnSubir');
    var comentario = document.getElementById('comentario');
    var loadingOverlay = document.getElementById('loadingOverlay');
    var alertResultado = document.getElementById('alertResultado');
    var uploadIcon = document.getElementById('uploadIcon');
    var uploadTitle = document.getElementById('uploadTitle');
    var uploadSubtitle = document.getElementById('uploadSubtitle');
    
    var idSolicitud = <?php echo $idSolicitud; ?>;
    var archivoSeleccionado = null;
    
    // EVENTOS DRAG & DROP
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
        uploadZone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });
    
    ['dragenter', 'dragover'].forEach(function(eventName) {
        uploadZone.addEventListener(eventName, function() {
            uploadZone.classList.add('dragover');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(function(eventName) {
        uploadZone.addEventListener(eventName, function() {
            uploadZone.classList.remove('dragover');
        }, false);
    });
    
    uploadZone.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        if (files.length > 0) {
            procesarArchivo(files[0]);
        }
    }, false);
    
    // EVENTOS CLICK
    uploadZone.addEventListener('click', function(e) {
        if (e.target === btnSeleccionar || e.target.closest('#btnSeleccionar')) {
            return;
        }
        inputFile.click();
    });
    
    btnSeleccionar.addEventListener('click', function(e) {
        e.stopPropagation();
        inputFile.click();
    });
    
    inputFile.addEventListener('change', function() {
        if (this.files.length > 0) {
            procesarArchivo(this.files[0]);
        }
    });
    
    btnRemoveFile.addEventListener('click', limpiarArchivo);
    btnSubir.addEventListener('click', subirArchivo);
    
    // FUNCIONES
    function procesarArchivo(file) {
        if (file.type !== 'application/pdf') {
            mostrarError('Solo se permiten archivos PDF');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            mostrarError('El archivo excede el tamaño máximo de 5 MB');
            return;
        }
        
        archivoSeleccionado = file;
        
        fileName.textContent = file.name;
        fileSize.textContent = formatBytes(file.size);
        filePreview.classList.add('show');
        
        uploadZone.classList.add('has-file');
        uploadIcon.className = 'bi bi-file-earmark-pdf-fill upload-icon';
        uploadTitle.textContent = file.name;
        uploadSubtitle.textContent = formatBytes(file.size);
        btnSeleccionar.style.display = 'none';
        
        btnSubir.disabled = false;
        alertResultado.classList.remove('show');
    }
    
    function limpiarArchivo() {
        archivoSeleccionado = null;
        inputFile.value = '';
        
        filePreview.classList.remove('show');
        
        uploadZone.classList.remove('has-file');
        uploadIcon.className = 'bi bi-cloud-arrow-up upload-icon';
        uploadTitle.textContent = 'Arrastra tu archivo PDF aquí';
        uploadSubtitle.textContent = 'o haz clic para seleccionar';
        btnSeleccionar.style.display = 'inline-block';
        
        btnSubir.disabled = true;
    }
    
    function subirArchivo() {
        if (!archivoSeleccionado) {
            mostrarError('Selecciona un archivo primero');
            return;
        }
        
        loadingOverlay.classList.add('show');
        btnSubir.disabled = true;
        
        var formData = new FormData();
        formData.append('pdf_cuadrilla', archivoSeleccionado);
        formData.append('idsolicitud', idSolicitud);
        formData.append('comentario', comentario.value.trim());
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'chc_cuadrilla.php', true);
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                loadingOverlay.classList.remove('show');
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
    // Ocultar formulario y mostrar éxito grande
    var uploadZoneEl = document.getElementById('uploadZone');
    var filePreviewEl = document.getElementById('filePreview');
    var comentarioContainer = document.querySelector('.mt-3');
    var botonesContainer = document.querySelector('.d-flex.gap-2.mt-3');
    
    if(uploadZoneEl) uploadZoneEl.style.display = 'none';
    if(filePreviewEl) filePreviewEl.style.display = 'none';
    if(comentarioContainer) comentarioContainer.style.display = 'none';
    if(botonesContainer) botonesContainer.style.display = 'none';
    
    // Mostrar mensaje de éxito grande
    alertResultado.className = 'alert alert-success alert-resultado show text-center';
    alertResultado.innerHTML = 
        '<div style="padding: 30px 0;">' +
            '<i class="bi bi-check-circle-fill" style="font-size: 4rem; color: #28a745;"></i>' +
            '<h4 class="mt-3 mb-2">¡Cuadrilla subida exitosamente!</h4>' +
            '<p class="text-muted mb-0">El documento ha sido registrado correctamente.</p>' +
            '<p class="text-muted"><small>Cerrando en un momento...</small></p>' +
        '</div>';
    
    // Notificar al padre para cerrar modal y refrescar vista CHC
    setTimeout(function() {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage('cuadrilla_subida', '*');
        }
    }, 1500);
} else {
                            mostrarError(response.message);
                            btnSubir.disabled = false;
                        }
                    } catch(e) {
                        mostrarError('Error al procesar la respuesta del servidor');
                        btnSubir.disabled = false;
                    }
                } else {
                    mostrarError('Error de conexión con el servidor');
                    btnSubir.disabled = false;
                }
            }
        };
        
        xhr.send(formData);
    }
    
    function mostrarError(mensaje) {
        alertResultado.className = 'alert alert-danger alert-resultado show';
        alertResultado.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + mensaje;
    }
    
    function mostrarExito(mensaje) {
        alertResultado.className = 'alert alert-success alert-resultado show';
        alertResultado.innerHTML = '<i class="bi bi-check-circle"></i> ' + mensaje;
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
<?php endif; ?>
})();
</script>

</body>
</html>