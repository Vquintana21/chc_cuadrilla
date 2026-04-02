<?php
session_start();
include_once("conexion.php");
include_once("login/control_sesion.php");
header('Content-Type: application/json');

if (empty($_SESSION['sesion_idLogin'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$rutRecibidox = isset($_POST['rut']) ? trim($_POST['rut']) : '';

if (empty($rutRecibidox)) {
    echo json_encode(['success' => false, 'message' => 'RUT no proporcionado']);
    exit;
}

// Verificar que el RUT pertenece al usuario de la sesión
// Normalizar ambos RUTs (quitar ceros a la izquierda y comparar)
$rutSesion = str_pad($_SESSION['sesion_idLogin'], 10, "0", STR_PAD_LEFT);
$rut = str_pad($rutRecibidox, 10, "0", STR_PAD_LEFT);

// Debug para ver qué está pasando (puedes comentar después)
error_log("RUT Sesión (original): " . $_SESSION['sesion_idLogin']);
error_log("RUT Sesión (sin ceros): $rutSesion");
error_log("RUT Recibido (original): $rutRecibidox");
error_log("RUT Recibido (sin ceros): $rut");

if ($rut !== $rutSesion) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado. RUT no coincide con la sesión']);
    exit;
}

$sql = "SELECT Nombres, Paterno, Materno, EmailReal, Telefono 
        FROM spre_personas 
        WHERE Rut = ?";

$stmt = mysqli_prepare($conexion3, $sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta']);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $rut);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $nombreCompleto = trim($row['Nombres'] . ' ' . $row['Paterno'] . ' ' . $row['Materno']);
    
    $response = [
        'success' => true,
        'data' => [
            'nombreCompleto' => utf8_encode($nombreCompleto),
            'EmailReal' => $row['EmailReal'],
            'Telefono' => $row['Telefono']
        ]
    ];
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'No se encontraron datos para este RUT']);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion3);
?>