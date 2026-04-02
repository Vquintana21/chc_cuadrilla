<?php
// chc_guardar_solicitud.php
header('Content-Type: application/json; charset=utf-8');

// Incluir archivo de conexión
require_once('conexion.php');
require_once('chc_correo_soli_enviada.php');

// Iniciar sesión
session_start();

// Inicializar respuesta
$response = array(
    'success' => false,
    'message' => '',
    'idsolicitud' => null,
    'debug' => array()
);

try {
    // ===== VALIDAR CONEXIÓN =====
    if(!$conn) {
        throw new Exception('No hay conexión a la base de datos');
    }
	
	mysqli_set_charset($conn, "utf8");
		if(isset($conexion3)) {
			mysqli_set_charset($conexion3, "utf8");
		}
    
    // ===== OBTENER DATOS DE SESIÓN =====
    if(!isset($_SESSION['chc_modalidad'])) {
        throw new Exception('No se encontró la modalidad en sesión. Por favor inicie el proceso nuevamente.');
    }
    
    if(!isset($_SESSION['chc_actividades']) || empty($_SESSION['chc_actividades'])) {
        throw new Exception('No se encontraron actividades seleccionadas. Por favor seleccione al menos una actividad.');
    }
    
    if(!isset($_SESSION['chc_idcurso'])) {
        throw new Exception('No se encontró el ID del curso en sesión.');
    }
    
    $idModalidad = intval($_SESSION['chc_modalidad']);
    $actividadesSeleccionadas = $_SESSION['chc_actividades'];
    $idCurso = intval($_SESSION['chc_idcurso']);
    
    // ===== ✅ NUEVA LÓGICA: Verificar si alguna actividad es Fantoma =====
    $tieneActividadFantoma = false;
	
	// ===== obtener periodo =====		
	$actual="SELECT periodo FROM periodo WHERE activo=1";
	$res_actual = mysqli_query($conn,$actual);
	$ok_actual = mysqli_fetch_assoc($res_actual);
	$periodo=$ok_actual['periodo'];
	
    
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
        
        error_log("CHC Guardar - Modalidad: $idModalidad, Tiene Fantoma: " . ($tieneActividadFantoma ? 'SÍ' : 'NO'));
    }
    
    // ===== OBTENER DATOS DEL FORMULARIO =====
    
    // Campos comunes a todas las modalidades
    $npacientes = isset($_POST['npacientes']) ? $_POST['npacientes'] : '';
    $nestudiantesxsesion = isset($_POST['nestudiantesxsesion']) ? intval($_POST['nestudiantesxsesion']) : 0;
    $comentarios = isset($_POST['comentarios']) ? trim($_POST['comentarios']) : '';
    
    // Si npacientes es "mayor_12", tomar el valor de npacientes_otro
    if($npacientes === 'mayor_12') {
        $npacientes = isset($_POST['npacientes_otro']) ? intval($_POST['npacientes_otro']) : 0;
    } else {
        $npacientes = intval($npacientes);
    }
    
   // Campos específicos según modalidad
$uso_fantoma = 0;
$fantoma_capacitado = 0;
$fantoma_fecha_capacitacion = null;
$fantoma_hora_capacitacion = null;
$nboxes = 0;
$uso_debriefing = 0;
$espacio_requerido_otros = '';

