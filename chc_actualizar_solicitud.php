<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once('conexion.php');

$response = array('success' => false, 'message' => '');

try {
    if(!isset($_POST['idsolicitud'])) {
        throw new Exception('ID de solicitud no especificado');
    }
    
    $idSolicitud = intval($_POST['idsolicitud']);
    
    if($idSolicitud <= 0) {
        throw new Exception('ID de solicitud inválido');
    }
    
    error_log("===== CHC ACTUALIZAR SOLICITUD =====");
    error_log("ID Solicitud: $idSolicitud");
    error_log("POST DATA: " . print_r($_POST, true));
    
    // Verificar que la solicitud existe y obtener modalidad
    $sqlVerificar = "
        SELECT s.idsolicitud, m.idmodalidad
        FROM chc_solicitud s
        LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
        LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
        WHERE s.idsolicitud = ?
    ";
    $stmtVerificar = mysqli_prepare($conn, $sqlVerificar);
    mysqli_stmt_bind_param($stmtVerificar, "i", $idSolicitud);
    mysqli_stmt_execute($stmtVerificar);
    $resultVerificar = mysqli_stmt_get_result($stmtVerificar);
    $verificar = mysqli_fetch_assoc($resultVerificar);
    
    if(!$verificar) {
        throw new Exception('Solicitud no encontrada');
    }
    
    $idModalidad = $verificar['idmodalidad'];
    mysqli_stmt_close($stmtVerificar);
    
    // ===== RECIBIR DATOS DEL FORMULARIO =====
    $usoFantoma = isset($_POST['uso_fantoma']) ? intval($_POST['uso_fantoma']) : 0;
    $fantomaCapacitado = isset($_POST['fantoma_capacitado']) ? intval($_POST['fantoma_capacitado']) : 0;
    $nPacientes = isset($_POST['npacientes']) ? $_POST['npacientes'] : '';
	
	$fantomaFechaCapacitacion = '';
	$fantomaHoraCapacitacion = '';

if($fantomaCapacitado == 0) {
    $fantomaFechaCapacitacion = isset($_POST['fantoma_fecha_capacitacion']) ? $_POST['fantoma_fecha_capacitacion'] : '';
    $fantomaHoraCapacitacion = isset($_POST['fantoma_hora_capacitacion']) ? $_POST['fantoma_hora_capacitacion'] : '';
    
    // Agregar segundos si solo tiene HH:MM
    if(!empty($fantomaHoraCapacitacion) && strlen($fantomaHoraCapacitacion) == 5) {
        $fantomaHoraCapacitacion .= ':00';
    }
}
    
    if($nPacientes === 'mayor_12' && isset($_POST['npacientes_otro'])) {
        $nPacientes = $_POST['npacientes_otro'];
    }
    
    $nEstudiantesXSesion = isset($_POST['nestudiantesxsesion']) ? intval($_POST['nestudiantesxsesion']) : 0;
    $nBoxes = isset($_POST['nboxes']) ? $_POST['nboxes'] : '';
    
    if($nBoxes === 'mayor_12' && isset($_POST['nboxes_otro'])) {
        $nBoxes = $_POST['nboxes_otro'];
    }
    
    $espacioRequeridoOtros = isset($_POST['espacio_requerido_otros']) ? $_POST['espacio_requerido_otros'] : null;
    $usoDebriefing = isset($_POST['uso_debriefing']) ? intval($_POST['uso_debriefing']) : 0;
    $comentarios = isset($_POST['comentarios']) ? $_POST['comentarios'] : null;
    
    // Convertir a strings seguros
    $nPacientesStr = (string)$nPacientes;
    $nBoxesStr = (string)$nBoxes;
    $espacioRequeridoOtrosStr = $espacioRequeridoOtros !== null ? (string)$espacioRequeridoOtros : '';
    $comentariosStr = $comentarios !== null ? (string)$comentarios : '';
    
    // Iniciar transacción
    mysqli_begin_transaction($conn);
    
    // ===== ACTUALIZAR DATOS PRINCIPALES =====
    $sqlUpdate = "
        UPDATE chc_solicitud SET
			uso_fantoma = ?,
			fantoma_capacitado = ?,
			fantoma_fecha_capacitacion = ?,
			fantoma_hora_capacitacion = ?,
			npacientes = ?,
            nestudiantesxsesion = ?,
            nboxes = ?,
            espacio_requerido_otros = ?,
            uso_debriefing = ?,
            comentarios = ?
        WHERE idsolicitud = ?
    ";
    
    $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);
    if(!$stmtUpdate) {
        throw new Exception('Error al preparar actualización: ' . mysqli_error($conn));
    }
    
	 mysqli_stmt_bind_param($stmtUpdate, "iissisisssi",
		$usoFantoma,
		$fantomaCapacitado,
		$fantomaFechaCapacitacion,
		$fantomaHoraCapacitacion,
		$nPacientesStr,
        $nEstudiantesXSesion,
        $nBoxesStr,
        $espacioRequeridoOtrosStr,
        $usoDebriefing,
        $comentariosStr,
        $idSolicitud
    );
    
    if(!mysqli_stmt_execute($stmtUpdate)) {
        throw new Exception('Error al actualizar solicitud: ' . mysqli_stmt_error($stmtUpdate));
    }
    
    mysqli_stmt_close($stmtUpdate);
    error_log("✅ Solicitud actualizada");
    
    // ===== ACTUALIZAR ESPACIOS (solo para modalidad Presencial) =====
    if($idModalidad == 1) {
        // Eliminar espacios actuales
        $sqlDeleteEspacios = "DELETE FROM chc_solicitud_espacio WHERE idsolicitud = ?";
        $stmtDeleteEspacios = mysqli_prepare($conn, $sqlDeleteEspacios);
        mysqli_stmt_bind_param($stmtDeleteEspacios, "i", $idSolicitud);
        mysqli_stmt_execute($stmtDeleteEspacios);
        mysqli_stmt_close($stmtDeleteEspacios);
        
        error_log("✅ Espacios anteriores eliminados");
        
        // Insertar nuevos espacios seleccionados
        $mapaEspacios = array(
            'espacio_salas_atencion' => 1,
            'espacio_salas_procedimientos' => 2,
			'espacio_mesa_buzon' => 3,
            'espacio_voz_off' => 4,
            'espacio_otros' => 5
        );
        
        $espaciosGuardados = 0;
        
        foreach($mapaEspacios as $nombreCampo => $idEspacio) {
            if(isset($_POST[$nombreCampo]) && $_POST[$nombreCampo] == '1') {
                
                $detalleEspacio = '';
                if($nombreCampo === 'espacio_otros' && isset($_POST['espacio_otros_detalle'])) {
                    $detalleEspacio = $_POST['espacio_otros_detalle'];
                }
                
                $sqlEspacio = "INSERT INTO chc_solicitud_espacio (idsolicitud, idespacio, otro) VALUES (?, ?, ?)";
                $stmtEspacio = mysqli_prepare($conn, $sqlEspacio);
                mysqli_stmt_bind_param($stmtEspacio, "iis", $idSolicitud, $idEspacio, $detalleEspacio);
                
                if(mysqli_stmt_execute($stmtEspacio)) {
                    $espaciosGuardados++;
                    error_log("  ✅ Espacio insertado: ID=$idEspacio");
                }
                
                mysqli_stmt_close($stmtEspacio);
            }
        }
        
        error_log("✅ Nuevos espacios guardados: $espaciosGuardados");
    }
    
    // Commit transacción
    mysqli_commit($conn);
    
    $response['success'] = true;
    $response['message'] = 'Solicitud actualizada correctamente';
    
    error_log("===== ACTUALIZACIÓN COMPLETADA =====");
    
} catch(Exception $e) {
    // Rollback en caso de error
    if(isset($conn)) {
        mysqli_rollback($conn);
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("❌ Error: " . $e->getMessage());
}

if(isset($conn)) {
    mysqli_close($conn);
}

echo json_encode($response);
?>