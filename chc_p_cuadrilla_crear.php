<?php
session_start();
require_once('conexion.php');

// ============================================================
// SEGURIDAD: Verificar sesión activa
// ============================================================
if(empty($_SESSION['sesion_idLogin'])) {
    echo '<div class="alert alert-danger">Sesión no válida. Por favor ingrese nuevamente.</div>';
    exit;
}

$rutPecRaw = $_SESSION['sesion_idLogin'];
$rutPEC    = str_pad($rutPecRaw, 10, "0", STR_PAD_LEFT);

// ============================================================
// PARÁMETROS
// ============================================================
$idSolicitud = isset($_GET['solicitud']) ? intval($_GET['solicitud']) : 0;
$idCuadrilla = isset($_GET['cuadrilla']) ? intval($_GET['cuadrilla']) : 0;

if($idSolicitud <= 0) {
    echo '<div class="alert alert-danger">Solicitud no válida.</div>';
    exit;
}

// ============================================================
// DATOS DE LA SOLICITUD
// ============================================================
$sqlSolicitud = "
    SELECT
        s.idsolicitud, s.codigocurso, s.seccion, s.nombrecurso,
        s.npacientes, s.uso_debriefing, s.idestadoagenda, s.rutpec,
        m.modalidad, m.idmodalidad,
        sm.idmodalidad as idmodalidad_sol
    FROM chc_solicitud s
    LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
    LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
    WHERE s.idsolicitud = $idSolicitud AND s.idestadoagenda = 2
    LIMIT 1
";
$solicitud = mysqli_fetch_assoc(mysqli_query($conn, $sqlSolicitud));

if(!$solicitud) {
    echo '<div class="alert alert-danger">La solicitud no existe o no está confirmada.</div>';
    exit;
}

$idModalidad = intval($solicitud['idmodalidad']);
$npacientesMax = intval($solicitud['npacientes']);
$usaDebriefing = intval($solicitud['uso_debriefing']);

// ============================================================
// SUBTIPOS disponibles para esta modalidad
// ============================================================
$sqlSubtipos = "SELECT idsubtipo, nombre, tiene_seccion3, tiene_pacientes,
                       tiene_insumos, tiene_debriefing, tiene_link, tiene_ubicacion
                FROM chc_p_cuadrilla_subtipo
                WHERE idmodalidad = $idModalidad AND activo = 1
                ORDER BY idsubtipo";
$resSub = mysqli_query($conn, $sqlSubtipos);
$subtipos = array();
while($row = mysqli_fetch_assoc($resSub)) {
    $subtipos[] = $row;
}

// ============================================================
// ACTIVIDADES asociadas a la solicitud (para tabla de fechas)
// ============================================================
$sqlActividades = "
    SELECT
        p.idplanclases,
        DATE_FORMAT(p.pcl_Fecha, '%W, %d de %M de %Y') as fecha_formateada,
        DATE_FORMAT(p.pcl_Inicio, '%H:%i') as hora_inicio_bloque,
        DATE_FORMAT(p.pcl_Termino, '%H:%i') as hora_termino_bloque,
        p.pcl_Inicio as inicio_raw,
        p.pcl_Termino as termino_raw
    FROM chc_solicitud_actividad sa
    INNER JOIN planclases_test p ON sa.idplanclases = p.idplanclases
    WHERE sa.idsolicitud = $idSolicitud
    ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC
";
$resAct = mysqli_query($conn, $sqlActividades);
$actividades = array();
while($row = mysqli_fetch_assoc($resAct)) {
    $actividades[] = $row;
}

// ============================================================
// DATOS EXISTENTES si la cuadrilla ya fue iniciada
// ============================================================
$cuadrilla        = null;
$fechasGuardadas  = array();
$capsGuardadas    = array();
$debriefGuardado  = null;

if($idCuadrilla > 0) {
    // Cabecera
    $cuadrilla = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM chc_p_cuadrilla WHERE idcuadrilla = $idCuadrilla AND idsolicitud = $idSolicitud"));

    if($cuadrilla) {
        // Fechas
        $resFech = mysqli_query($conn, "SELECT * FROM chc_p_cuadrilla_fecha WHERE idcuadrilla = $idCuadrilla");
        while($row = mysqli_fetch_assoc($resFech)) {
            $fechasGuardadas[$row['idplanclases']] = $row;
        }

        // Capacitaciones
        $resCap = mysqli_query($conn, "SELECT * FROM chc_p_cuadrilla_capacitacion WHERE idcuadrilla = $idCuadrilla ORDER BY orden ASC");
        while($row = mysqli_fetch_assoc($resCap)) {
            $capsGuardadas[$row['orden']] = $row;
        }

        // Debriefing
        $debriefGuardado = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM chc_p_cuadrilla_debriefing WHERE idcuadrilla = $idCuadrilla"));
    }
}

