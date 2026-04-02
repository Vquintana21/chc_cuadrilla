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
    
    error_log("===== CHC OBTENER DATOS EDICIÓN =====");
    error_log("ID Solicitud: $idSolicitud");
    
    // ===== OBTENER DATOS DE LA SOLICITUD =====
    $sqlSolicitud = "
        SELECT 
            s.idsolicitud,
            s.uso_fantoma,
            s.fantoma_capacitado,
            s.npacientes,
            s.nestudiantesxsesion,
            s.nboxes,
            s.espacio_requerido_otros,
            s.uso_debriefing,
            s.comentarios,
            m.idmodalidad,
			s.fantoma_fecha_capacitacion,
			s.fantoma_hora_capacitacion,
            m.modalidad,
            COUNT(DISTINCT sa.idplanclases) as total_actividades
        FROM chc_solicitud s
        LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
        LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
        LEFT JOIN chc_solicitud_actividad sa ON s.idsolicitud = sa.idsolicitud
        WHERE s.idsolicitud = ?
        GROUP BY s.idsolicitud, s.uso_fantoma, s.fantoma_capacitado, s.npacientes, 
                 s.nestudiantesxsesion, s.nboxes, s.espacio_requerido_otros, 
                 s.uso_debriefing, s.comentarios, m.idmodalidad, s.fantoma_fecha_capacitacion, s.fantoma_hora_capacitacion, m.modalidad
    ";
    
    $stmtSolicitud = mysqli_prepare($conn, $sqlSolicitud);
    if(!$stmtSolicitud) {
        throw new Exception('Error al preparar consulta: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmtSolicitud, "i", $idSolicitud);
    mysqli_stmt_execute($stmtSolicitud);
    $resultSolicitud = mysqli_stmt_get_result($stmtSolicitud);
    $solicitud = mysqli_fetch_assoc($resultSolicitud);
    
    if(!$solicitud) {
        throw new Exception('Solicitud no encontrada');
    }
    
    mysqli_stmt_close($stmtSolicitud);
	
	// ===== OBTENER ACTIVIDADES DETALLADAS =====
$actividades = array();

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
if($stmtActividades) {
    mysqli_stmt_bind_param($stmtActividades, "i", $idSolicitud);
    mysqli_stmt_execute($stmtActividades);
    $resultActividades = mysqli_stmt_get_result($stmtActividades);
    
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
    
    mysqli_stmt_close($stmtActividades);
    error_log("Actividades obtenidas: " . count($actividades));
}
    
    // ===== OBTENER ESPACIOS SELECCIONADOS (solo para modalidad Presencial) =====
    $espaciosSeleccionados = array();
    
    if($solicitud['idmodalidad'] == 1) {
        $sqlEspacios = "
            SELECT idespacio, otro
            FROM chc_solicitud_espacio
            WHERE idsolicitud = ?
        ";
        
        $stmtEspacios = mysqli_prepare($conn, $sqlEspacios);
        mysqli_stmt_bind_param($stmtEspacios, "i", $idSolicitud);
        mysqli_stmt_execute($stmtEspacios);
        $resultEspacios = mysqli_stmt_get_result($stmtEspacios);
        
        while($row = mysqli_fetch_assoc($resultEspacios)) {
            $espaciosSeleccionados[] = array(
                'idespacio' => $row['idespacio'],
                'otro' => $row['otro']
            );
        }
        
        mysqli_stmt_close($stmtEspacios);
        
        error_log("Espacios seleccionados: " . count($espaciosSeleccionados));
    }
    
    // ===== CONSTRUIR RESPUESTA =====
    $response['success'] = true;
    $response['message'] = 'Datos obtenidos correctamente';
    $response['data'] = array(
    'solicitud' => $solicitud,
    'espacios_seleccionados' => $espaciosSeleccionados,
    'actividades' => $actividades
);
    
    error_log("Datos de edición obtenidos correctamente");
    
} catch(Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("❌ Error: " . $e->getMessage());
}

if(isset($conn)) {
    mysqli_close($conn);
}

echo json_encode($response);
?>