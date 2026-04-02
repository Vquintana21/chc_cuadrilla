<?php
/**
 * CHC - Verificar Estado de Actividad Clínica
 * Verifica si una actividad del plan de clases clínico tiene solicitud CHC asociada
 * y determina si puede ser editada o eliminada según las reglas de negocio.
 * 
 * =====================================================
 * REGLAS DE NEGOCIO (consistente con index.php)
 * =====================================================
 * 
 * ELIMINACIÓN:
 * - Actividad SIN agenda CHC → Puede eliminarse
 * - Actividad CON agenda CHC → NUNCA puede eliminarse
 * 
 * EDICIÓN:
 * - Actividad SIN agenda CHC → Puede editarse
 * - Estado 2 (Confirmado) → SIEMPRE bloqueado (sin importar período)
 * - Estado 1 (Enviado) + período vencido → Bloqueado
 * - Estado 1 (Enviado) + período vigente → Puede editarse
 * - Estado 3 (Cancelado) → Puede editarse (sin importar período)
 * 
 * =====================================================
 */

header('Content-Type: application/json');
require_once('conexion.php');

// Obtener el ID de la actividad
$idPlanClase = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Debug
error_log("CHC Verificar Actividad Clínica - ID recibido: $idPlanClase");

if ($idPlanClase <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'ID de actividad no válido'
    ]);
    exit;
}