if($idModalidad == 1) {
    // ===== MODALIDAD PRESENCIAL =====
    
    // ===== ✅ NUEVA LÓGICA: Si tiene actividades Fantoma, uso_fantoma = 1 SIEMPRE =====
    if($tieneActividadFantoma) {
        // ✅ Uso de Fantoma = 1 automático
        $uso_fantoma = 1;
        
        // Obtener si está capacitado
        $fantoma_capacitado = isset($_POST['fantoma_capacitado']) ? intval($_POST['fantoma_capacitado']) : 0;
        
     // Si NO está capacitado, obtener fechas de capacitación
if($fantoma_capacitado == 0) {
    $fantoma_fecha_capacitacion = isset($_POST['fantoma_fecha_capacitacion']) && !empty($_POST['fantoma_fecha_capacitacion']) 
        ? $_POST['fantoma_fecha_capacitacion'] 
        : null;
    $fantoma_hora_capacitacion = isset($_POST['fantoma_hora_capacitacion']) && !empty($_POST['fantoma_hora_capacitacion']) 
        ? $_POST['fantoma_hora_capacitacion'] 
        : null;
    
    // ✅ DEBUG: Ver qué llega exactamente (compatible con PHP 5.6)
    error_log("=== DEBUG FECHAS ===");
    error_log("POST raw fecha: " . var_export(isset($_POST['fantoma_fecha_capacitacion']) ? $_POST['fantoma_fecha_capacitacion'] : 'NO EXISTE', true));
    error_log("POST raw hora: " . var_export(isset($_POST['fantoma_hora_capacitacion']) ? $_POST['fantoma_hora_capacitacion'] : 'NO EXISTE', true));
    error_log("Variable fecha despues de capturar: " . var_export($fantoma_fecha_capacitacion, true));
    error_log("Variable hora despues de capturar: " . var_export($fantoma_hora_capacitacion, true));
    error_log("Tipo de dato fecha: " . gettype($fantoma_fecha_capacitacion));
    error_log("Tipo de dato hora: " . gettype($fantoma_hora_capacitacion));
}
        
        error_log("✅ Procesando Fantoma - Uso: 1 (automático), Capacitado: $fantoma_capacitado");
    } else {
        // ✅ NO tiene actividades Fantoma: Valores en 0 y null
        $uso_fantoma = 0;
        $fantoma_capacitado = 0;
        $fantoma_fecha_capacitacion = null;
        $fantoma_hora_capacitacion = null;
        error_log("⚠️ NO tiene actividades Fantoma - Valores en 0/null");
    }
        
        $nboxes = isset($_POST['nboxes']) ? $_POST['nboxes'] : '';
        
        if($nboxes === 'mayor_12') {
            $nboxes = isset($_POST['nboxes_otro']) ? intval($_POST['nboxes_otro']) : 0;
        } else {
            $nboxes = intval($nboxes);
        }
        
        $uso_debriefing = isset($_POST['uso_debriefing']) ? intval($_POST['uso_debriefing']) : 0;
        
    } elseif($idModalidad == 2) {
        // ===== MODALIDAD VIRTUAL =====
        // ✅ Para Virtual: Fantoma siempre en 0
        $uso_fantoma = 0;
        $fantoma_capacitado = 0;
        $espacio_requerido_otros = isset($_POST['espacio_requerido_otros']) ? trim($_POST['espacio_requerido_otros']) : '';
        
    } elseif($idModalidad == 3) {
        // ===== MODALIDAD EXTERIOR =====
        // ✅ Para Exterior: Fantoma siempre en 0
        $uso_fantoma = 0;
        $fantoma_capacitado = 0;
        $espacio_requerido_otros = isset($_POST['espacio_requerido_otros']) ? trim($_POST['espacio_requerido_otros']) : '';
    }
    
    // ===== VALIDACIONES =====
    if($nestudiantesxsesion <= 0) {
        throw new Exception('Debe especificar el número de estudiantes por sesión');
    }
    
    if($npacientes <= 0) {
        throw new Exception('Debe especificar el número de pacientes simulados');
    }
    
    if($idModalidad == 1 && $nboxes <= 0) {
        throw new Exception('Debe especificar el número de boxes para modalidad presencial');
    }
    
    if(($idModalidad == 2 || $idModalidad == 3) && empty($espacio_requerido_otros)) {
        throw new Exception('Debe describir el espacio requerido para esta modalidad');
    }
    
    // ===== DEBUG: LOG DE DATOS RECIBIDOS =====
    $response['debug']['datos_formulario'] = array(
        'idModalidad' => $idModalidad,
        'tieneActividadFantoma' => $tieneActividadFantoma,
        'npacientes' => $npacientes,
        'nestudiantesxsesion' => $nestudiantesxsesion,
        'nboxes' => $nboxes,
        'uso_fantoma' => $uso_fantoma,
        'fantoma_capacitado' => $fantoma_capacitado,
        'uso_debriefing' => $uso_debriefing,
        'espacio_requerido_otros' => $espacio_requerido_otros,
        'comentarios' => $comentarios,
        'actividades_count' => count($actividadesSeleccionadas)
    );
    
    // ===== OBTENER DATOS DEL CURSO Y USUARIO =====
    $sqlCurso = "SELECT c.CodigoCurso, c.Seccion, r.NombreCurso, c.idperiodo, r.Responsable 
                 FROM spre_cursos c
                 LEFT JOIN spre_ramos r ON c.CodigoCurso = r.CodigoCurso
                 WHERE c.idCurso = ?";
    
    $stmtCurso = mysqli_prepare($conexion3, $sqlCurso);
    
    if($stmtCurso === false) {
        throw new Exception('Error al preparar consulta de curso: ' . mysqli_error($conexion3));
    }
    
    mysqli_stmt_bind_param($stmtCurso, "i", $idCurso);
    
    if(!mysqli_stmt_execute($stmtCurso)) {
        throw new Exception('Error al ejecutar consulta de curso: ' . mysqli_stmt_error($stmtCurso));
    }
    
    $resultCurso = mysqli_stmt_get_result($stmtCurso);
    $dataCurso = mysqli_fetch_assoc($resultCurso);
    
    mysqli_stmt_close($stmtCurso);
    
    if(!$dataCurso) {
        throw new Exception('No se encontró información del curso');
    }
    
    error_log("Curso encontrado: " . $dataCurso['CodigoCurso'] . " - Sección: " . $dataCurso['Seccion']);
    
    // Obtener RUT del usuario en sesión
    $rutPECX = isset($_SESSION['sesion_idLogin']) ? $_SESSION['sesion_idLogin'] : '';
	$rutPEC = str_pad($rutPECX, 10, "0", STR_PAD_LEFT);
    
    // Obtener email del usuario
    // Obtener email y nombre del usuario