// Valores precargados
$subtipoCargado       = $cuadrilla ? intval($cuadrilla['idsubtipo']) : 0;
$resumencargado       = $cuadrilla ? htmlspecialchars($cuadrilla['resumen_actividad']) : '';
$insumosCargado       = $cuadrilla ? htmlspecialchars($cuadrilla['insumos']) : '';
$estadoCuadActual     = $cuadrilla ? intval($cuadrilla['estado']) : 1;

// ============================================================
// Pasar subtipos a JS como JSON para lógica dinámica
// ============================================================
$subtipJSON = json_encode($subtipos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cuadrilla CHC - Solicitud #<?php echo $idSolicitud; ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    body { background: #f4f6f9; }

    .cuadrilla-header {
        background: linear-gradient(135deg, #1a5276, #2980b9);
        color: white;
        padding: 1.5rem 2rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
    }

    /* Wizard de pasos */
    .wizard-steps {
        display: flex;
        justify-content: center;
        gap: 0;
        margin-bottom: 2rem;
    }
    .wizard-step {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.5rem;
        background: #dee2e6;
        color: #6c757d;
        font-weight: 600;
        font-size: 0.9rem;
        border-radius: 0;
        border-right: 1px solid #bbb;
        cursor: default;
    }
    .wizard-step:first-child { border-radius: 8px 0 0 8px; }
    .wizard-step:last-child  { border-radius: 0 8px 8px 0; border-right: none; }
    .wizard-step.active { background: #0d6efd; color: white; }
    .wizard-step.completado { background: #198754; color: white; cursor: pointer; }
    .wizard-step .num {
        background: rgba(255,255,255,0.25);
        border-radius: 50%;
        width: 24px; height: 24px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .wizard-step.active .num,
    .wizard-step.completado .num { background: rgba(255,255,255,0.3); }

    /* Secciones */
    .seccion-card {
        background: white;
        border-radius: 10px;
        padding: 1.8rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 6px rgba(0,0,0,0.07);
        border-left: 4px solid #0d6efd;
    }
    .seccion-titulo {
        font-size: 1.15rem;
        font-weight: 700;
        color: #1a3c6e;
        margin-bottom: 1.2rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e9ecef;
    }

    /* Tabla de fechas */
    .tabla-fechas th { background: #f0f4ff; font-size: 0.85rem; white-space: nowrap; }
    .tabla-fechas td { vertical-align: middle; font-size: 0.88rem; }
    .tabla-fechas .fecha-label { font-weight: 600; color: #1a3c6e; }
    .tabla-fechas .bloque-label { color: #555; font-size: 0.82rem; }

    /* Capacitación */
    .cap-fila {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 0.8rem 1rem;
        margin-bottom: 0.5rem;
        border: 1px solid #dee2e6;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .cap-num {
        background: #0d6efd;
        color: white;
        border-radius: 50%;
        width: 28px; height: 28px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .cap-fila.cap-oculta { display: none !important; }

    /* Botón guardar individual */
    .btn-guardar-campo {
        background: #198754;
        color: white;
        border: none;
        padding: 0.3rem 0.9rem;
        border-radius: 5px;
        font-size: 0.82rem;
        font-weight: 600;
        white-space: nowrap;
        transition: all 0.2s;
    }
    .btn-guardar-campo:hover { background: #146c43; color: white; }
    .btn-guardar-campo:disabled { background: #6c757d; cursor: not-allowed; }

    /* Alertas de guardado */
    .guardado-ok  { color: #198754; font-size: 0.8rem; font-weight: 600; display: none; }
    .guardado-err { color: #dc3545; font-size: 0.8rem; font-weight: 600; display: none; }

    /* Notas de ayuda */
    .nota-campo { font-size: 0.8rem; color: #6c757d; font-style: italic; }

    /* Botones de navegación del wizard */
    .wizard-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #dee2e6;
    }
</style>
</head>
<body>

<div class="container-fluid py-4" style="max-width: 960px;">

    <!-- Header -->
    <div class="cuadrilla-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1">
                    <i class="bi bi-clipboard2-pulse me-2"></i>
                    Formulario de Cuadrilla CHC
                </h4>
                <p class="mb-0 opacity-75">
                    <?php echo $solicitud['codigocurso']; ?>-<?php echo $solicitud['seccion']; ?> — 
                    <?php echo htmlspecialchars($solicitud['nombrecurso']); ?> — 
                    Solicitud #<?php echo $idSolicitud; ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge bg-light text-dark fs-6">
                    <i class="bi bi-tag me-1"></i>
                    <?php echo $solicitud['modalidad']; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Wizard de pasos -->
    <div class="wizard-steps">
        <div class="wizard-step active" id="step-btn-1">
            <span class="num">1</span> Descripción General
        </div>
        <div class="wizard-step" id="step-btn-2">
            <span class="num">2</span> Logística y Programación
        </div>
        <div class="wizard-step" id="step-btn-3">
            <span class="num">3</span> Estaciones
        </div>
    </div>

    <!-- Variables ocultas globales -->
    <input type="hidden" id="idSolicitud"    value="<?php echo $idSolicitud; ?>">
    <input type="hidden" id="idCuadrilla"    value="<?php echo $idCuadrilla > 0 ? $idCuadrilla : 0; ?>">
    <input type="hidden" id="idModalidad"    value="<?php echo $idModalidad; ?>">
    <input type="hidden" id="npacientesMax"  value="<?php echo $npacientesMax; ?>">
    <input type="hidden" id="usaDebriefing"  value="<?php echo $usaDebriefing; ?>">

    <!-- ================================================================== -->
    <!-- PASO 1: Descripción General                                         -->
    <!-- ================================================================== -->
    <div id="paso-1">

        <div class="seccion-card">
            <div class="seccion-titulo">
                <i class="bi bi-1-circle-fill me-2 text-primary"></i>
                Descripción general de la actividad
            </div>

            <!-- Modalidad (informativa) + Subtipo -->
            <div class="row mb-3 align-items-center">
                <div class="col-auto">
                    <span class="fw-bold text-muted me-2">Modalidad:</span>
                    <span class="badge bg-success fs-6"><?php echo $solicitud['modalidad']; ?></span>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold mb-1" style="color: #c0392b;">
                        Subtipo de modalidad <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="selectSubtipo" onchange="onCambioSubtipo()">
                        <option value="">-- Seleccione --</option>
                        <?php foreach($subtipos as $st): ?>
                        <option value="<?php echo $st['idsubtipo']; ?>"
                            data-pacientes="<?php echo $st['tiene_pacientes']; ?>"
                            data-insumos="<?php echo $st['tiene_insumos']; ?>"
                            data-debriefing="<?php echo $st['tiene_debriefing']; ?>"
                            data-link="<?php echo $st['tiene_link']; ?>"
                            data-ubicacion="<?php echo $st['tiene_ubicacion']; ?>"
                            data-seccion3="<?php echo $st['tiene_seccion3']; ?>"
                            <?php echo ($subtipoCargado == $st['idsubtipo']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($st['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Resumen de la actividad -->
            <div class="mb-3">
                <label class="form-label fw-bold">Indique resumen de la actividad: <span class="text-danger">*</span></label>
                <div class="d-flex gap-2 align-items-start">
                    <textarea class="form-control" id="txtResumen" rows="4"
                        placeholder="Describa brevemente el objetivo y contenido de la actividad..."
                        onblur="autoGuardarSeccion1()"><?php echo $resumencargado; ?></textarea>
                    <div class="d-flex flex-column align-items-center gap-1">
                        <button class="btn-guardar-campo" onclick="guardarSeccion1()" id="btnGuardarS1">
                            <i class="bi bi-floppy me-1"></i>Guardar
                        </button>
                        <span class="guardado-ok" id="okS1"><i class="bi bi-check-circle-fill"></i> Guardado</span>
                        <span class="guardado-err" id="errS1"><i class="bi bi-x-circle-fill"></i> Error</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegación paso 1 -->
        <div class="wizard-nav">
            <div>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>
            <div>
                <button class="btn btn-primary" onclick="irAPaso2()" id="btnContinuar1">
                    Continuar <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </div>

    </div><!-- /paso-1 -->

    <!-- ================================================================== -->
    <!-- PASO 2: Logística y Programación                                    -->
    <!-- ================================================================== -->
    <div id="paso-2" style="display:none;">

        <!-- 2A: Tabla de fechas programadas -->
        <div class="seccion-card">
            <div class="seccion-titulo">
                <i class="bi bi-2-circle-fill me-2 text-primary"></i>
                Logística y Programación
            </div>
            <p class="text-muted mb-3">
                Indique hora de inicio, hora de término<?php echo ($npacientesMax > 0) ? ' y número de pacientes simulados' : ''; ?> para cada fecha agendada.
            </p>

            <div class="table-responsive">
                <table class="table table-bordered tabla-fechas">
                    <thead>
                        <tr>
                            <th>Fechas Programadas</th>
                            <th>Bloques Horarios Agendados</th>
                            <th>Hora inicio actividad</th>
                            <th>Hora término actividad</th>
                            <th id="th-pacientes" style="display:none;">Nro Pacientes Simulados</th>
                            <th id="th-link" style="display:none;">Link videoconferencia</th>
                            <th id="th-ubicacion" style="display:none;">Ubicación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($actividades as $act):
                            $pcl_id = $act['idplanclases'];
                            $fGuard  = isset($fechasGuardadas[$pcl_id]) ? $fechasGuardadas[$pcl_id] : null;
                            $hiGuard = $fGuard ? $fGuard['hora_inicio']   : '';
                            $htGuard = $fGuard ? $fGuard['hora_termino']  : '';
                            $npGuard = $fGuard ? $fGuard['nro_pacientes'] : $npacientesMax;
                            $lkGuard = $fGuard ? htmlspecialchars($fGuard['link_actividad']) : '';
                            $ubGuard = $fGuard ? htmlspecialchars($fGuard['ubicacion'])      : '';
                        ?>
                        <tr data-idplanclases="<?php echo $pcl_id; ?>">
                            <td class="fecha-label"><?php echo $act['fecha_formateada']; ?></td>
                            <td class="bloque-label">
                                <?php echo $act['hora_inicio_bloque']; ?> a 
                                <?php echo $act['hora_termino_bloque']; ?> hrs.
                            </td>
                            <td>
                                <select class="form-select form-select-sm sel-hinicio"
                                    data-inicio="<?php echo $act['inicio_raw']; ?>"
                                    data-termino="<?php echo $act['termino_raw']; ?>"
                                    onchange="autoGuardarFecha(this)">
                                    <option value="">--</option>
                                    <?php
                                    // Generar opciones cada 15 minutos dentro del rango del bloque
                                    $tInicio  = strtotime($act['inicio_raw']);
                                    $tTermino = strtotime($act['termino_raw']);
                                    for($t = $tInicio; $t < $tTermino; $t += 900) {
                                        $val  = date('H:i:s', $t);
                                        $lbl  = date('H:i', $t);
                                        $sel  = ($hiGuard == $val || substr($hiGuard,0,5) == $lbl) ? 'selected' : '';
                                        echo "<option value=\"$val\" $sel>$lbl</option>";
                                    }
                                    ?>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm sel-htermino"
                                    data-inicio="<?php echo $act['inicio_raw']; ?>"
                                    data-termino="<?php echo $act['termino_raw']; ?>"
                                    onchange="autoGuardarFecha(this)">
                                    <option value="">--</option>
                                    <?php
                                    // Hora término: desde el mínimo+15 hasta el máximo del bloque
                                    for($t = $tInicio + 900; $t <= $tTermino; $t += 900) {
                                        $val  = date('H:i:s', $t);
                                        $lbl  = date('H:i', $t);
                                        $sel  = ($htGuard == $val || substr($htGuard,0,5) == $lbl) ? 'selected' : '';
                                        echo "<option value=\"$val\" $sel>$lbl</option>";
                                    }
                                    ?>
                                </select>
                            </td>
                            <td class="td-pacientes" style="display:none;">
                                <select class="form-select form-select-sm sel-pacientes"
                                    onchange="autoGuardarFecha(this)">
                                    <option value="">--</option>
                                    <?php for($np = 1; $np <= $npacientesMax; $np++): ?>
                                    <option value="<?php echo $np; ?>" <?php echo ($npGuard == $np) ? 'selected' : ''; ?>>
                                        <?php echo $np; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                            <td class="td-link" style="display:none;">
                                <input type="url" class="form-control form-control-sm inp-link"
                                    placeholder="https://..." value="<?php echo $lkGuard; ?>"
                                    onblur="autoGuardarFecha(this)">
                            </td>
                            <td class="td-ubicacion" style="display:none;">
                                <textarea class="form-control form-control-sm inp-ubicacion"
                                    rows="2" placeholder="Dirección y referencias..."
                                    onblur="autoGuardarFecha(this)"><?php echo $ubGuard; ?></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-2">
                <button class="btn-guardar-campo" onclick="guardarTodasFechas()">
                    <i class="bi bi-floppy me-1"></i>Guardar toda la tabla
                </button>
                <span class="guardado-ok ms-2" id="okFechas"><i class="bi bi-check-circle-fill"></i> Guardado</span>
                <span class="guardado-err ms-2" id="errFechas"><i class="bi bi-x-circle-fill"></i> Error</span>
            </div>
        </div>

        <!-- 2B: Capacitación paciente simulado -->
        <div class="seccion-card" id="bloque-capacitacion" style="display:none;">
            <div class="seccion-titulo">
                <i class="bi bi-person-check-fill me-2 text-primary"></i>
                Capacitación de Paciente Simulado
            </div>
            <p class="text-muted mb-1">
                Indique fechas tentativas, horas y modalidad para realizar la capacitación de paciente simulado.
            </p>
            <p class="nota-campo mb-3">Mínimo 1 fila requerida — máximo 5 fechas tentativas.</p>

            <div id="contenedor-capacitaciones">
                <?php for($i = 1; $i <= 5; $i++):
                    $cap     = isset($capsGuardadas[$i]) ? $capsGuardadas[$i] : null;
                    $modCap  = $cap ? htmlspecialchars($cap['modalidad']) : '';
                    $fecCap  = $cap ? $cap['fecha'] : '';
                    $jorCap  = $cap ? htmlspecialchars($cap['jornada']) : '';
                    $oculta  = ($i > 1 && !$cap) ? 'cap-oculta' : '';
                ?>
                <div class="cap-fila <?php echo $oculta; ?>" id="cap-fila-<?php echo $i; ?>" data-orden="<?php echo $i; ?>">
                    <span class="cap-num"><?php echo $i; ?></span>

                    <div style="min-width:130px;">
                        <label class="form-label form-label-sm mb-0 text-muted">Modalidad</label>
                        <select class="form-select form-select-sm cap-modalidad" onchange="autoGuardarCap(<?php echo $i; ?>)">
                            <option value="">--</option>
                            <option value="Presencial" <?php echo ($modCap == 'Presencial') ? 'selected' : ''; ?>>Presencial</option>
                            <option value="Virtual"    <?php echo ($modCap == 'Virtual')    ? 'selected' : ''; ?>>Virtual</option>
                        </select>
                    </div>

                    <div style="min-width:145px;">
                        <label class="form-label form-label-sm mb-0 text-muted">Fecha</label>
                        <input type="date" class="form-control form-control-sm cap-fecha"
                            value="<?php echo $fecCap; ?>"
                            onchange="autoGuardarCap(<?php echo $i; ?>)">
                    </div>

                    <div style="min-width:150px;">
                        <label class="form-label form-label-sm mb-0 text-muted">Jornada</label>
                        <select class="form-select form-select-sm cap-jornada" onchange="autoGuardarCap(<?php echo $i; ?>)">
                            <option value="">--</option>
                            <option value="AM"          <?php echo ($jorCap == 'AM')          ? 'selected' : ''; ?>>AM</option>
                            <option value="PM"          <?php echo ($jorCap == 'PM')          ? 'selected' : ''; ?>>PM</option>
                            <option value="Todo el día" <?php echo ($jorCap == 'Todo el día') ? 'selected' : ''; ?>>Todo el día</option>
                        </select>
                    </div>

                    <div class="ms-auto d-flex align-items-center gap-2">
                        <span class="guardado-ok" id="okCap<?php echo $i; ?>"><i class="bi bi-check-circle-fill"></i></span>
                        <span class="guardado-err" id="errCap<?php echo $i; ?>"><i class="bi bi-x-circle-fill"></i></span>
                        <?php if($i > 1): ?>
                        <button class="btn btn-sm btn-outline-danger" onclick="quitarCap(<?php echo $i; ?>)" title="Quitar esta fila">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <div class="mt-2 d-flex justify-content-between align-items-center">
                <button class="btn btn-sm btn-outline-primary" id="btnAgregarCap" onclick="agregarCap()">
                    <i class="bi bi-plus-circle me-1"></i>Agregar fecha tentativa
                </button>
                <div>
                    <button class="btn-guardar-campo" onclick="guardarTodasCaps()">
                        <i class="bi bi-floppy me-1"></i>Guardar capacitaciones
                    </button>
                    <span class="guardado-ok ms-2" id="okCaps"><i class="bi bi-check-circle-fill"></i> Guardado</span>
                    <span class="guardado-err ms-2" id="errCaps"><i class="bi bi-x-circle-fill"></i> Error</span>
                </div>
            </div>
        </div>

        <!-- 2C: Insumos (solo subtipo 9) -->
        <div class="seccion-card" id="bloque-insumos" style="display:none;">
            <div class="seccion-titulo">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i>
                Insumos y Equipamiento
            </div>
            <p class="text-muted mb-3">Indique el listado de insumos y equipamiento requerido para la actividad.</p>
            <div class="d-flex gap-2 align-items-start">
                <textarea class="form-control" id="txtInsumos" rows="5"
                    placeholder="Ej: 3 maniquíes de punción, 10 jeringas 5ml, guantes talla M..."
                    onblur="autoGuardarInsumos()"><?php echo $insumosCargado; ?></textarea>
                <div class="d-flex flex-column align-items-center gap-1">
                    <button class="btn-guardar-campo" onclick="guardarInsumos()">
                        <i class="bi bi-floppy me-1"></i>Guardar
                    </button>
                    <span class="guardado-ok" id="okInsumos"><i class="bi bi-check-circle-fill"></i> Guardado</span>
                    <span class="guardado-err" id="errInsumos"><i class="bi bi-x-circle-fill"></i> Error</span>
                </div>
            </div>
        </div>

        <!-- 2D: Debriefing (condicional) -->
        <div class="seccion-card" id="bloque-debriefing" style="display:none;">
            <div class="seccion-titulo">
                <i class="bi bi-people-fill me-2 text-primary"></i>
                Implementación Física — Inducción y Retroalimentación
            </div>
            <p class="text-muted mb-3">
                Indique la implementación del espacio físico necesaria para la inducción (briefing) y retroalimentación (debriefing).
            </p>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width:200px;"></th>
                            <th>Implementación física</th>
                            <th style="width:100px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold align-middle">Inducción (briefing)</td>
                            <td>
                                <textarea class="form-control form-control-sm" id="txtBriefing" rows="2"
                                    placeholder="Describa la implementación necesaria..."
                                    onblur="autoGuardarDebriefing()"><?php echo $debriefGuardado ? htmlspecialchars($debriefGuardado['implementacion_briefing']) : ''; ?></textarea>
                            </td>
                            <td class="align-middle text-center">
                                <button class="btn-guardar-campo" onclick="guardarDebriefing()">
                                    <i class="bi bi-floppy me-1"></i>Guardar
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold align-middle">Retroalimentación (debriefing)</td>
                            <td>
                                <textarea class="form-control form-control-sm" id="txtDebriefing" rows="2"
                                    placeholder="Describa la implementación necesaria..."
                                    onblur="autoGuardarDebriefing()"><?php echo $debriefGuardado ? htmlspecialchars($debriefGuardado['implementacion_debriefing']) : ''; ?></textarea>
                            </td>
                            <td class="align-middle text-center">
                                <span class="guardado-ok" id="okDebrief"><i class="bi bi-check-circle-fill"></i> Guardado</span>
                                <span class="guardado-err" id="errDebrief"><i class="bi bi-x-circle-fill"></i> Error</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Navegación paso 2 -->
        <div class="wizard-nav">
            <button class="btn btn-outline-secondary" onclick="irAPaso1()">
                <i class="bi bi-arrow-left me-1"></i> Paso anterior
            </button>
            <button class="btn btn-primary" onclick="irAPaso3()" id="btnContinuar2">
                Continuar <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>

    </div><!-- /paso-2 -->

    <!-- ================================================================== -->
    <!-- PASO 3: Estaciones (placeholder hasta validación con cliente)        -->
    <!-- ================================================================== -->
    <div id="paso-3" style="display:none;">
        <div class="seccion-card">
            <div class="seccion-titulo">
                <i class="bi bi-3-circle-fill me-2 text-primary"></i>
                Estaciones
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                Esta sección está en proceso de definición con el cliente. Se habilitará próximamente.
            </div>
        </div>

        <div class="wizard-nav">
            <button class="btn btn-outline-secondary" onclick="irAPaso2()">
                <i class="bi bi-arrow-left me-1"></i> Paso anterior
            </button>
            <button class="btn btn-success" onclick="irAVerificacion()">
                <i class="bi bi-check2-circle me-1"></i> Revisar y Verificar Cuadrilla
            </button>
        </div>
    </div><!-- /paso-3 -->

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
// ============================================================
// VARIABLES GLOBALES
// ============================================================
const idSolicitud   = parseInt(document.getElementById('idSolicitud').value);
const idModalidad   = parseInt(document.getElementById('idModalidad').value);
const npacientesMax = parseInt(document.getElementById('npacientesMax').value);
const usaDebriefing = parseInt(document.getElementById('usaDebriefing').value);
let   idCuadrilla   = parseInt(document.getElementById('idCuadrilla').value);

let flagPacientes  = false;
let flagLink       = false;
let flagUbicacion  = false;
let flagDebriefing = false;
let flagInsumos    = false;
let flagSeccion3   = true;
let capVisibles    = 1; // cuántas filas de capacitación están visibles

// Detectar cuántas caps ya tienen datos (al cargar con cuadrilla existente)
document.querySelectorAll('.cap-fila').forEach(fila => {
    if(!fila.classList.contains('cap-oculta')) {
        const ord = parseInt(fila.dataset.orden);
        if(ord > capVisibles) capVisibles = ord;
    }
});

// ============================================================
// INIT: Aplicar flags del subtipo cargado (si viene de edición)
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('selectSubtipo');
    if(sel.value) {
        aplicarFlagsSubtipo(sel.options[sel.selectedIndex]);
    }
});

// ============================================================
// CAMBIO DE SUBTIPO
// ============================================================
function onCambioSubtipo() {
    const sel = document.getElementById('selectSubtipo');
    const opt = sel.options[sel.selectedIndex];
    if(!sel.value) return;
    aplicarFlagsSubtipo(opt);
    // Auto-guardar sección 1 cuando cambia el subtipo
    if(idCuadrilla > 0 || document.getElementById('txtResumen').value.trim()) {
        guardarSeccion1(false);
    }
}

function aplicarFlagsSubtipo(opt) {
    flagPacientes  = opt.dataset.pacientes  === '1';
    flagLink       = opt.dataset.link       === '1';
    flagUbicacion  = opt.dataset.ubicacion  === '1';
    flagDebriefing = opt.dataset.debriefing === '1';
    flagInsumos    = opt.dataset.insumos    === '1';
    flagSeccion3   = opt.dataset.seccion3   === '1';

    // Columnas en tabla de fechas
    document.getElementById('th-pacientes').style.display = flagPacientes ? '' : 'none';
    document.getElementById('th-link').style.display      = flagLink      ? '' : 'none';
    document.getElementById('th-ubicacion').style.display = flagUbicacion ? '' : 'none';
    document.querySelectorAll('.td-pacientes').forEach(td => td.style.display = flagPacientes ? '' : 'none');
    document.querySelectorAll('.td-link').forEach(td      => td.style.display = flagLink      ? '' : 'none');
    document.querySelectorAll('.td-ubicacion').forEach(td => td.style.display = flagUbicacion ? '' : 'none');

    // Bloques opcionales
    document.getElementById('bloque-capacitacion').style.display = flagPacientes ? '' : 'none';
    document.getElementById('bloque-insumos').style.display      = flagInsumos   ? '' : 'none';

    // Debriefing: requiere AMBAS condiciones
    const mostrarDebrief = flagDebriefing && (usaDebriefing === 1);
    document.getElementById('bloque-debriefing').style.display = mostrarDebrief ? '' : 'none';

    // Paso 3 en wizard
    document.getElementById('step-btn-3').style.opacity = flagSeccion3 ? '1' : '0.4';
}

// ============================================================
// NAVEGACIÓN WIZARD
// ============================================================
function irAPaso1() {
    document.getElementById('paso-1').style.display = '';
    document.getElementById('paso-2').style.display = 'none';
    document.getElementById('paso-3').style.display = 'none';
    marcarStep(1);
}

function irAPaso2() {
    // Validar que paso 1 esté completo
    const subtipo = document.getElementById('selectSubtipo').value;
    const resumen = document.getElementById('txtResumen').value.trim();
    if(!subtipo) {
        Swal.fire({ icon:'warning', title:'Atención', text:'Debe seleccionar un subtipo de modalidad.' });
        return;
    }
    if(!resumen) {
        Swal.fire({ icon:'warning', title:'Atención', text:'Debe ingresar el resumen de la actividad.' });
        return;
    }
    // Guardar sección 1 antes de continuar
    guardarSeccion1(true, function() {
        document.getElementById('paso-1').style.display = 'none';
        document.getElementById('paso-2').style.display = '';
        document.getElementById('paso-3').style.display = 'none';
        marcarStep(2);
    });
}

function irAPaso3() {
    if(!flagSeccion3) {
        irAVerificacion();
        return;
    }
    document.getElementById('paso-1').style.display = 'none';
    document.getElementById('paso-2').style.display = 'none';
    document.getElementById('paso-3').style.display = '';
    marcarStep(3);
}

function irAVerificacion() {
    if(idCuadrilla <= 0) {
        Swal.fire({ icon:'warning', title:'Atención', text:'Debe guardar la cuadrilla antes de verificar.' });
        return;
    }
    if(typeof irAVerificarCuadrilla === 'function') {
        irAVerificarCuadrilla(idCuadrilla);
    } else {
        window.location.href = 'chc_p_cuadrilla_verificar.php?cuadrilla=' + idCuadrilla;
    }
}

function marcarStep(paso) {
    for(let i = 1; i <= 3; i++) {
        const btn = document.getElementById('step-btn-' + i);
        btn.classList.remove('active', 'completado');
        if(i < paso)  btn.classList.add('completado');
        if(i === paso) btn.classList.add('active');
    }
}

// ============================================================
// GUARDADO SECCIÓN 1
// ============================================================
function autoGuardarSeccion1() {
    if(document.getElementById('selectSubtipo').value && 
       document.getElementById('txtResumen').value.trim()) {
        guardarSeccion1(false);
    }
}

function guardarSeccion1(mostrarLoader, callback) {
    const idsubtipo = document.getElementById('selectSubtipo').value;
    const resumen   = document.getElementById('txtResumen').value.trim();

    if(!idsubtipo || !resumen) return;

    const btn = document.getElementById('btnGuardarS1');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

    const datos = new FormData();
    datos.append('accion',      'guardar_seccion1');
    datos.append('idsolicitud', idSolicitud);
    datos.append('idcuadrilla', idCuadrilla);
    datos.append('idsubtipo',   idsubtipo);
    datos.append('resumen',     resumen);

    fetch('chc_p_cuadrilla_guardar.php', { method:'POST', body:datos })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
        if(data.success) {
            if(data.idcuadrilla) {
                idCuadrilla = data.idcuadrilla;
                document.getElementById('idCuadrilla').value = idCuadrilla;
            }
            mostrarIndicador('okS1', 'errS1', true);
            if(callback) callback();
        } else {
            mostrarIndicador('okS1', 'errS1', false);
            if(mostrarLoader) Swal.fire({ icon:'error', title:'Error', text: data.message });
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
        mostrarIndicador('okS1', 'errS1', false);
    });
}

// ============================================================
// GUARDADO TABLA DE FECHAS
// ============================================================
function autoGuardarFecha(el) {
    const fila = el.closest('tr');
    guardarFila(fila);
}

function guardarFila(fila) {
    if(idCuadrilla <= 0) return;
    const idplanclases = fila.dataset.idplanclases;
    const hinicio  = fila.querySelector('.sel-hinicio').value;
    const htermino = fila.querySelector('.sel-htermino').value;
    const selPac   = fila.querySelector('.sel-pacientes');
    const inpLink  = fila.querySelector('.inp-link');
    const inpUbic  = fila.querySelector('.inp-ubicacion');

    const datos = new FormData();
    datos.append('accion',      'guardar_fecha');
    datos.append('idcuadrilla', idCuadrilla);
    datos.append('idplanclases',idplanclases);
    datos.append('hora_inicio', hinicio);
    datos.append('hora_termino',htermino);
    if(selPac)  datos.append('nro_pacientes',  selPac.value);
    if(inpLink) datos.append('link_actividad', inpLink.value);
    if(inpUbic) datos.append('ubicacion',      inpUbic.value);

    fetch('chc_p_cuadrilla_guardar.php', { method:'POST', body:datos })
    .then(r => r.json())
    .then(data => {
        mostrarIndicador('okFechas', 'errFechas', data.success);
    })
    .catch(() => mostrarIndicador('okFechas','errFechas', false));
}

function guardarTodasFechas() {
    if(idCuadrilla <= 0) {
        Swal.fire({ icon:'warning', title:'Atención', text:'Primero guarde la Sección 1.' });
        return;
    }
    document.querySelectorAll('.tabla-fechas tbody tr').forEach(fila => guardarFila(fila));
}

// ============================================================
// GUARDADO CAPACITACIONES
// ============================================================
function autoGuardarCap(orden) {
    if(idCuadrilla <= 0) return;
    const fila     = document.getElementById('cap-fila-' + orden);
    const modalidad= fila.querySelector('.cap-modalidad').value;
    const fecha    = fila.querySelector('.cap-fecha').value;
    const jornada  = fila.querySelector('.cap-jornada').value;

    const datos = new FormData();
    datos.append('accion',      'guardar_capacitacion');
    datos.append('idcuadrilla', idCuadrilla);
    datos.append('orden',       orden);
    datos.append('modalidad',   modalidad);
    datos.append('fecha',       fecha);
    datos.append('jornada',     jornada);

    fetch('chc_p_cuadrilla_guardar.php', { method:'POST', body:datos })
    .then(r => r.json())
    .then(data => mostrarIndicador('okCap'+orden, 'errCap'+orden, data.success))
    .catch(() => mostrarIndicador('okCap'+orden, 'errCap'+orden, false));
}

function guardarTodasCaps() {
    for(let i = 1; i <= 5; i++) {
        const fila = document.getElementById('cap-fila-' + i);
        if(!fila.classList.contains('cap-oculta')) {
            autoGuardarCap(i);
        }
    }
    // Mostrar OK global
    setTimeout(() => mostrarIndicador('okCaps', 'errCaps', true), 500);
}

function agregarCap() {
    if(capVisibles >= 5) {
        document.getElementById('btnAgregarCap').disabled = true;
        return;
    }
    capVisibles++;
    const fila = document.getElementById('cap-fila-' + capVisibles);
    fila.classList.remove('cap-oculta');
    if(capVisibles >= 5) {
        document.getElementById('btnAgregarCap').disabled = true;
    }
}

function quitarCap(orden) {
    if(orden <= 1) return; // La primera no se puede quitar
    const fila = document.getElementById('cap-fila-' + orden);
    // Limpiar campos
    fila.querySelector('.cap-modalidad').value = '';
    fila.querySelector('.cap-fecha').value     = '';
    fila.querySelector('.cap-jornada').value   = '';
    fila.classList.add('cap-oculta');
    capVisibles = orden - 1;
    document.getElementById('btnAgregarCap').disabled = false;

    // Eliminar de BD si existe
    if(idCuadrilla > 0) {
        const datos = new FormData();
        datos.append('accion',      'eliminar_capacitacion');
        datos.append('idcuadrilla', idCuadrilla);
        datos.append('orden',       orden);
        fetch('chc_p_cuadrilla_guardar.php', { method:'POST', body:datos });
    }
}

// ============================================================
// GUARDADO INSUMOS
// ============================================================
function autoGuardarInsumos() {
    if(idCuadrilla > 0) guardarInsumos();
}
function guardarInsumos() {
    if(idCuadrilla <= 0) return;
    const datos = new FormData();
    datos.append('accion',      'guardar_insumos');
    datos.append('idcuadrilla', idCuadrilla);
    datos.append('insumos',     document.getElementById('txtInsumos').value);
    fetch('chc_p_cuadrilla_guardar.php', { method:'POST', body:datos })
    .then(r => r.json())
    .then(data => mostrarIndicador('okInsumos','errInsumos', data.success))
    .catch(() => mostrarIndicador('okInsumos','errInsumos', false));
}

// ============================================================
// GUARDADO DEBRIEFING
// ============================================================
function autoGuardarDebriefing() {
    if(idCuadrilla > 0) guardarDebriefing();
}
function guardarDebriefing() {
    if(idCuadrilla <= 0) return;
    const datos = new FormData();
    datos.append('accion',      'guardar_debriefing');
    datos.append('idcuadrilla', idCuadrilla);
    datos.append('briefing',    document.getElementById('txtBriefing').value);
    datos.append('debriefing',  document.getElementById('txtDebriefing').value);
    fetch('chc_p_cuadrilla_guardar.php', { method:'POST', body:datos })
    .then(r => r.json())
    .then(data => mostrarIndicador('okDebrief','errDebrief', data.success))
    .catch(() => mostrarIndicador('okDebrief','errDebrief', false));
}

// ============================================================
// UTILIDADES
// ============================================================
function mostrarIndicador(idOk, idErr, exito) {
    const ok  = document.getElementById(idOk);
    const err = document.getElementById(idErr);
    if(!ok || !err) return;
    ok.style.display  = exito ? 'inline' : 'none';
    err.style.display = exito ? 'none'   : 'inline';
    if(exito) {
        setTimeout(() => { ok.style.display = 'none'; }, 2500);
    }
}
</script>

</body>
</html>
