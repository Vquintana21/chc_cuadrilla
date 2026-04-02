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

$rutRecibido = isset($_POST['rut']) ? trim($_POST['rut']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';

// Validaciones
if (empty($rutRecibido)) {
    echo json_encode(['success' => false, 'message' => 'RUT no proporcionado']);
    exit;
}

// ✅ Normalizar AMBOS RUTs antes de comparar
$rutSesion = str_pad($_SESSION['sesion_idLogin'], 10, "0", STR_PAD_LEFT);
$rut = str_pad($rutRecibido, 10, "0", STR_PAD_LEFT);

if ($rut !== $rutSesion) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

// Verificar que el RUT pertenece al usuario de la sesión
$rutSesion = str_pad($_SESSION['sesion_idLogin'], 10, "0", STR_PAD_LEFT);
if ($rut !== $rutSesion) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'El email es obligatorio']);
    exit;
}

// Validar formato de email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Formato de email inválido']);
    exit;
}

// Obtener año actual
$periodoActual = date('Y');

$errorOcurrido = false;
$mensajeError = '';

// Actualizar tabla spre_personas en conexion3
$sql = "UPDATE spre_personas 
        SET EmailReal = ?, Telefono = ? 
        WHERE Rut = ?";

$stmt = mysqli_prepare($conexion3, $sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta spre_personas']);
    exit;
}

mysqli_stmt_bind_param($stmt, "sss", $email, $telefono, $rut);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar spre_personas']);
    mysqli_stmt_close($stmt);
    mysqli_close($conexion3);
    mysqli_close($conn);
    exit;
}

$filasAfectadasPersonas = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

// Ahora trabajar con correos_actualizados en conn
// Verificar si el RUT existe en correos_actualizados
$sqlCheck = "SELECT id FROM correos_actualizados WHERE rut = ?";
$stmtCheck = mysqli_prepare($conn, $sqlCheck);

if (!$stmtCheck) {
    echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta de verificación']);
    mysqli_close($conexion3);
    mysqli_close($conn);
    exit;
}

mysqli_stmt_bind_param($stmtCheck, "s", $rut);
mysqli_stmt_execute($stmtCheck);
mysqli_stmt_store_result($stmtCheck);

$existeRut = mysqli_stmt_num_rows($stmtCheck) > 0;
mysqli_stmt_close($stmtCheck);

if ($existeRut) {
    // Actualizar registro existente
    $sqlUpdate = "UPDATE correos_actualizados 
                  SET correo = ?, periodo = ? 
                  WHERE rut = ?";
    
    $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);
    if (!$stmtUpdate) {
        echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta de actualización correos_actualizados']);
        mysqli_close($conexion3);
        mysqli_close($conn);
        exit;
    }
    
    mysqli_stmt_bind_param($stmtUpdate, "sis", $email, $periodoActual, $rut);
    
    if (!mysqli_stmt_execute($stmtUpdate)) {
        $errorOcurrido = true;
        $mensajeError = 'Error al actualizar correos_actualizados';
    }
    
    mysqli_stmt_close($stmtUpdate);
} else {
    // Insertar nuevo registro
    $sqlInsert = "INSERT INTO correos_actualizados (rut, correo, periodo) 
                  VALUES (?, ?, ?)";
    
    $stmtInsert = mysqli_prepare($conn, $sqlInsert);
    if (!$stmtInsert) {
        echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta de inserción correos_actualizados']);
        mysqli_close($conexion3);
        mysqli_close($conn);
        exit;
    }
    
    mysqli_stmt_bind_param($stmtInsert, "ssi", $rut, $email, $periodoActual);
    
    if (!mysqli_stmt_execute($stmtInsert)) {
        $errorOcurrido = true;
        $mensajeError = 'Error al insertar en correos_actualizados';
    }
    
    mysqli_stmt_close($stmtInsert);
}

mysqli_close($conexion3);
mysqli_close($conn);

// Responder según el resultado
if ($errorOcurrido) {
    echo json_encode(['success' => false, 'message' => $mensajeError]);
} else {
    if ($filasAfectadasPersonas > 0) {
        echo json_encode(['success' => true, 'message' => 'Datos actualizados correctamente']);
    } else {
        echo json_encode(['success' => true, 'message' => 'No se realizaron cambios en personas, pero correo registrado']);
    }
}
?>