$sqlUsuario = "SELECT 
	COALESCE(NULLIF(EmailReal, ''), Email) AS Email,
    CONCAT(Nombres, ' ', Paterno, ' ', IFNULL(Materno, '')) as nombre_completo
FROM spre_personas 
WHERE Rut = ?";
$stmtUsuario = mysqli_prepare($conexion3, $sqlUsuario);
    
    if($stmtUsuario === false) {
        throw new Exception('Error al preparar consulta de usuario: ' . mysqli_error($conexion3));
    }
    
    mysqli_stmt_bind_param($stmtUsuario, "s", $rutPEC);
    mysqli_stmt_execute($stmtUsuario);
    $resultUsuario = mysqli_stmt_get_result($stmtUsuario);
    $dataUsuario = mysqli_fetch_assoc($resultUsuario);
	$correoPEC = $dataUsuario ? $dataUsuario['Email'] : '';
	$nombrePEC = $dataUsuario ? trim($dataUsuario['nombre_completo']) : '';

	mysqli_stmt_close($stmtUsuario);

	error_log("Usuario: RUT=$rutPEC, Email=$correoPEC, Nombre=$nombrePEC");
    
    // ===== INICIAR TRANSACCIÓN =====
    mysqli_begin_transaction($conn);
    
    // ===== PASO 1: INSERTAR EN chc_solicitud =====
    // NOTA: NO incluir idmodalidad (está en chc_solicitud_modalidad)
    $carrera = ''; // carrera vacío por ahora
    
    $sqlInsertSolicitud = "INSERT INTO chc_solicitud (
        idcurso,
		periodo,
        carrera,
        codigocurso,
        seccion,
        nombrecurso,
        rutpec,
		nombrepec,
        correopec,
        uso_fantoma,
		fantoma_capacitado,
		fantoma_fecha_capacitacion,
		fantoma_hora_capacitacion,
        npacientes,
        nestudiantesxsesion,
        nboxes,
        espacio_requerido_otros,
        uso_debriefing,
        comentarios,
        idestadoagenda,
        idestadocuadrilla,
        fecha_registro
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())";
    
    $stmtSolicitud = mysqli_prepare($conn, $sqlInsertSolicitud);
    
    if($stmtSolicitud === false) {
        throw new Exception('Error al preparar inserción de solicitud: ' . mysqli_error($conn));
    }
    
    error_log("=== ANTES DE BIND_PARAM ===");
