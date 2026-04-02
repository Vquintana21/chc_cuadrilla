<?php
session_start();
require_once('conexion.php');
header('Content-Type: application/json; charset=utf-8');

// ============================================================
// FUNCIÓN HELPER: obtener POST con valor por defecto
// Compatible PHP 5.6 - evita ?? y ternarios problemáticos
// ============================================================
function post($key, $default) {
    if(isset($_POST[$key]) && $_POST[$key] !== '') {
        return $_POST[$key];
    }
    return $default;
}

// ============================================================
// SEGURIDAD
// ============================================================
if(empty($_SESSION['sesion_idLogin'])) {
    echo json_encode(array('success' => false, 'message' => 'Sesion no valida'));
    exit;
}
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'Metodo no permitido'));
    exit;
}

$rutPecRaw = $_SESSION['sesion_idLogin'];
$rutPEC    = str_pad($rutPecRaw, 10, "0", STR_PAD_LEFT);
$accion    = trim(post('accion', ''));

// ============================================================
// ROUTER DE ACCIONES — if/elseif para evitar problemas
// de ternarios dentro de switch en PHP 5.6
// ============================================================

if($accion === 'guardar_seccion1') {
    accionGuardarSeccion1($conn, $rutPEC);

} elseif($accion === 'guardar_fecha') {
    accionGuardarFecha($conn, $rutPEC);

} elseif($accion === 'guardar_capacitacion') {
    accionGuardarCapacitacion($conn, $rutPEC);

} elseif($accion === 'eliminar_capacitacion') {
    accionEliminarCapacitacion($conn, $rutPEC);

} elseif($accion === 'guardar_insumos') {
    accionGuardarInsumos($conn, $rutPEC);

} elseif($accion === 'guardar_debriefing') {
    accionGuardarDebriefing($conn, $rutPEC);

} else {
    echo json_encode(array('success' => false, 'message' => 'Accion no reconocida'));
}

