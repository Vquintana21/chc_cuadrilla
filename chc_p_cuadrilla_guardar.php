<?php
session_start();
require_once('conexion.php');
header('Content-Type: application/json; charset=utf-8');

// ============================================================
// SEGURIDAD
// ============================================================
if(empty($_SESSION['sesion_idLogin'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$rutPecRaw = $_SESSION['sesion_idLogin'];
$rutPEC    = str_pad($rutPecRaw, 10, "0", STR_PAD_LEFT);
$accion    = isset($_POST['accion']) ? trim($_POST['accion']) : '';

// ============================================================
// ROUTER DE ACCIONES
// ============================================================
switch($accion) {

    // ----------------------------------------------------------
    // SECCIÓN 1: Descripción general
    // Crea la cuadrilla si no existe, o actualiza subtipo y resumen
    // ----------------------------------------------------------
    case 'guardar_seccion1':
        $idsolicitud = intval($_POST['idsolicitud'] ?? 0);
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $idsubtipo   = intval($_POST['idsubtipo']   ?? 0);
        $resumen     = trim($_POST['resumen']        ?? '');

        if($idsolicitud <= 0 || $idsubtipo <= 0 || empty($resumen)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }

        // Verificar que la solicitud existe y está confirmada (estado 2)
        $stmtCheck = mysqli_prepare($conn, 
            "SELECT idsolicitud FROM chc_solicitud WHERE idsolicitud = ? AND idestadoagenda = 2 LIMIT 1");
        mysqli_stmt_bind_param($stmtCheck, "i", $idsolicitud);
        mysqli_stmt_execute($stmtCheck);
        $rowCheck = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCheck));
        mysqli_stmt_close($stmtCheck);

        if(!$rowCheck) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no válida o no confirmada']);
            exit;
        }

        if($idcuadrilla <= 0) {
            // ✅ INSERTAR nueva cuadrilla
            $sql = "INSERT INTO chc_p_cuadrilla 
                        (idsolicitud, idsubtipo, resumen_actividad, estado, rut_pec, fecha_creacion)
                    VALUES (?, ?, ?, 1, ?, NOW())";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiss", $idsolicitud, $idsubtipo, $resumen, $rutPEC);
            if(mysqli_stmt_execute($stmt)) {
                $nuevoId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                // Actualizar idestadocuadrilla en la solicitud (estado 1 = En creación)
                actualizarEstadoCuadrillaSolicitud($conn, $idsolicitud, 1);
                echo json_encode(['success' => true, 'idcuadrilla' => $nuevoId, 'message' => 'Cuadrilla creada']);
            } else {
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => false, 'message' => 'Error al crear cuadrilla: ' . mysqli_error($conn)]);
            }
        } else {
            // ✅ ACTUALIZAR cuadrilla existente
            $sql = "UPDATE chc_p_cuadrilla 
                    SET idsubtipo = ?, resumen_actividad = ?, fecha_modificacion = NOW()
                    WHERE idcuadrilla = ? AND idsolicitud = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isii", $idsubtipo, $resumen, $idcuadrilla, $idsolicitud);
            if(mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => true, 'idcuadrilla' => $idcuadrilla, 'message' => 'Sección 1 guardada']);
            } else {
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . mysqli_error($conn)]);
            }
        }
        break;

    // ----------------------------------------------------------
    // SECCIÓN 2A: Guardar una fila de fecha programada
    // INSERT ON DUPLICATE KEY UPDATE
    // ----------------------------------------------------------
    case 'guardar_fecha':
        $idcuadrilla  = intval($_POST['idcuadrilla']  ?? 0);
        $idplanclases = intval($_POST['idplanclases']  ?? 0);
        $hora_inicio  = trim($_POST['hora_inicio']     ?? '');
        $hora_termino = trim($_POST['hora_termino']    ?? '');
        $nro_pac      = !empty($_POST['nro_pacientes']) ? intval($_POST['nro_pacientes']) : null;
        $link_act     = trim($_POST['link_actividad']  ?? '');
        $ubicacion    = trim($_POST['ubicacion']       ?? '');

        if($idcuadrilla <= 0 || $idplanclases <= 0) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }

        // Verificar que la cuadrilla pertenece al PEC en sesión
        if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }

        $sql = "INSERT INTO chc_p_cuadrilla_fecha 
                    (idcuadrilla, idplanclases, hora_inicio, hora_termino, nro_pacientes, link_actividad, ubicacion)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    hora_inicio    = VALUES(hora_inicio),
                    hora_termino   = VALUES(hora_termino),
                    nro_pacientes  = VALUES(nro_pacientes),
                    link_actividad = VALUES(link_actividad),
                    ubicacion      = VALUES(ubicacion)";

        $stmt = mysqli_prepare($conn, $sql);
        // hora_inicio y hora_termino pueden ser vacíos (no nulos), los dejamos como string
        $hi = !empty($hora_inicio)  ? $hora_inicio  : null;
        $ht = !empty($hora_termino) ? $hora_termino : null;
        $lk = !empty($link_act)     ? $link_act     : null;
        $ub = !empty($ubicacion)    ? $ubicacion    : null;

        mysqli_stmt_bind_param($stmt, "iississs", 
            $idcuadrilla, $idplanclases, 
            $hi, $ht, 
            $nro_pac, 
            $lk, $ub);

        // Nota: bind_param con null requiere bind_param por referencia en PHP 5.6
        // Usamos una forma compatible:
        mysqli_stmt_close($stmt);

        // Forma compatible PHP 5.6 con posible null:
        $sqlAlt = "INSERT INTO chc_p_cuadrilla_fecha 
                       (idcuadrilla, idplanclases, hora_inicio, hora_termino, nro_pacientes, link_actividad, ubicacion)
                   VALUES ($idcuadrilla, $idplanclases, 
                       " . ($hi   !== null ? "'" . mysqli_real_escape_string($conn, $hi)   . "'" : "NULL") . ",
                       " . ($ht   !== null ? "'" . mysqli_real_escape_string($conn, $ht)   . "'" : "NULL") . ",
                       " . ($nro_pac !== null ? intval($nro_pac) : "NULL") . ",
                       " . ($lk   !== null ? "'" . mysqli_real_escape_string($conn, $lk)   . "'" : "NULL") . ",
                       " . ($ub   !== null ? "'" . mysqli_real_escape_string($conn, $ub)   . "'" : "NULL") . "
                   )
                   ON DUPLICATE KEY UPDATE
                       hora_inicio    = VALUES(hora_inicio),
                       hora_termino   = VALUES(hora_termino),
                       nro_pacientes  = VALUES(nro_pacientes),
                       link_actividad = VALUES(link_actividad),
                       ubicacion      = VALUES(ubicacion)";

        if(mysqli_query($conn, $sqlAlt)) {
            // Actualizar fecha_modificacion en cabecera
            mysqli_query($conn, "UPDATE chc_p_cuadrilla SET fecha_modificacion = NOW() WHERE idcuadrilla = $idcuadrilla");
            echo json_encode(['success' => true, 'message' => 'Fecha guardada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
        }
        break;

    // ----------------------------------------------------------
    // SECCIÓN 2B: Guardar una fila de capacitación
    // ----------------------------------------------------------
    case 'guardar_capacitacion':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $orden       = intval($_POST['orden']       ?? 0);
        $modalidad   = trim($_POST['modalidad']     ?? '');
        $fecha       = trim($_POST['fecha']         ?? '');
        $jornada     = trim($_POST['jornada']       ?? '');

        if($idcuadrilla <= 0 || $orden < 1 || $orden > 5) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }

        $modEsc  = mysqli_real_escape_string($conn, $modalidad);
        $fecEsc  = !empty($fecha) ? "'" . mysqli_real_escape_string($conn, $fecha) . "'" : "NULL";
        $jorEsc  = mysqli_real_escape_string($conn, $jornada);

        $sql = "INSERT INTO chc_p_cuadrilla_capacitacion (idcuadrilla, orden, modalidad, fecha, jornada)
                VALUES ($idcuadrilla, $orden, '$modEsc', $fecEsc, '$jorEsc')
                ON DUPLICATE KEY UPDATE
                    modalidad = VALUES(modalidad),
                    fecha     = VALUES(fecha),
                    jornada   = VALUES(jornada)";

        if(mysqli_query($conn, $sql)) {
            mysqli_query($conn, "UPDATE chc_p_cuadrilla SET fecha_modificacion = NOW() WHERE idcuadrilla = $idcuadrilla");
            echo json_encode(['success' => true, 'message' => 'Capacitación guardada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
        }
        break;

    // ----------------------------------------------------------
    // Eliminar una fila de capacitación
    // ----------------------------------------------------------
    case 'eliminar_capacitacion':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $orden       = intval($_POST['orden']       ?? 0);

        if($idcuadrilla <= 0 || $orden < 2) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar']);
            exit;
        }
        if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }

        $sql = "DELETE FROM chc_p_cuadrilla_capacitacion WHERE idcuadrilla = $idcuadrilla AND orden = $orden";
        mysqli_query($conn, $sql);
        echo json_encode(['success' => true, 'message' => 'Fila eliminada']);
        break;

    // ----------------------------------------------------------
    // SECCIÓN 2C: Guardar insumos (subtipo 9)
    // ----------------------------------------------------------
    case 'guardar_insumos':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $insumos     = trim($_POST['insumos']       ?? '');

        if($idcuadrilla <= 0) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }

        $insEsc = mysqli_real_escape_string($conn, $insumos);
        $sql = "UPDATE chc_p_cuadrilla 
                SET insumos = '$insEsc', fecha_modificacion = NOW()
                WHERE idcuadrilla = $idcuadrilla";

        if(mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => 'Insumos guardados']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
        }
        break;

    // ----------------------------------------------------------
    // SECCIÓN 2D: Guardar debriefing/briefing
    // ----------------------------------------------------------
    case 'guardar_debriefing':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $briefing    = trim($_POST['briefing']      ?? '');
        $debriefing  = trim($_POST['debriefing']    ?? '');

        if($idcuadrilla <= 0) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }

        $brfEsc = mysqli_real_escape_string($conn, $briefing);
        $debEsc = mysqli_real_escape_string($conn, $debriefing);

        $sql = "INSERT INTO chc_p_cuadrilla_debriefing 
                    (idcuadrilla, implementacion_briefing, implementacion_debriefing)
                VALUES ($idcuadrilla, '$brfEsc', '$debEsc')
                ON DUPLICATE KEY UPDATE
                    implementacion_briefing   = VALUES(implementacion_briefing),
                    implementacion_debriefing = VALUES(implementacion_debriefing)";

        if(mysqli_query($conn, $sql)) {
            mysqli_query($conn, "UPDATE chc_p_cuadrilla SET fecha_modificacion = NOW() WHERE idcuadrilla = $idcuadrilla");
            echo json_encode(['success' => true, 'message' => 'Debriefing guardado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
        break;
}

// ============================================================
// FUNCIONES AUXILIARES
// ============================================================

/**
 * Verifica que la cuadrilla pertenece al PEC en sesión
 */
function verificarCuadrilla($conn, $idcuadrilla, $rutPEC) {
    $sql  = "SELECT idcuadrilla FROM chc_p_cuadrilla WHERE idcuadrilla = ? AND rut_pec = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $idcuadrilla, $rutPEC);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return !empty($row);
}

/**
 * Actualiza el idestadocuadrilla en chc_solicitud cuando cambia el estado de la cuadrilla
 * Estado: 1=En creación, 2=En verificación, 3=Enviada
 */
function actualizarEstadoCuadrillaSolicitud($conn, $idsolicitud, $estado) {
    $sql  = "UPDATE chc_solicitud SET idestadocuadrilla = ? WHERE idsolicitud = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $estado, $idsolicitud);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
?>
