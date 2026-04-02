<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$response = array('success' => false, 'message' => '');

try {
    if(!isset($_POST['paso'])) {
        throw new Exception('Paso no especificado');
    }
    
    $paso = $_POST['paso'];
    
    if($paso === 'guardar_modalidad') {
        if(!isset($_POST['idmodalidad'])) {
            throw new Exception('Modalidad no especificada');
        }
        
        $_SESSION['chc_modalidad'] = intval($_POST['idmodalidad']);
        $response['success'] = true;
        $response['message'] = 'Modalidad guardada en sesión';
        
    } elseif($paso === 'guardar_actividades') {
        if(!isset($_POST['actividades'])) {
            throw new Exception('Actividades no especificadas');
        }
        
        $actividades = json_decode($_POST['actividades'], true);
        
        if(!is_array($actividades) || empty($actividades)) {
            throw new Exception('Formato de actividades inválido');
        }
        
        $_SESSION['chc_actividades'] = $actividades;
        $response['success'] = true;
        $response['message'] = 'Actividades guardadas en sesión';
        
    } else {
        throw new Exception('Paso no reconocido');
    }
    
} catch(Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>