// ============================================================
// ACCIÓN 1: Guardar Sección 1
// ============================================================
function accionGuardarSeccion1($conn, $rutPEC) {
    $idsolicitud = intval(post('idsolicitud', 0));
    $idcuadrilla = intval(post('idcuadrilla', 0));
    $idsubtipo   = intval(post('idsubtipo', 0));
    $resumen     = trim(post('resumen', ''));

    if($idsolicitud <= 0 || $idsubtipo <= 0 || $resumen === '') {
        echo json_encode(array('success' => false, 'message' => 'Datos incompletos'));
        return;
    }

    // Verificar que la solicitud existe y está confirmada (estado 2)
    $rutEscCheck = mysqli_real_escape_string($conn, '');
    $sqlCheck    = "SELECT idsolicitud FROM chc_solicitud
                    WHERE idsolicitud = $idsolicitud AND idestadoagenda = 2 LIMIT 1";
    $resCheck = mysqli_query($conn, $sqlCheck);
    if(!$resCheck || mysqli_num_rows($resCheck) === 0) {
        echo json_encode(array('success' => false, 'message' => 'Solicitud no valida o no confirmada'));
        return;
    }

    $resEsc  = mysqli_real_escape_string($conn, $resumen);
    $rutEsc  = mysqli_real_escape_string($conn, $rutPEC);

    if($idcuadrilla <= 0) {
        // INSERTAR nueva cuadrilla
        $sql = "INSERT INTO chc_p_cuadrilla
                    (idsolicitud, idsubtipo, resumen_actividad, estado, rut_pec, fecha_creacion)
                VALUES ($idsolicitud, $idsubtipo, '$resEsc', 1, '$rutEsc', NOW())";

        if(mysqli_query($conn, $sql)) {
            $nuevoId = mysqli_insert_id($conn);
            actualizarEstadoCuadrillaSolicitud($conn, $idsolicitud, 1);
            echo json_encode(array('success' => true, 'idcuadrilla' => $nuevoId, 'message' => 'Cuadrilla creada'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Error al crear: ' . mysqli_error($conn)));
        }
    } else {
        // ACTUALIZAR cuadrilla existente
        $sql = "UPDATE chc_p_cuadrilla
                SET idsubtipo          = $idsubtipo,
                    resumen_actividad  = '$resEsc',
                    fecha_modificacion = NOW()
                WHERE idcuadrilla = $idcuadrilla AND idsolicitud = $idsolicitud";

        if(mysqli_query($conn, $sql)) {
            echo json_encode(array('success' => true, 'idcuadrilla' => $idcuadrilla, 'message' => 'Seccion 1 guardada'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Error al actualizar: ' . mysqli_error($conn)));
        }
    }
}

// ============================================================
// ACCIÓN 2: Guardar fila de fecha programada
// ============================================================
function accionGuardarFecha($conn, $rutPEC) {
    $idcuadrilla  = intval(post('idcuadrilla', 0));
    $idplanclases = intval(post('idplanclases', 0));
    $hora_inicio  = trim(post('hora_inicio', ''));
    $hora_termino = trim(post('hora_termino', ''));
    $nro_pac_raw  = post('nro_pacientes', '');
    $link_act     = trim(post('link_actividad', ''));
    $ubicacion    = trim(post('ubicacion', ''));

    if($idcuadrilla <= 0 || $idplanclases <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Datos incompletos'));
        return;
    }
    if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
        echo json_encode(array('success' => false, 'message' => 'Acceso no autorizado'));
        return;
    }

    $hi = ($hora_inicio  !== '') ? "'" . mysqli_real_escape_string($conn, $hora_inicio)  . "'" : "NULL";
    $ht = ($hora_termino !== '') ? "'" . mysqli_real_escape_string($conn, $hora_termino) . "'" : "NULL";
    $np = ($nro_pac_raw  !== '') ? intval($nro_pac_raw) : "NULL";
    $lk = ($link_act     !== '') ? "'" . mysqli_real_escape_string($conn, $link_act)     . "'" : "NULL";
    $ub = ($ubicacion    !== '') ? "'" . mysqli_real_escape_string($conn, $ubicacion)    . "'" : "NULL";

    $sql = "INSERT INTO chc_p_cuadrilla_fecha
                (idcuadrilla, idplanclases, hora_inicio, hora_termino, nro_pacientes, link_actividad, ubicacion)
            VALUES ($idcuadrilla, $idplanclases, $hi, $ht, $np, $lk, $ub)
            ON DUPLICATE KEY UPDATE
                hora_inicio    = VALUES(hora_inicio),
                hora_termino   = VALUES(hora_termino),
                nro_pacientes  = VALUES(nro_pacientes),
                link_actividad = VALUES(link_actividad),
                ubicacion      = VALUES(ubicacion)";

    if(mysqli_query($conn, $sql)) {
        mysqli_query($conn, "UPDATE chc_p_cuadrilla SET fecha_modificacion = NOW() WHERE idcuadrilla = $idcuadrilla");
        echo json_encode(array('success' => true, 'message' => 'Fecha guardada'));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . mysqli_error($conn)));
    }
}

// ============================================================
// ACCIÓN 3: Guardar fila de capacitación
// ============================================================
function accionGuardarCapacitacion($conn, $rutPEC) {
    $idcuadrilla = intval(post('idcuadrilla', 0));
    $orden       = intval(post('orden', 0));
    $modalidad   = trim(post('modalidad', ''));
    $fecha       = trim(post('fecha', ''));
    $jornada     = trim(post('jornada', ''));

    if($idcuadrilla <= 0 || $orden < 1 || $orden > 5) {
        echo json_encode(array('success' => false, 'message' => 'Datos incompletos'));
        return;
    }
    if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
        echo json_encode(array('success' => false, 'message' => 'Acceso no autorizado'));
        return;
    }

    $modEsc = mysqli_real_escape_string($conn, $modalidad);
    $jorEsc = mysqli_real_escape_string($conn, $jornada);
    $fecSql = ($fecha !== '') ? "'" . mysqli_real_escape_string($conn, $fecha) . "'" : "NULL";

    $sql = "INSERT INTO chc_p_cuadrilla_capacitacion (idcuadrilla, orden, modalidad, fecha, jornada)
            VALUES ($idcuadrilla, $orden, '$modEsc', $fecSql, '$jorEsc')
            ON DUPLICATE KEY UPDATE
                modalidad = VALUES(modalidad),
                fecha     = VALUES(fecha),
                jornada   = VALUES(jornada)";

    if(mysqli_query($conn, $sql)) {
        mysqli_query($conn, "UPDATE chc_p_cuadrilla SET fecha_modificacion = NOW() WHERE idcuadrilla = $idcuadrilla");
        echo json_encode(array('success' => true, 'message' => 'Capacitacion guardada'));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . mysqli_error($conn)));
    }
}