error_log("11. fantoma_fecha_capacitacion: '" . $fantoma_fecha_capacitacion . "' (tipo: " . gettype($fantoma_fecha_capacitacion) . ")");
error_log("12. fantoma_hora_capacitacion: '" . $fantoma_hora_capacitacion . "' (tipo: " . gettype($fantoma_hora_capacitacion) . ")");

mysqli_stmt_bind_param(
        $stmtSolicitud,
        "isssssssssisssiisis",
        $idCurso,
		$periodo,
        $dataCurso['Responsable'],
        $dataCurso['CodigoCurso'],
        $dataCurso['Seccion'],
        $dataCurso['NombreCurso'],
        $rutPEC,
		$nombrePEC,
        $correoPEC,
        $uso_fantoma,
        $fantoma_capacitado,
		$fantoma_fecha_capacitacion, 
        $fantoma_hora_capacitacion,
        $npacientes,
        $nestudiantesxsesion,
        $nboxes,
        $espacio_requerido_otros,
        $uso_debriefing,
        $comentarios
    );
    
    if(!mysqli_stmt_execute($stmtSolicitud)) {
        throw new Exception('Error al guardar solicitud: ' . mysqli_stmt_error($stmtSolicitud));
    }
    
    $idSolicitud = mysqli_insert_id($conn);
    
    if($idSolicitud <= 0) {
        throw new Exception('Error al obtener ID de la solicitud insertada');
    }
    
    mysqli_stmt_close($stmtSolicitud);
    
    $response['debug']['idsolicitud_generado'] = $idSolicitud;
    error_log("Solicitud insertada con ID: $idSolicitud");
    
    // ===== PASO 1.5: INSERTAR MODALIDAD EN chc_solicitud_modalidad =====
    $sqlInsertModalidad = "INSERT INTO chc_solicitud_modalidad (
        idsolicitud,
        idmodalidad
    ) VALUES (?, ?)";
    
    $stmtModalidad = mysqli_prepare($conn, $sqlInsertModalidad);
    
    if($stmtModalidad === false) {
        throw new Exception('Error al preparar inserción de modalidad: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmtModalidad, "ii", $idSolicitud, $idModalidad);
    
    if(!mysqli_stmt_execute($stmtModalidad)) {
        throw new Exception('Error al guardar modalidad: ' . mysqli_stmt_error($stmtModalidad));
    }
    
    mysqli_stmt_close($stmtModalidad);
    
    $response['debug']['modalidad_guardada'] = $idModalidad;
    error_log("Modalidad insertada: $idModalidad");
    
    // ===== PASO 2: INSERTAR ACTIVIDADES =====
    $sqlInsertActividad = "INSERT INTO chc_solicitud_actividad (
        idsolicitud,
        idplanclases
    ) VALUES (?, ?)";
    
    $stmtActividad = mysqli_prepare($conn, $sqlInsertActividad);
    
    if($stmtActividad === false) {
        throw new Exception('Error al preparar inserción de actividades: ' . mysqli_error($conn));
    }
    
    $actividadesGuardadas = 0;
    
    foreach($actividadesSeleccionadas as $idPlanClase) {
        $idPlanClaseInt = intval($idPlanClase);
        
        mysqli_stmt_bind_param($stmtActividad, "ii", $idSolicitud, $idPlanClaseInt);
        
        if(!mysqli_stmt_execute($stmtActividad)) {
            throw new Exception('Error al guardar actividad ' . $idPlanClaseInt . ': ' . mysqli_stmt_error($stmtActividad));
        }
        
        $actividadesGuardadas++;
    }
    
    mysqli_stmt_close($stmtActividad);
    
    $response['debug']['actividades_guardadas'] = $actividadesGuardadas;
    
    // ===== PASO 3: INSERTAR ESPACIOS (solo para modalidad Presencial) =====
    if($idModalidad == 1) {
        $espaciosGuardados = 0;
        
        // Array de espacios posibles
        $espaciosDisponibles = array(
            'espacio_salas_atencion' => 1,
            'espacio_salas_procedimientos' => 2,
            'espacio_mesa_buzon' => 3,
            'espacio_voz_off' => 4,
            'espacio_otros' => 5
        );
        
        $sqlInsertEspacio = "INSERT INTO chc_solicitud_espacio (
            idsolicitud,
            idespacio,
            otro
        ) VALUES (?, ?, ?)";
        
        $stmtEspacio = mysqli_prepare($conn, $sqlInsertEspacio);
        
        if($stmtEspacio === false) {
            throw new Exception('Error al preparar inserción de espacios: ' . mysqli_error($conn));
        }
        
        foreach($espaciosDisponibles as $campo => $idEspacio) {
            if(isset($_POST[$campo]) && $_POST[$campo] == '1') {
                $otroDetalle = '';
                
                // Si es "Otros", obtener el detalle
                if($campo === 'espacio_otros' && isset($_POST['espacio_otros_detalle'])) {
                    $otroDetalle = trim($_POST['espacio_otros_detalle']);
                }
                
                mysqli_stmt_bind_param($stmtEspacio, "iis", $idSolicitud, $idEspacio, $otroDetalle);
                
                if(!mysqli_stmt_execute($stmtEspacio)) {
                    throw new Exception('Error al guardar espacio ' . $idEspacio . ': ' . mysqli_stmt_error($stmtEspacio));
                }
                
                $espaciosGuardados++;
            }
        }
        
        mysqli_stmt_close($stmtEspacio);
        
        $response['debug']['espacios_guardados'] = $espaciosGuardados;
    }
    
    // ===== CONFIRMAR TRANSACCIÓN =====
    mysqli_commit($conn);
	
	try {
    // Reabrir conexión para el correo (se cerró implícitamente)
		require_once('conexion.php');
		enviarCorreoSolicitudEnviada($conn, $idSolicitud);
		error_log("CHC: Correo de solicitud enviada - ID: $idSolicitud");
	} catch (Exception $e) {
		// No fallar si el correo no se envía, la solicitud ya está guardada
		error_log("CHC: Error al enviar correo de solicitud - " . $e->getMessage());
	}
    
    // ===== LIMPIAR SESIÓN =====
    unset($_SESSION['chc_modalidad']);
    unset($_SESSION['chc_actividades']);
    
    // ===== RESPUESTA EXITOSA =====
    $response['success'] = true;
    $response['message'] = 'Solicitud CHC guardada correctamente';
    $response['idsolicitud'] = $idSolicitud;
    
    // Log de éxito
    error_log("CHC Solicitud guardada exitosamente - ID: $idSolicitud, Actividades: $actividadesGuardadas, Tiene Fantoma: " . ($tieneActividadFantoma ? 'SÍ' : 'NO'));
    
} catch(Exception $e) {
    // ROLLBACK en caso de error
    if($conn) {
        mysqli_rollback($conn);
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Log del error
    error_log("Error al guardar solicitud CHC: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}

// Cerrar conexión
if($conn) {
    mysqli_close($conn);
}

// Devolver respuesta JSON
echo json_encode($response);
?>