try {
    // =====================================================
    // PASO 1: Verificar si la actividad existe
    // =====================================================
    $sqlActividad = "SELECT idplanclases, pcl_tituloActividad, pcl_Fecha, cursos_idcursos 
                     FROM planclases 
                     WHERE idplanclases = ?";
    $stmtActividad = mysqli_prepare($conn, $sqlActividad);
    mysqli_stmt_bind_param($stmtActividad, "i", $idPlanClase);
    mysqli_stmt_execute($stmtActividad);
    $resultActividad = mysqli_stmt_get_result($stmtActividad);
    $actividad = mysqli_fetch_assoc($resultActividad);
    mysqli_stmt_close($stmtActividad);
    
    if (!$actividad) {
        echo json_encode([
            'success' => false,
            'error' => 'Actividad no encontrada'
        ]);
        exit;
    }
    
    error_log("CHC Verificar - Actividad encontrada: " . $actividad['pcl_tituloActividad']);
    
    // =====================================================
    // PASO 2: Buscar si existe solicitud CHC para esta actividad
    // =====================================================
    $sqlSolicitud = "SELECT 
                        s.idsolicitud,
                        s.idestadoagenda,
                        s.nombrecurso,
                        s.codigocurso,
                        e.estado_agenda,
                        sa.idplanclases
                     FROM chc_solicitud_actividad sa
                     INNER JOIN chc_solicitud s ON sa.idsolicitud = s.idsolicitud
                     LEFT JOIN chc_estado_agenda e ON s.idestadoagenda = e.idestadoagenda
                     WHERE sa.idplanclases = ?";
    
    $stmtSolicitud = mysqli_prepare($conn, $sqlSolicitud);
    mysqli_stmt_bind_param($stmtSolicitud, "i", $idPlanClase);
    mysqli_stmt_execute($stmtSolicitud);
    $resultSolicitud = mysqli_stmt_get_result($stmtSolicitud);
    $solicitud = mysqli_fetch_assoc($resultSolicitud);
    mysqli_stmt_close($stmtSolicitud);
    
    error_log("CHC Verificar - Solicitud encontrada: " . ($solicitud ? "SÍ (ID: {$solicitud['idsolicitud']}, Estado: {$solicitud['idestadoagenda']})" : "NO"));
    
    // =====================================================
    // PASO 3: Si NO tiene solicitud CHC → Puede todo
    // =====================================================
    if (!$solicitud) {
        echo json_encode([
            'success' => true,
            'tiene_agenda_chc' => false,
            'puede_editar' => true,
            'puede_eliminar' => true,
            'mensaje' => '',
            'actividad' => [
                'id' => $actividad['idplanclases'],
                'titulo' => $actividad['pcl_tituloActividad'],
                'fecha' => $actividad['pcl_Fecha']
            ]
        ]);
        exit;
    }
    
    // =====================================================
    // PASO 4: TIENE solicitud CHC → Verificar período y estado
    // =====================================================
    
    // Obtener el período CHC activo
    $sqlPeriodo = "SELECT id, periodo, fin, DATE(fin) as fecha_fin, DATE(NOW()) as hoy 
                   FROM chc_periodosys 
                   WHERE activo = 1 
                   ORDER BY id DESC 
                   LIMIT 1";
    $resultPeriodo = mysqli_query($conn, $sqlPeriodo);
    $periodo = mysqli_fetch_assoc($resultPeriodo);
    
    // Determinar si el período está vencido
    $periodoVencido = false;
    $fechaFin = null;
    
    if ($periodo) {
        $fechaFin = $periodo['fecha_fin'];
        $hoy = $periodo['hoy'];
        // Período vencido si hoy es POSTERIOR a la fecha fin
        $periodoVencido = ($hoy > $fechaFin);
        
        error_log("CHC Verificar - Período: {$periodo['periodo']}, fin=$fechaFin, hoy=$hoy, vencido=" . ($periodoVencido ? 'SÍ' : 'NO'));
    } else {
        error_log("CHC Verificar - No se encontró período activo");
    }
    
    // =====================================================
    // PASO 5: Clasificar estados
    // =====================================================
    // Estado de la solicitud CHC
    // 1 = Enviado, 2 = Confirmado, 3 = Cancelado
    $estadoAgenda = intval($solicitud['idestadoagenda']);
    $estadoEnviado = ($estadoAgenda === 1);
    $estadoConfirmado = ($estadoAgenda === 2);
    $estadoCancelado = ($estadoAgenda === 3);
    
    error_log("CHC Verificar - Estado: $estadoAgenda (Enviado=$estadoEnviado, Confirmado=$estadoConfirmado, Cancelado=$estadoCancelado)");
    
    // =====================================================
    // PASO 6: Aplicar reglas de negocio (IGUAL QUE INDEX.PHP)
    // =====================================================
    
    // REGLA ELIMINAR: Si tiene agenda CHC, NUNCA puede eliminarse
    // REGLA ELIMINAR: 
	// - Estado 3 (Cancelado) → SÍ puede eliminarse
	// - Estado 1 o 2 → NO puede eliminarse
	if ($estadoCancelado) {
		$puedeEliminar = true;
		$mensajeEliminar = '';
	} else {
		$puedeEliminar = false;
		$mensajeEliminar = 'Esta actividad tiene una solicitud CHC asociada (' . $solicitud['estado_agenda'] . ') y no puede ser eliminada.';
	}
    
    // REGLA EDITAR: 
    // BLOQUEADO si: Estado 2 (Confirmado) O (Estado 1 (Enviado) + período vencido)
    // Esto es equivalente a: (estadoCHC == 2) || (estadoCHC == 1 && chcPeriodoVencido)
    $bloqueadoEdicion = $estadoConfirmado || ($estadoEnviado && $periodoVencido);
    
    if ($bloqueadoEdicion) {
        $puedeEditar = false;
        
        if ($estadoConfirmado) {
            $mensajeEditar = 'Esta actividad tiene una solicitud CHC <strong>confirmada</strong> y no puede ser modificada.';
        } else {
            // Estado Enviado + período vencido
            $mensajeEditar = 'El período CHC ha finalizado (' . date('d/m/Y', strtotime($fechaFin)) . '). Esta actividad está <strong>en revisión</strong> y no puede ser modificada.';
        }
    } else {
        // Puede editar si:
        // - Estado 3 (Cancelado) en cualquier momento
        // - Estado 1 (Enviado) + período vigente
        $puedeEditar = true;
        $mensajeEditar = '';
    }
    
    error_log("CHC Verificar - Resultado: bloqueadoEdicion=" . ($bloqueadoEdicion ? 'SÍ' : 'NO') . ", puedeEditar=" . ($puedeEditar ? 'SÍ' : 'NO') . ", puedeEliminar=" . ($puedeEliminar ? 'SÍ' : 'NO'));
    
    // Construir mensaje general
    $mensajeGeneral = '';
    if (!$puedeEditar || !$puedeEliminar) {
        $mensajeGeneral = 'Esta actividad está vinculada a la solicitud CHC #' . $solicitud['idsolicitud'];
        $mensajeGeneral .= ' (' . $solicitud['estado_agenda'] . ')';
    }
    
    // =====================================================
    // RESPUESTA
    // =====================================================
    echo json_encode([
        'success' => true,
        'tiene_agenda_chc' => true,
        'puede_editar' => $puedeEditar,
        'puede_eliminar' => $puedeEliminar,
        'mensaje' => $mensajeGeneral,
        'mensaje_editar' => $mensajeEditar,
        'mensaje_eliminar' => $mensajeEliminar,
        'periodo_vencido' => $periodoVencido,
        'fecha_fin_periodo' => $fechaFin,
        'actividad' => [
            'id' => $actividad['idplanclases'],
            'titulo' => $actividad['pcl_tituloActividad'],
            'fecha' => $actividad['pcl_Fecha']
        ],
        'solicitud_chc' => [
            'id' => $solicitud['idsolicitud'],
            'estado' => $solicitud['idestadoagenda'],
            'estado_nombre' => $solicitud['estado_agenda'],
            'curso' => $solicitud['codigocurso'] . ' - ' . $solicitud['nombrecurso']
        ],
        'debug' => [
            'periodo_fin' => $fechaFin,
            'periodo_hoy' => $periodo ? $periodo['hoy'] : null,
            'estado_agenda_id' => $estadoAgenda,
            'estado_enviado' => $estadoEnviado,
            'estado_confirmado' => $estadoConfirmado,
            'estado_cancelado' => $estadoCancelado,
            'bloqueado_edicion' => $bloqueadoEdicion
        ]
    ]);
    
} catch (Exception $e) {
    error_log("CHC Verificar - ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar la actividad: ' . $e->getMessage()
    ]);
}