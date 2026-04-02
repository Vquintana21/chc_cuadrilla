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
    
    // Verificar que la solicitud existe
    $sqlVerificar = "SELECT idsolicitud FROM chc_solicitud WHERE idsolicitud = ?";
    $stmtVerificar = mysqli_prepare($conn, $sqlVerificar);
    mysqli_stmt_bind_param($stmtVerificar, "i", $idSolicitud);
    mysqli_stmt_execute($stmtVerificar);
    $resultVerificar = mysqli_stmt_get_result($stmtVerificar);
    
    if(mysqli_num_rows($resultVerificar) === 0) {
        throw new Exception('La solicitud no existe');
    }
    
    mysqli_stmt_close($stmtVerificar);
    
    // Eliminar la solicitud (CASCADE eliminará automáticamente los registros relacionados)
    $sqlEliminar = "DELETE FROM chc_solicitud WHERE idsolicitud = ?";
    $stmtEliminar = mysqli_prepare($conn, $sqlEliminar);
    mysqli_stmt_bind_param($stmtEliminar, "i", $idSolicitud);
    
    if(!mysqli_stmt_execute($stmtEliminar)) {
        throw new Exception('Error al eliminar la solicitud: ' . mysqli_stmt_error($stmtEliminar));
    }
    
    mysqli_stmt_close($stmtEliminar);
    
    $response['success'] = true;
    $response['message'] = 'Solicitud CHC eliminada correctamente';
    
    error_log("✅ Solicitud CHC eliminada: ID=$idSolicitud");
    
} catch(Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("❌ Error al eliminar solicitud CHC: " . $e->getMessage());
}

if(isset($conn)) {
    mysqli_close($conn);
}

echo json_encode($response);
?>