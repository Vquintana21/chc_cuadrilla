<?php
session_start();
require_once('conexion.php');
header('Content-Type: application/json; charset=utf-8');

if(empty($_SESSION['sesion_idLogin'])) {
    echo json_encode(['success'=>false,'message'=>'Sesión no válida']); exit;
}
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
}

$rutPEC = str_pad($_SESSION['sesion_idLogin'], 10, "0", STR_PAD_LEFT);
$accion = trim($_POST['accion'] ?? '');

// ============================================================
// Función auxiliar: verificar que la cuadrilla pertenece al PEC
// y está en estado editable (1 o 2, no enviada)
// ============================================================
function verificarCuadrilaEditable($conn, $idcuadrilla, $rutPEC) {
    $sql  = "SELECT idcuadrilla, idsolicitud, idsubtipo, estado
             FROM chc_p_cuadrilla
             WHERE idcuadrilla = ? AND rut_pec = ? LIMIT 1";
    $rutEscE = mysqli_real_escape_string($conn, $rutPEC);
    $sqlE    = "SELECT idcuadrilla, idsolicitud, idsubtipo, estado
                FROM chc_p_cuadrilla
                WHERE idcuadrilla = $idcuadrilla AND rut_pec = '$rutEscE' LIMIT 1";
    $resE = mysqli_query($conn, $sqlE);
    $row  = $resE ? mysqli_fetch_assoc($resE) : null;
    if(!$row) return null;
    if($row['estado'] == 3) return null;
    return $row;
}