// ============================================================
// ACCIÓN 4: Eliminar fila de capacitación
// ============================================================
function accionEliminarCapacitacion($conn, $rutPEC) {
    $idcuadrilla = intval(post('idcuadrilla', 0));
    $orden       = intval(post('orden', 0));

    if($idcuadrilla <= 0 || $orden < 2) {
        echo json_encode(array('success' => false, 'message' => 'No se puede eliminar'));
        return;
    }
    if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
        echo json_encode(array('success' => false, 'message' => 'Acceso no autorizado'));
        return;
    }

    mysqli_query($conn, "DELETE FROM chc_p_cuadrilla_capacitacion WHERE idcuadrilla = $idcuadrilla AND orden = $orden");
    echo json_encode(array('success' => true, 'message' => 'Fila eliminada'));
}

// ============================================================
// ACCIÓN 5: Guardar insumos
// ============================================================
function accionGuardarInsumos($conn, $rutPEC) {
    $idcuadrilla = intval(post('idcuadrilla', 0));
    $insumos     = trim(post('insumos', ''));

    if($idcuadrilla <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Datos incompletos'));
        return;
    }
    if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
        echo json_encode(array('success' => false, 'message' => 'Acceso no autorizado'));
        return;
    }

    $insEsc = mysqli_real_escape_string($conn, $insumos);
    $sql    = "UPDATE chc_p_cuadrilla
               SET insumos = '$insEsc', fecha_modificacion = NOW()
               WHERE idcuadrilla = $idcuadrilla";

    if(mysqli_query($conn, $sql)) {
        echo json_encode(array('success' => true, 'message' => 'Insumos guardados'));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . mysqli_error($conn)));
    }
}

// ============================================================
// ACCIÓN 6: Guardar debriefing/briefing
// ============================================================
function accionGuardarDebriefing($conn, $rutPEC) {
    $idcuadrilla = intval(post('idcuadrilla', 0));
    $briefing    = trim(post('briefing', ''));
    $debriefing  = trim(post('debriefing', ''));

    if($idcuadrilla <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Datos incompletos'));
        return;
    }
    if(!verificarCuadrilla($conn, $idcuadrilla, $rutPEC)) {
        echo json_encode(array('success' => false, 'message' => 'Acceso no autorizado'));
        return;
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
        echo json_encode(array('success' => true, 'message' => 'Debriefing guardado'));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . mysqli_error($conn)));
    }
}

// ============================================================
// FUNCIONES AUXILIARES
// ============================================================
function verificarCuadrilla($conn, $idcuadrilla, $rutPEC) {
    $rutEsc = mysqli_real_escape_string($conn, $rutPEC);
    $sql    = "SELECT idcuadrilla FROM chc_p_cuadrilla
               WHERE idcuadrilla = $idcuadrilla AND rut_pec = '$rutEsc'
               LIMIT 1";
    $res = mysqli_query($conn, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}

function actualizarEstadoCuadrillaSolicitud($conn, $idsolicitud, $estado) {
    $sql = "UPDATE chc_solicitud SET idestadocuadrilla = $estado WHERE idsolicitud = $idsolicitud";
    mysqli_query($conn, $sql);
}
?>