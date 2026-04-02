<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once('conexion.php');

$response = array('success' => false, 'message' => '', 'data' => null);

try {
    if(!isset($_POST['idsolicitud'])) {
        throw new Exception('ID de solicitud no especificado');
    }
    
    $idSolicitud = intval($_POST['idsolicitud']);
    
    if($idSolicitud <= 0) {
        throw new Exception('ID de solicitud inválido');
    }
    
    // ===== DEBUG =====
    error_log("===== CHC OBTENER DETALLE =====");
    error_log("ID Solicitud: $idSolicitud");
    
    // ===== OBTENER INFORMACIÓN GENERAL =====
    $sqlGeneral = "
        SELECT 
            s.idsolicitud,
            s.idcurso,
            s.codigocurso,
            s.seccion,
            s.nombrecurso,
            s.rutpec,
			s.nombrepec,
            s.correopec,
            s.fecha_registro,
            m.idmodalidad,
            m.modalidad,
            s.uso_fantoma,
            s.fantoma_capacitado,
            s.npacientes,
            s.nestudiantesxsesion,
            s.nboxes,
            s.espacio_requerido_otros,
            s.uso_debriefing,
            s.comentarios,
			s.uso_debriefing,
			s.comentarios,
			s.fantoma_fecha_capacitacion,
			s.fantoma_hora_capacitacion
        FROM chc_solicitud s
        LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
        LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
        WHERE s.idsolicitud = ?
    ";
    
    // ✅ USAR LA CONEXIÓN CORRECTA (ajusta según tu conexion.php)
    $stmtGeneral = mysqli_prepare($conn, $sqlGeneral);
    if(!$stmtGeneral) {
        error_log("Error al preparar consulta general: " . mysqli_error($conn));
        throw new Exception('Error al preparar consulta: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmtGeneral, "i", $idSolicitud);
    mysqli_stmt_execute($stmtGeneral);
    $resultGeneral = mysqli_stmt_get_result($stmtGeneral);
    $general = mysqli_fetch_assoc($resultGeneral);
    
    if(!$general) {
        throw new Exception('Solicitud no encontrada');
    }
    
    error_log("Modalidad: " . $general['idmodalidad'] . " - " . $general['modalidad']);
    mysqli_stmt_close($stmtGeneral);
    
    // ===== OBTENER ACTIVIDADES =====
    $sqlActividades = "
        SELECT 
            p.idplanclases,
            p.pcl_tituloActividad,
            DATE_FORMAT(p.pcl_Fecha, '%d/%m/%Y') as fecha_formateada,
            p.dia,
            p.pcl_Inicio,
            p.pcl_Termino,
            p.pcl_TipoSesion,
            p.pcl_SubTipoSesion
        FROM chc_solicitud_actividad sa
        INNER JOIN planclases p ON sa.idplanclases = p.idplanclases
        WHERE sa.idsolicitud = ?
        ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC
    ";
    
    $stmtActividades = mysqli_prepare($conn, $sqlActividades);
    if(!$stmtActividades) {
        error_log("Error al preparar consulta actividades: " . mysqli_error($conn));
        throw new Exception('Error al preparar consulta de actividades');
    }
    
    mysqli_stmt_bind_param($stmtActividades, "i", $idSolicitud);
    mysqli_stmt_execute($stmtActividades);
    $resultActividades = mysqli_stmt_get_result($stmtActividades);
    $actividades = array();
    
    while($row = mysqli_fetch_assoc($resultActividades)) {
        $actividades[] = array(
            'idplanclases' => $row['idplanclases'],
            'titulo' => $row['pcl_tituloActividad'],
            'fecha' => $row['fecha_formateada'],
            'dia' => $row['dia'],
            'hora_inicio' => substr($row['pcl_Inicio'], 0, 5),
            'hora_termino' => substr($row['pcl_Termino'], 0, 5),
            'tipo' => $row['pcl_TipoSesion'],
            'subtipo' => $row['pcl_SubTipoSesion']
        );
    }
    
    error_log("Total actividades encontradas: " . count($actividades));
    mysqli_stmt_close($stmtActividades);
    
// ===== OBTENER ESPACIOS =====
$espacios = array();

error_log("Verificando espacios para modalidad: " . $general['idmodalidad']);

if($general['idmodalidad'] == 1) {
    // ✅ MODALIDAD PRESENCIAL: Obtener de chc_solicitud_espacio
    $sqlEspacios = "
        SELECT 
            se.id,
            se.idespacio,
            e.espacio_requerido,
            se.otro
        FROM chc_solicitud_espacio se
        INNER JOIN chc_espacio_requerido e ON se.idespacio = e.idespacio
        WHERE se.idsolicitud = ?
        ORDER BY e.espacio_requerido
    ";
    
    $stmtEspacios = mysqli_prepare($conn, $sqlEspacios);
    if(!$stmtEspacios) {
        error_log("Error al preparar consulta espacios: " . mysqli_error($conn));
    } else {
        mysqli_stmt_bind_param($stmtEspacios, "i", $idSolicitud);
        
        if(!mysqli_stmt_execute($stmtEspacios)) {
            error_log("Error al ejecutar consulta espacios: " . mysqli_stmt_error($stmtEspacios));
        } else {
            $resultEspacios = mysqli_stmt_get_result($stmtEspacios);
            
            while($row = mysqli_fetch_assoc($resultEspacios)) {
                error_log("  Espacio encontrado: " . $row['espacio_requerido'] . " (ID: " . $row['idespacio'] . ")");
                
                $espacios[] = array(
                    'id' => $row['id'],
                    'idespacio' => $row['idespacio'],
                    'nombre' => $row['espacio_requerido'],
                    'detalle' => $row['otro']
                );
            }
            
            error_log("Total espacios encontrados: " . count($espacios));
        }
        
        mysqli_stmt_close($stmtEspacios);
    }
}
    
    // ===== CONSTRUIR RESPUESTA =====
    $response['success'] = true;
    $response['message'] = 'Detalle obtenido correctamente';
    $response['data'] = array(
        'general' => $general,
        'actividades' => $actividades,
        'espacios' => $espacios
    );
    
    error_log("Respuesta construida exitosamente");
    error_log("Total espacios en respuesta: " . count($espacios));
    
} catch(Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("❌ Error al obtener detalle CHC: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}

if(isset($conn)) {
    mysqli_close($conn);
}

echo json_encode($response);
?>