switch($accion) {

    // ============================================================
    // EDITAR SECCIÓN 1: Subtipo + Resumen
    // Si cambia el subtipo → limpieza en cascada de datos huérfanos
    // ============================================================
    case 'editar_seccion1':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $idsubtipo   = intval($_POST['idsubtipo']   ?? 0);
        $resumen     = trim($_POST['resumen']        ?? '');

        if($idcuadrilla <= 0 || $idsubtipo <= 0 || empty($resumen)) {
            echo json_encode(['success'=>false,'message'=>'Datos incompletos']); exit;
        }

        $cuad = verificarCuadrilaEditable($conn, $idcuadrilla, $rutPEC);
        if(!$cuad) {
            echo json_encode(['success'=>false,'message'=>'Cuadrilla no editable o sin acceso']); exit;
        }

        $subtipAnterior = intval($cuad['idsubtipo']);
        $cambioSubtipo  = ($idsubtipo !== $subtipAnterior);
        $requiereRecarga = false;

        // Cargar flags del NUEVO subtipo
        $resNuevo = mysqli_query($conn,
            "SELECT tiene_pacientes, tiene_insumos, tiene_debriefing, tiene_link, tiene_ubicacion
             FROM chc_p_cuadrilla_subtipo WHERE idsubtipo = $idsubtipo LIMIT 1");
        $nuevoSub = $resNuevo ? mysqli_fetch_assoc($resNuevo) : null;

        if(!$nuevoSub) {
            echo json_encode(['success'=>false,'message'=>'Subtipo no válido']); exit;
        }

        mysqli_begin_transaction($conn);
        try {
            if($cambioSubtipo) {
                $requiereRecarga = true;

                // 1. Si el nuevo subtipo NO tiene pacientes:
                //    - Borrar columnas de pacientes en tabla fechas
                //    - Borrar todas las capacitaciones
                if(!$nuevoSub['tiene_pacientes']) {
                    // Limpiar nro_pacientes en fechas
                    mysqli_query($conn,
                        "UPDATE chc_p_cuadrilla_fecha
                         SET nro_pacientes = NULL
                         WHERE idcuadrilla = $idcuadrilla");
                    // Borrar capacitaciones
                    mysqli_query($conn,
                        "DELETE FROM chc_p_cuadrilla_capacitacion WHERE idcuadrilla = $idcuadrilla");
                }

                // 2. Si el nuevo subtipo NO tiene link:
                if(!$nuevoSub['tiene_link']) {
                    mysqli_query($conn,
                        "UPDATE chc_p_cuadrilla_fecha SET link_actividad = NULL WHERE idcuadrilla = $idcuadrilla");
                }

                // 3. Si el nuevo subtipo NO tiene ubicacion:
                if(!$nuevoSub['tiene_ubicacion']) {
                    mysqli_query($conn,
                        "UPDATE chc_p_cuadrilla_fecha SET ubicacion = NULL WHERE idcuadrilla = $idcuadrilla");
                }

                // 4. Si el nuevo subtipo NO tiene debriefing → borrar registro
                if(!$nuevoSub['tiene_debriefing']) {
                    mysqli_query($conn,
                        "DELETE FROM chc_p_cuadrilla_debriefing WHERE idcuadrilla = $idcuadrilla");
                }

                // 5. Si el nuevo subtipo NO tiene insumos → limpiar campo
                if(!$nuevoSub['tiene_insumos']) {
                    mysqli_query($conn,
                        "UPDATE chc_p_cuadrilla SET insumos = NULL WHERE idcuadrilla = $idcuadrilla");
                }
            }

            // Actualizar cabecera
            $resEsc = mysqli_real_escape_string($conn, $resumen);
            $sql    = "UPDATE chc_p_cuadrilla
                       SET idsubtipo = $idsubtipo,
                           resumen_actividad = '$resEsc',
                           fecha_modificacion = NOW()
                       WHERE idcuadrilla = $idcuadrilla";
            if(!mysqli_query($conn, $sql)) {
                throw new Exception('Error al actualizar: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);
            echo json_encode([
                'success'          => true,
                'message'          => 'Sección 1 actualizada',
                'requiere_recarga' => $requiereRecarga
            ]);

        } catch(Exception $e) {
            mysqli_rollback($conn);
            error_log('CHC editar_seccion1: ' . $e->getMessage());
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        break;

    // ============================================================
    // EDITAR SECCIÓN 2A: Tabla de fechas (todas las filas a la vez)
    // ============================================================
    case 'editar_fechas':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $filasJson   = trim($_POST['filas']         ?? '[]');

        if($idcuadrilla <= 0) {
            echo json_encode(['success'=>false,'message'=>'Datos incompletos']); exit;
        }

        $cuad = verificarCuadrilaEditable($conn, $idcuadrilla, $rutPEC);
        if(!$cuad) {
            echo json_encode(['success'=>false,'message'=>'Cuadrilla no editable']); exit;
        }

        $filas = json_decode($filasJson, true);
        if(!is_array($filas) || empty($filas)) {
            echo json_encode(['success'=>false,'message'=>'Sin filas para guardar']); exit;
        }

        mysqli_begin_transaction($conn);
        try {
            foreach($filas as $fila) {
                $idpcl = intval($fila['idplanclases']);
                $hi    = !empty($fila['hora_inicio'])   ? "'" . mysqli_real_escape_string($conn, $fila['hora_inicio'])   . "'" : "NULL";
                $ht    = !empty($fila['hora_termino'])  ? "'" . mysqli_real_escape_string($conn, $fila['hora_termino'])  . "'" : "NULL";
                $np    = !empty($fila['nro_pacientes']) ? intval($fila['nro_pacientes']) : "NULL";
                $lk    = !empty($fila['link_actividad'])? "'" . mysqli_real_escape_string($conn, $fila['link_actividad'])  . "'" : "NULL";
                $ub    = !empty($fila['ubicacion'])     ? "'" . mysqli_real_escape_string($conn, $fila['ubicacion'])      . "'" : "NULL";

                $sql = "INSERT INTO chc_p_cuadrilla_fecha
                            (idcuadrilla, idplanclases, hora_inicio, hora_termino, nro_pacientes, link_actividad, ubicacion)
                        VALUES ($idcuadrilla, $idpcl, $hi, $ht, $np, $lk, $ub)
                        ON DUPLICATE KEY UPDATE
                            hora_inicio    = VALUES(hora_inicio),
                            hora_termino   = VALUES(hora_termino),
                            nro_pacientes  = VALUES(nro_pacientes),
                            link_actividad = VALUES(link_actividad),
                            ubicacion      = VALUES(ubicacion)";

                if(!mysqli_query($conn, $sql)) {
                    throw new Exception('Error en fila ' . $idpcl . ': ' . mysqli_error($conn));
                }
            }

            mysqli_query($conn,
                "UPDATE chc_p_cuadrilla SET fecha_modificacion = NOW() WHERE idcuadrilla = $idcuadrilla");

            mysqli_commit($conn);
            echo json_encode(['success'=>true,'message'=>'Fechas actualizadas']);

        } catch(Exception $e) {
            mysqli_rollback($conn);
            error_log('CHC editar_fechas: ' . $e->getMessage());
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        break;

    // ============================================================
    // EDITAR SECCIÓN 2B: Capacitaciones (reemplaza todo)
    // ============================================================
    case 'editar_capacitaciones':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $filasJson   = trim($_POST['filas']         ?? '[]');

        if($idcuadrilla <= 0) {
            echo json_encode(['success'=>false,'message'=>'Datos incompletos']); exit;
        }

        $cuad = verificarCuadrilaEditable($conn, $idcuadrilla, $rutPEC);
        if(!$cuad) {
            echo json_encode(['success'=>false,'message'=>'Cuadrilla no editable']); exit;
        }

        $filas = json_decode($filasJson, true);
        if(!is_array($filas) || empty($filas)) {
            echo json_encode(['success'=>false,'message'=>'Debe haber al menos una capacitación']); exit;
        }

        // Validar que la primera tenga modalidad y fecha
        $primera = $filas[0];
        if(empty($primera['modalidad']) || empty($primera['fecha'])) {
            echo json_encode(['success'=>false,'message'=>'La primera capacitación debe tener modalidad y fecha']); exit;
        }

        mysqli_begin_transaction($conn);
        try {
            // Borrar todas las existentes y reinsertar
            mysqli_query($conn,
                "DELETE FROM chc_p_cuadrilla_capacitacion WHERE idcuadrilla = $idcuadrilla");

            foreach($filas as $f) {
                $ord    = intval($f['orden']);
                $mod    = mysqli_real_escape_string($conn, $f['modalidad'] ?? '');
                $fecha  = !empty($f['fecha']) ? "'" . mysqli_real_escape_string($conn, $f['fecha']) . "'" : "NULL";
                $jor    = mysqli_real_escape_string($conn, $f['jornada']  ?? '');

                $sql = "INSERT INTO chc_p_cuadrilla_capacitacion
                            (idcuadrilla, orden, modalidad, fecha, jornada)
                        VALUES ($idcuadrilla, $ord, '$mod', $fecha, '$jor')";
                if(!mysqli_query($conn, $sql)) {
                    throw new Exception('Error en cap ' . $ord . ': ' . mysqli_error($conn));
                }
            }

            mysqli_query($conn,
                "UPDATE chc_p_cuadrilla SET fecha_modificacion = NOW() WHERE idcuadrilla = $idcuadrilla");

            mysqli_commit($conn);
            echo json_encode(['success'=>true,'message'=>'Capacitaciones actualizadas']);

        } catch(Exception $e) {
            mysqli_rollback($conn);
            error_log('CHC editar_capacitaciones: ' . $e->getMessage());
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        break;

    // ============================================================
    // EDITAR SECCIÓN 2C: Insumos
    // ============================================================
    case 'editar_insumos':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $insumos     = trim($_POST['insumos']       ?? '');

        $cuad = verificarCuadrilaEditable($conn, $idcuadrilla, $rutPEC);
        if(!$cuad) {
            echo json_encode(['success'=>false,'message'=>'Cuadrilla no editable']); exit;
        }

        $insEsc = mysqli_real_escape_string($conn, $insumos);
        $sql    = "UPDATE chc_p_cuadrilla
                   SET insumos = '$insEsc', fecha_modificacion = NOW()
                   WHERE idcuadrilla = $idcuadrilla";

        if(mysqli_query($conn, $sql)) {
            echo json_encode(['success'=>true,'message'=>'Insumos actualizados']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Error: ' . mysqli_error($conn)]);
        }
        break;

    // ============================================================
    // EDITAR SECCIÓN 2D: Debriefing / Briefing
    // ============================================================
    case 'editar_debriefing':
        $idcuadrilla = intval($_POST['idcuadrilla'] ?? 0);
        $briefing    = trim($_POST['briefing']      ?? '');
        $debriefing  = trim($_POST['debriefing']    ?? '');

        $cuad = verificarCuadrilaEditable($conn, $idcuadrilla, $rutPEC);
        if(!$cuad) {
            echo json_encode(['success'=>false,'message'=>'Cuadrilla no editable']); exit;
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
            mysqli_query($conn,
                "UPDATE chc_p_cuadrilla SET fecha_modificacion = NOW() WHERE idcuadrilla = $idcuadrilla");
            echo json_encode(['success'=>true,'message'=>'Debriefing actualizado']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Error: ' . mysqli_error($conn)]);
        }
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Acción no reconocida']);
        break;
}
?>
