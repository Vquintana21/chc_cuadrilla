<?php
// chc_actividades.php
header('Content-Type: application/json; charset=utf-8');

// ✅ PASO 1: Obtener la modalidad de la sesión
session_start();
$idModalidad = isset($_SESSION['chc_modalidad']) ? intval($_SESSION['chc_modalidad']) : 0;

// Log para debug
error_log("CHC Actividades - Modalidad detectada: $idModalidad");

// Incluir archivo de conexión a la base de datos
require_once('conexion2.php');
initLegacyConnections();

// Recibir parámetros - Compatible con PHP 5.6
if(isset($_REQUEST['curso'])) {
    $cursos_idcursos = intval($_REQUEST['curso']);
} else {
    $cursos_idcursos = 0;
}

if(isset($_REQUEST['idplanclases'])) {
    $idplanclases = intval($_REQUEST['idplanclases']);
} else {
    $idplanclases = 0;
}

if(isset($_REQUEST['periodo'])) {
    $periodo = intval($_REQUEST['periodo']);
} else {
    $periodo = 2025;
}

// Inicializar respuesta
$response = array(
    'success' => false,
    'message' => '',
    'data' => array(),
    'debug' => array()
);

// Debug: Ver qué está llegando por POST
$response['debug']['post_recibido'] = $_REQUEST;
$response['debug']['curso_recibido'] = $cursos_idcursos;
$response['debug']['idplanclases_recibido'] = $idplanclases;
$response['debug']['periodo_recibido'] = $periodo;

// Log de debug
error_log("CHC Actividades - Curso: $cursos_idcursos, PlanClase: $idplanclases, Periodo: $periodo, modalidad: $idModalidad");

try {
    // Validar que tenemos conexión
    if(!$conn) {
        throw new Exception('No hay conexion a la base de datos');
    }
    
    // ✅ CONSULTA MODIFICADA: Verificar estado de la solicitud
    $sql = "SELECT 
                p.idplanclases,
                p.pcl_Fecha,
                p.dia,
                DATE_FORMAT(p.pcl_Fecha, '%d/%m/%Y') as fecha_formateada,
                p.pcl_Inicio,
                p.pcl_Termino,
                p.pcl_tituloActividad,
                p.pcl_TipoSesion,
                p.pcl_SubTipoSesion,
                CONCAT(p.pcl_TipoSesion, ' (', p.pcl_SubTipoSesion, ')') as tipo_completo,
                -- ✅ VERIFICAR SI TIENE SOLICITUD Y SU ESTADO
                CASE 
                    WHEN csa.idplanclases IS NOT NULL THEN 1
                    ELSE 0
                END as tiene_solicitud,
                -- ✅ NUEVO: Verificar si la solicitud está cancelada (estado 3)
                CASE 
                    WHEN cs.idestadoagenda = 3 THEN 1
                    ELSE 0
                END as solicitud_cancelada,
                cs.idestadoagenda as estado_agenda
            FROM planclases p
            -- ✅ LEFT JOIN con la tabla de actividades CHC
            LEFT JOIN chc_solicitud_actividad csa ON p.idplanclases = csa.idplanclases
            -- ✅ LEFT JOIN con la tabla de solicitudes para obtener el estado
            LEFT JOIN chc_solicitud cs ON csa.idsolicitud = cs.idsolicitud
            WHERE p.pcl_TipoSesion = 'CHC'
			AND p.pcl_Fecha >= CURDATE()";

    // ✅ FILTRO POR MODALIDAD
    if($idModalidad == 2 || $idModalidad == 3) {
        // Virtual (2) o Exterior (3): Solo CHC + Subtipo CHC
        $sql .= " AND p.pcl_SubTipoSesion = 'Simulación con pacientes simulados'";
        error_log("Aplicando filtro: Solo subtipo CHC para modalidad $idModalidad");
    }
    // Para Presencial (1): No se aplica filtro adicional

    //$sql .= " AND p.pcl_Periodo = ?";
    
    // Agregar condición según el parámetro recibido
    if($idplanclases > 0) {
        $sql .= " AND p.idplanclases = ?";
        $tipo_filtro = "idplanclases";
        $valor_filtro = $idplanclases;
    } elseif($cursos_idcursos > 0) {
        $sql .= " AND p.cursos_idcursos = ?";
        $tipo_filtro = "cursos_idcursos";
        $valor_filtro = $cursos_idcursos;
    } else {
        throw new Exception('Debe proporcionar idplanclases o curso');
    }
    
    $sql .= " ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC";
    
    // Preparar la consulta
    $stmt = mysqli_prepare($conn, $sql);
    
    if($stmt === false) {
        throw new Exception('Error al preparar la consulta: ' . mysqli_error($conn));
    }
    
    // Bindear parámetros según el caso
    if($idplanclases > 0) {
        mysqli_stmt_bind_param($stmt, "i", $idplanclases);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $cursos_idcursos);
    }
    
    // Ejecutar consulta
    if(!mysqli_stmt_execute($stmt)) {
        throw new Exception('Error al ejecutar la consulta: ' . mysqli_stmt_error($stmt));
    }
    
    // Obtener resultados
    $result = mysqli_stmt_get_result($stmt);
    
    if($result === false) {
        throw new Exception('Error al obtener resultados: ' . mysqli_error($conn));
    }
    
    $actividades = array();
    
    while($row = mysqli_fetch_assoc($result)) {
        // ✅ LÓGICA: Si tiene solicitud PERO está cancelada (estado 3), se considera disponible
        $tiene_solicitud_activa = (intval($row['tiene_solicitud']) === 1 && intval($row['solicitud_cancelada']) === 0);
        
        $actividades[] = array(
            'idplanclases' => $row['idplanclases'],
            'fecha' => $row['fecha_formateada'],
            'dia' => $row['dia'],
            'hora_inicio' => substr($row['pcl_Inicio'], 0, 5), // HH:MM
            'hora_termino' => substr($row['pcl_Termino'], 0, 5), // HH:MM
            'titulo_actividad' => $row['pcl_tituloActividad'],
            'tipo_actividad' => $row['pcl_TipoSesion'] . ' (' . $row['pcl_SubTipoSesion'] . ')',
            'tipo_sesion' => $row['pcl_TipoSesion'],
            'subtipo_sesion' => $row['pcl_SubTipoSesion'],
            'tiene_solicitud' => $tiene_solicitud_activa ? 1 : 0, // ✅ Solo bloquear si NO está cancelada
            'solicitud_cancelada' => intval($row['solicitud_cancelada']), // ✅ Indicador de cancelación
            'estado_agenda' => $row['estado_agenda'] // ✅ Para referencia
        );
    }
    
    mysqli_stmt_close($stmt);
    
    $response['success'] = true;
    $response['data'] = $actividades;
    $response['message'] = 'Actividades cargadas correctamente';
    $response['total'] = count($actividades);
    
    // Log de resultado
    error_log("Actividades encontradas: " . count($actividades));
    
} catch(Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['data'] = array();
    
    // Log del error
    error_log("Error en chc_actividades.php: " . $e->getMessage());
}

// Cerrar conexión si está abierta
if($conn) {
    mysqli_close($conn);
}

// Devolver respuesta JSON
echo json_encode($response);
?>