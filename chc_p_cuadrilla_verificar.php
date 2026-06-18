<?php
session_start();
require_once('conexion.php');

if(empty($_SESSION['sesion_idLogin'])) {
    echo '<div class="alert alert-danger">Sesión no válida.</div>'; exit;
}
$rutPecRaw = $_SESSION['sesion_idLogin'];
$rutPEC    = str_pad($rutPecRaw, 10, "0", STR_PAD_LEFT);

$idCuadrilla = isset($_GET['cuadrilla']) ? intval($_GET['cuadrilla']) : 0;
if($idCuadrilla <= 0) {
    echo '<div class="alert alert-danger">Cuadrilla no especificada.</div>'; exit;
}

// Cargar cuadrilla
$rutPECesc = mysqli_real_escape_string($conn, $rutPEC);
$sqlCuad = "
    SELECT cq.*, cs.nombre AS nombre_subtipo,
           cs.tiene_pacientes, cs.tiene_insumos, cs.tiene_debriefing,
           cs.tiene_link, cs.tiene_ubicacion, cs.tiene_seccion3,
           sol.idsolicitud, sol.codigocurso, sol.seccion,
           sol.nombrecurso, sol.npacientes, sol.uso_debriefing,
           m.modalidad, sm.idmodalidad
    FROM chc_p_cuadrilla cq
    INNER JOIN chc_p_cuadrilla_subtipo cs ON cq.idsubtipo   = cs.idsubtipo
    INNER JOIN chc_solicitud sol           ON cq.idsolicitud = sol.idsolicitud
    LEFT  JOIN chc_solicitud_modalidad sm  ON sol.idsolicitud = sm.idsolicitud
    LEFT  JOIN chc_modalidad m             ON sm.idmodalidad  = m.idmodalidad
    WHERE cq.idcuadrilla = $idCuadrilla AND cq.rut_pec = '$rutPECesc'
    LIMIT 1";
$cuadrilla = mysqli_fetch_assoc(mysqli_query($conn, $sqlCuad));

if(!$cuadrilla) {
    echo '<div class="alert alert-danger">Cuadrilla no encontrada o sin acceso.</div>'; exit;
}

$yaEnviada   = ($cuadrilla['estado'] == 3);
$idModalidad = intval($cuadrilla['idmodalidad']);

// Subtipos para edición
$sqlSub = "SELECT idsubtipo,nombre,tiene_pacientes,tiene_insumos,tiene_debriefing,
            tiene_link,tiene_ubicacion,tiene_seccion3
     FROM chc_p_cuadrilla_subtipo WHERE idmodalidad=$idModalidad AND activo=1 ORDER BY idsubtipo";
$resS = mysqli_query($conn, $sqlSub);
$subtipos = array();
while($r=mysqli_fetch_assoc($resS)) $subtipos[]=$r;

// Fechas
$sqlF = "SELECT cf.*,
            DATE_FORMAT(p.pcl_Fecha,'%W, %d de %M de %Y') AS fecha_display,
            DATE_FORMAT(p.pcl_Inicio,'%H:%i')  AS bloque_inicio,
            DATE_FORMAT(p.pcl_Termino,'%H:%i') AS bloque_termino,
            p.pcl_Inicio AS inicio_raw, p.pcl_Termino AS termino_raw
     FROM chc_p_cuadrilla_fecha cf
     INNER JOIN planclases_test p ON cf.idplanclases=p.idplanclases
     WHERE cf.idcuadrilla=$idCuadrilla
     ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC";
$resF = mysqli_query($conn, $sqlF);
$fechas=array();
while($r=mysqli_fetch_assoc($resF)) $fechas[]=$r;

// Caps
$sqlC = "SELECT * FROM chc_p_cuadrilla_capacitacion WHERE idcuadrilla=$idCuadrilla ORDER BY orden ASC";
$resC = mysqli_query($conn, $sqlC);
$caps=array();
while($r=mysqli_fetch_assoc($resC)) $caps[]=$r;
$capsMap=array();
foreach($caps as $c) $capsMap[$c['orden']]=$c;

// Debriefing
$sqlD = "SELECT * FROM chc_p_cuadrilla_debriefing WHERE idcuadrilla=$idCuadrilla";
$debrief=mysqli_fetch_assoc(mysqli_query($conn, $sqlD));

$subtipJSON = json_encode($subtipos);
$npMax      = intval($cuadrilla['npacientes']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Verificar Cuadrilla #<?php echo $idCuadrilla; ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
body{background:#f4f6f9;}
.cuadrilla-header{background:linear-gradient(135deg,#1a5276,#2980b9);color:white;padding:1.5rem 2rem;border-radius:10px;margin-bottom:1.5rem;}
.sec-card{background:white;border-radius:10px;padding:1.6rem;margin-bottom:1.2rem;box-shadow:0 1px 6px rgba(0,0,0,.07);border-left:4px solid #198754;transition:border-color .2s;}
.sec-card.editando{border-left-color:#fd7e14;}
.sec-titulo{font-size:1.05rem;font-weight:700;color:#1a3c6e;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;}
.vista-lectura{display:block;}
.vista-edicion{display:none;}
.sec-card.editando .vista-lectura{display:none;}
.sec-card.editando .vista-edicion{display:block;}
.dato-label{font-size:.78rem;color:#6c757d;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem;}
.dato-valor{font-size:.94rem;color:#212529;}
.dato-vacio{color:#dc3545;font-style:italic;}
.tabla-ver th{background:#f0f4ff;font-size:.82rem;white-space:nowrap;}
.tabla-ver td{font-size:.87rem;vertical-align:middle;}
.btn-editar-sec{background:none;border:1px solid #dee2e6;color:#6c757d;border-radius:6px;padding:.25rem .7rem;font-size:.82rem;transition:all .2s;cursor:pointer;}
.btn-editar-sec:hover{background:#fff3cd;border-color:#ffc107;color:#856404;}
.btn-guardar-sec{background:#198754;color:white;border:none;border-radius:6px;padding:.4rem 1.1rem;font-size:.85rem;font-weight:600;transition:all .2s;}
.btn-guardar-sec:hover{background:#146c43;}
.btn-guardar-sec:disabled{background:#6c757d;cursor:not-allowed;}
.btn-cancelar-sec{background:#6c757d;color:white;border:none;border-radius:6px;padding:.4rem .9rem;font-size:.85rem;cursor:pointer;}
.cap-ed-fila{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;padding:.6rem .8rem;background:#f8f9fa;border-radius:7px;margin-bottom:.4rem;border:1px solid #dee2e6;}
.cap-num-ed{background:#0d6efd;color:white;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0;}
.cap-ed-oculta{display:none!important;}
.alerta-impacto{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:.8rem 1rem;font-size:.88rem;margin-bottom:1rem;display:none;}
.panel-envio{background:white;border-radius:10px;padding:1.5rem 2rem;margin-top:1.5rem;box-shadow:0 1px 6px rgba(0,0,0,.07);border:2px solid #198754;display:flex;justify-content:space-between;align-items:center;}
</style>
</head>
<body>
<div class="container-fluid py-4" style="max-width:980px;">

<div class="cuadrilla-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h4 class="mb-1"><i class="bi bi-clipboard2-check me-2"></i>
                <?php echo $yaEnviada ? 'Cuadrilla Enviada' : 'Verificación de Cuadrilla'; ?>
            </h4>
            <p class="mb-0 opacity-75">
                <?php echo $cuadrilla['codigocurso']; ?>-<?php echo $cuadrilla['seccion']; ?> —
                <?php echo htmlspecialchars($cuadrilla['nombrecurso']); ?> —
                Cuadrilla #<?php echo $idCuadrilla; ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge bg-light text-dark fs-6">
                <i class="bi bi-tag me-1"></i><?php echo $cuadrilla['modalidad']; ?>
            </span>
        </div>
    </div>
</div>

<?php if($yaEnviada): ?>
<div class="alert alert-success">
    <i class="bi bi-send-check-fill me-2"></i>
    <strong>Cuadrilla enviada</strong> el <?php echo date('d/m/Y H:i',strtotime($cuadrilla['fecha_envio'])); ?>.
    Solo lectura.
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle-fill me-2"></i>
    Revise cada sección. Use <strong><i class="bi bi-pencil-fill"></i> Editar sección</strong> para corregir.
    Los cambios solo se guardan al presionar <strong>Guardar sección</strong>.
</div>
<?php endif; ?>

<input type="hidden" id="gIdCuadrilla"   value="<?php echo $idCuadrilla; ?>">
<input type="hidden" id="gNpacientes"    value="<?php echo $npMax; ?>">
<input type="hidden" id="gUsaDebrief"    value="<?php echo $cuadrilla['uso_debriefing']; ?>">
<input type="hidden" id="gSubtipoActual" value="<?php echo $cuadrilla['idsubtipo']; ?>">

<!-- ============================================================
     SECCIÓN 1 — Descripción General
     ============================================================ -->
<div class="sec-card" id="sec1">
    <div class="sec-titulo">
        <span><i class="bi bi-1-circle-fill me-2 text-success"></i>Descripción General</span>
        <?php if(!$yaEnviada): ?>
        <button class="btn-editar-sec" onclick="editarSec(1)">
            <i class="bi bi-pencil-fill me-1"></i>Editar sección
        </button>
        <?php endif; ?>
    </div>
    <!-- Lectura -->
    <div class="vista-lectura">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="dato-label">Modalidad</div>
                <div class="dato-valor"><span class="badge bg-success"><?php echo $cuadrilla['modalidad']; ?></span></div>
            </div>
            <div class="col-md-8">
                <div class="dato-label">Subtipo de modalidad</div>
                <div class="dato-valor fw-bold" id="lbl-subtipo"><?php echo htmlspecialchars($cuadrilla['nombre_subtipo']); ?></div>
            </div>
            <div class="col-12">
                <div class="dato-label">Resumen de la actividad</div>
                <div class="dato-valor border rounded p-2 bg-light" id="lbl-resumen">
                    <?php echo $cuadrilla['resumen_actividad']
                        ? nl2br(htmlspecialchars($cuadrilla['resumen_actividad']))
                        : '<span class="dato-vacio">Sin resumen</span>'; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Edición -->
    <div class="vista-edicion">
        <div class="alerta-impacto" id="alerta-subtipo">
            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
            <strong>Atención:</strong> Cambiar el subtipo eliminará datos incompatibles (pacientes, capacitaciones, debriefing y/o insumos).
            Los datos solo se borran al presionar <strong>Guardar sección</strong>.
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <label class="form-label fw-bold">Subtipo de modalidad <span class="text-danger">*</span></label>
                <select class="form-select" id="ed-subtipo" onchange="onCambioSubtipoVerif()">
                    <?php foreach($subtipos as $st): ?>
                    <option value="<?php echo $st['idsubtipo']; ?>"
                        data-pacientes="<?php echo $st['tiene_pacientes']; ?>"
                        data-insumos="<?php echo $st['tiene_insumos']; ?>"
                        data-debriefing="<?php echo $st['tiene_debriefing']; ?>"
                        data-link="<?php echo $st['tiene_link']; ?>"
                        data-ubicacion="<?php echo $st['tiene_ubicacion']; ?>"
                        data-nombre="<?php echo htmlspecialchars($st['nombre']); ?>"
                        <?php echo ($cuadrilla['idsubtipo']==$st['idsubtipo'])?'selected':''; ?>>
                        <?php echo htmlspecialchars($st['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Resumen <span class="text-danger">*</span></label>
                <textarea class="form-control" id="ed-resumen" rows="4"><?php
                    echo htmlspecialchars(isset($cuadrilla['resumen_actividad']) ? $cuadrilla['resumen_actividad'] : '');
                ?></textarea>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn-guardar-sec" onclick="guardarS1()" id="btnGS1">
                <i class="bi bi-floppy me-1"></i>Guardar sección
            </button>
            <button class="btn-cancelar-sec" onclick="cancelarSec(1)">Cancelar</button>
        </div>
    </div>
</div>

<!-- ============================================================
     SECCIÓN 2A — Fechas Programadas
     ============================================================ -->
<div class="sec-card" id="sec2a">
    <div class="sec-titulo">
        <span><i class="bi bi-calendar3 me-2 text-success"></i>Fechas Programadas</span>
        <?php if(!$yaEnviada): ?>
        <button class="btn-editar-sec" onclick="editarSec('2a')">
            <i class="bi bi-pencil-fill me-1"></i>Editar sección
        </button>
        <?php endif; ?>
    </div>
    <!-- Lectura -->
    <div class="vista-lectura">
        <?php if(empty($fechas)): ?>
            <p class="dato-vacio"><i class="bi bi-exclamation-triangle me-1"></i>Sin fechas registradas.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered tabla-ver">
                <thead><tr>
                    <th>Fecha</th><th>Bloque agendado</th><th>H. Inicio</th><th>H. Término</th>
                    <?php if($cuadrilla['tiene_pacientes']): ?><th>Nro Pac.</th><?php endif; ?>
                    <?php if($cuadrilla['tiene_link']):      ?><th>Link</th><?php endif; ?>
                    <?php if($cuadrilla['tiene_ubicacion']): ?><th>Ubicación</th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach($fechas as $f): ?>
                <tr>
                    <td><?php echo $f['fecha_display']; ?></td>
                    <td><?php echo $f['bloque_inicio']; ?> — <?php echo $f['bloque_termino']; ?> hrs.</td>
                    <td><?php echo $f['hora_inicio']  ? date('H:i',strtotime($f['hora_inicio']))  : '<span class="dato-vacio">—</span>'; ?></td>
                    <td><?php echo $f['hora_termino'] ? date('H:i',strtotime($f['hora_termino'])) : '<span class="dato-vacio">—</span>'; ?></td>
                    <?php if($cuadrilla['tiene_pacientes']): ?>
                    <td><?php echo isset($f['nro_pacientes']) ? $f['nro_pacientes'] : '<span class="dato-vacio">—</span>'; ?></td>
                    <?php endif; ?>
                    <?php if($cuadrilla['tiene_link']): ?>
                    <td><?php echo $f['link_actividad'] ? '<a href="'.htmlspecialchars($f['link_actividad']).'" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Ver</a>' : '<span class="dato-vacio">—</span>'; ?></td>
                    <?php endif; ?>
                    <?php if($cuadrilla['tiene_ubicacion']): ?>
                    <td><?php echo $f['ubicacion'] ? htmlspecialchars($f['ubicacion']) : '<span class="dato-vacio">—</span>'; ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <!-- Edición -->
    <div class="vista-edicion">
        <div class="table-responsive">
            <table class="table table-bordered tabla-ver">
                <thead><tr>
                    <th>Fecha</th><th>Bloque agendado</th><th>H. Inicio</th><th>H. Término</th>
                    <th id="th-ed-pac"  class="<?php echo !$cuadrilla['tiene_pacientes']?'d-none':''; ?>">Nro Pac.</th>
                    <th id="th-ed-link" class="<?php echo !$cuadrilla['tiene_link']?     'd-none':''; ?>">Link</th>
                    <th id="th-ed-ubic" class="<?php echo !$cuadrilla['tiene_ubicacion']?'d-none':''; ?>">Ubicación</th>
                </tr></thead>
                <tbody id="tbody-ed-fechas">
                <?php foreach($fechas as $f):
                    $tI=strtotime($f['inicio_raw']); $tT=strtotime($f['termino_raw']); ?>
                <tr data-idplanclases="<?php echo $f['idplanclases']; ?>">
                    <td class="small fw-bold"><?php echo $f['fecha_display']; ?></td>
                    <td class="small"><?php echo $f['bloque_inicio']; ?> — <?php echo $f['bloque_termino']; ?></td>
                    <td>
                        <select class="form-select form-select-sm sel-ed-hi">
                            <option value="">--</option>
                            <?php for($t=$tI;$t<$tT;$t+=900):
                                $v=date('H:i:s',$t);$l=date('H:i',$t);
                                $s=(substr($f['hora_inicio'],0,5)==$l)?'selected':'';
                                echo "<option value=\"$v\" $s>$l</option>";
                            endfor; ?>
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm sel-ed-ht">
                            <option value="">--</option>
                            <?php for($t=$tI+900;$t<=$tT;$t+=900):
                                $v=date('H:i:s',$t);$l=date('H:i',$t);
                                $s=(substr($f['hora_termino'],0,5)==$l)?'selected':'';
                                echo "<option value=\"$v\" $s>$l</option>";
                            endfor; ?>
                        </select>
                    </td>
                    <td class="td-ed-pac <?php echo !$cuadrilla['tiene_pacientes']?'d-none':''; ?>">
                        <select class="form-select form-select-sm sel-ed-pac">
                            <option value="">--</option>
                            <?php for($np=1;$np<=$npMax;$np++):
                                $s=($f['nro_pacientes']==$np)?'selected':'';
                                echo "<option value=\"$np\" $s>$np</option>";
                            endfor; ?>
                        </select>
                    </td>
                    <td class="td-ed-link <?php echo !$cuadrilla['tiene_link']?'d-none':''; ?>">
                        <input type="url" class="form-control form-control-sm inp-ed-link"
                            value="<?php echo htmlspecialchars(isset($f['link_actividad']) ? $f['link_actividad'] : ''); ?>" placeholder="https://...">
                    </td>
                    <td class="td-ed-ubic <?php echo !$cuadrilla['tiene_ubicacion']?'d-none':''; ?>">
                        <textarea class="form-control form-control-sm inp-ed-ubic" rows="2"><?php
                            echo htmlspecialchars(isset($f['ubicacion']) ? $f['ubicacion'] : '');
                        ?></textarea>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex gap-2 mt-2">
            <button class="btn-guardar-sec" onclick="guardarS2a()" id="btnGS2a">
                <i class="bi bi-floppy me-1"></i>Guardar sección
            </button>
            <button class="btn-cancelar-sec" onclick="cancelarSec('2a')">Cancelar</button>
        </div>
    </div>
</div>

<!-- ============================================================
     SECCIÓN 2B — Capacitación PS
     ============================================================ -->
<?php if($cuadrilla['tiene_pacientes']): ?>
<div class="sec-card" id="sec2b">
    <div class="sec-titulo">
        <span><i class="bi bi-person-check-fill me-2 text-success"></i>Capacitación Paciente Simulado</span>
        <?php if(!$yaEnviada): ?>
        <button class="btn-editar-sec" onclick="editarSec('2b')">
            <i class="bi bi-pencil-fill me-1"></i>Editar sección
        </button>
        <?php endif; ?>
    </div>
    <div class="vista-lectura">
        <?php if(empty($caps)): ?>
            <p class="dato-vacio"><i class="bi bi-exclamation-triangle me-1"></i>Sin fechas de capacitación.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered tabla-ver">
                <thead><tr><th>#</th><th>Modalidad</th><th>Fecha</th><th>Jornada</th></tr></thead>
                <tbody>
                <?php foreach($caps as $cap): ?>
                <tr>
                    <td><?php echo $cap['orden']; ?></td>
                    <td><?php echo htmlspecialchars(isset($cap['modalidad']) ? $cap['modalidad'] : '—'); ?></td>
                    <td><?php echo $cap['fecha'] ? date('d/m/Y',strtotime($cap['fecha'])) : '—'; ?></td>
                    <td><?php echo htmlspecialchars(isset($cap['jornada']) ? $cap['jornada'] : '—'); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="vista-edicion">
        <p class="text-muted small mb-2">Mínimo 1 obligatoria — máximo 5.</p>
        <div id="ed-caps-cont">
            <?php for($i=1;$i<=5;$i++):
                $c=isset($capsMap[$i])?$capsMap[$i]:null;
                $ocu=($i>1&&!$c)?'cap-ed-oculta':'';
            ?>
            <div class="cap-ed-fila <?php echo $ocu; ?>" id="ed-cap-<?php echo $i; ?>" data-orden="<?php echo $i; ?>">
                <span class="cap-num-ed"><?php echo $i; ?></span>
                <div style="min-width:120px;">
                    <label class="form-label form-label-sm mb-0 text-muted">Modalidad</label>
                    <select class="form-select form-select-sm ed-cap-mod">
                        <option value="">--</option>
                        <option value="Presencial" <?php echo ($c&&$c['modalidad']=='Presencial')?'selected':''; ?>>Presencial</option>
                        <option value="Virtual"    <?php echo ($c&&$c['modalidad']=='Virtual')   ?'selected':''; ?>>Virtual</option>
                    </select>
                </div>
                <div style="min-width:140px;">
                    <label class="form-label form-label-sm mb-0 text-muted">Fecha</label>
                    <input type="date" class="form-control form-control-sm ed-cap-fecha"
                        value="<?php echo $c?$c['fecha']:''; ?>">
                </div>
                <div style="min-width:140px;">
                    <label class="form-label form-label-sm mb-0 text-muted">Jornada</label>
                    <select class="form-select form-select-sm ed-cap-jor">
                        <option value="">--</option>
                        <option value="AM"          <?php echo ($c&&$c['jornada']=='AM')         ?'selected':''; ?>>AM</option>
                        <option value="PM"          <?php echo ($c&&$c['jornada']=='PM')         ?'selected':''; ?>>PM</option>
                        <option value="Todo el día" <?php echo ($c&&$c['jornada']=='Todo el día')?'selected':''; ?>>Todo el día</option>
                    </select>
                </div>
                <?php if($i>1): ?>
                <button class="btn btn-sm btn-outline-danger ms-auto" onclick="edQuitarCap(<?php echo $i; ?>)" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
        <div class="mt-2 d-flex justify-content-between align-items-center">
            <button class="btn btn-sm btn-outline-primary" id="btnEdAgregarCap" onclick="edAgregarCap()">
                <i class="bi bi-plus-circle me-1"></i>Agregar fecha
            </button>
            <div class="d-flex gap-2">
                <button class="btn-guardar-sec" onclick="guardarS2b()" id="btnGS2b">
                    <i class="bi bi-floppy me-1"></i>Guardar sección
                </button>
                <button class="btn-cancelar-sec" onclick="cancelarSec('2b')">Cancelar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     SECCIÓN 2C — Insumos
     ============================================================ -->
<?php if($cuadrilla['tiene_insumos']): ?>
<div class="sec-card" id="sec2c">
    <div class="sec-titulo">
        <span><i class="bi bi-box-seam-fill me-2 text-success"></i>Insumos y Equipamiento</span>
        <?php if(!$yaEnviada): ?>
        <button class="btn-editar-sec" onclick="editarSec('2c')">
            <i class="bi bi-pencil-fill me-1"></i>Editar sección
        </button>
        <?php endif; ?>
    </div>
    <div class="vista-lectura">
        <div class="dato-valor border rounded p-2 bg-light" id="lbl-insumos">
            <?php echo $cuadrilla['insumos']
                ? nl2br(htmlspecialchars($cuadrilla['insumos']))
                : '<span class="dato-vacio">Sin insumos registrados</span>'; ?>
        </div>
    </div>
    <div class="vista-edicion">
        <textarea class="form-control mb-3" id="ed-insumos" rows="5"
            placeholder="Ej: 3 maniquíes de punción, 10 jeringas 5ml..."><?php
            echo htmlspecialchars(isset($cuadrilla['insumos']) ? $cuadrilla['insumos'] : '');
        ?></textarea>
        <div class="d-flex gap-2">
            <button class="btn-guardar-sec" onclick="guardarS2c()" id="btnGS2c">
                <i class="bi bi-floppy me-1"></i>Guardar sección
            </button>
            <button class="btn-cancelar-sec" onclick="cancelarSec('2c')">Cancelar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     SECCIÓN 2D — Debriefing
     ============================================================ -->
<?php if($cuadrilla['tiene_debriefing'] && $cuadrilla['uso_debriefing']==1): ?>
<div class="sec-card" id="sec2d">
    <div class="sec-titulo">
        <span><i class="bi bi-people-fill me-2 text-success"></i>Inducción y Retroalimentación</span>
        <?php if(!$yaEnviada): ?>
        <button class="btn-editar-sec" onclick="editarSec('2d')">
            <i class="bi bi-pencil-fill me-1"></i>Editar sección
        </button>
        <?php endif; ?>
    </div>
    <div class="vista-lectura">
        <div class="row g-3">
            <div class="col-12">
                <div class="dato-label">Inducción (briefing)</div>
                <div class="dato-valor border rounded p-2 bg-light" id="lbl-briefing">
                    <?php echo ($debrief&&$debrief['implementacion_briefing'])
                        ? nl2br(htmlspecialchars($debrief['implementacion_briefing']))
                        : '<span class="dato-vacio">Sin registrar</span>'; ?>
                </div>
            </div>
            <div class="col-12">
                <div class="dato-label">Retroalimentación (debriefing)</div>
                <div class="dato-valor border rounded p-2 bg-light" id="lbl-debriefing">
                    <?php echo ($debrief&&$debrief['implementacion_debriefing'])
                        ? nl2br(htmlspecialchars($debrief['implementacion_debriefing']))
                        : '<span class="dato-vacio">Sin registrar</span>'; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="vista-edicion">
        <div class="mb-3">
            <label class="form-label fw-bold">Inducción (briefing)</label>
            <textarea class="form-control" id="ed-briefing" rows="3"><?php
                echo htmlspecialchars(isset($debrief['implementacion_briefing']) ? $debrief['implementacion_briefing'] : '');
            ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Retroalimentación (debriefing)</label>
            <textarea class="form-control" id="ed-debriefing" rows="3"><?php
                echo htmlspecialchars(isset($debrief['implementacion_debriefing']) ? $debrief['implementacion_debriefing'] : '');
            ?></textarea>
        </div>
        <div class="d-flex gap-2">
            <button class="btn-guardar-sec" onclick="guardarS2d()" id="btnGS2d">
                <i class="bi bi-floppy me-1"></i>Guardar sección
            </button>
            <button class="btn-cancelar-sec" onclick="cancelarSec('2d')">Cancelar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     PANEL ENVÍO / VOLVER
     ============================================================ -->
<?php if(!$yaEnviada): ?>
<div class="panel-envio">
    <div>
        <h5 class="mb-1"><i class="bi bi-check2-all me-2 text-success"></i>¿Todo está correcto?</h5>
        <p class="mb-0 text-muted small">Una vez enviada no podrá modificarla.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary"
                onclick="if(typeof irACuadrilla==='function'){irACuadrilla(<?php echo $cuadrilla['idsolicitud']; ?>,<?php echo $idCuadrilla; ?>)}else{window.location.href='chc_p_cuadrilla_crear.php?solicitud=<?php echo $cuadrilla['idsolicitud']; ?>&cuadrilla=<?php echo $idCuadrilla; ?>';}">
            <i class="bi bi-arrow-left me-1"></i>Volver al formulario
        </button>
        <button class="btn btn-success btn-lg px-4" onclick="confirmarEnvio()">
            <i class="bi bi-send-fill me-2"></i>Enviar Cuadrilla
        </button>
    </div>
</div>
<?php else: ?>
<div class="d-flex justify-content-between mt-3">
    <a href="javascript:history.back()" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
    <a href="chc_p_cuadrilla_pdf.php?cuadrilla=<?php echo $idCuadrilla; ?>" target="_blank"
       class="btn btn-outline-danger">
        <i class="bi bi-file-pdf-fill me-1"></i>Ver PDF
    </a>
</div>
<?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
const idCuadrilla  = parseInt(document.getElementById('gIdCuadrilla').value);
const npacMax      = parseInt(document.getElementById('gNpacientes').value);
const usaDebrief   = parseInt(document.getElementById('gUsaDebrief').value);
let   subtipActual = parseInt(document.getElementById('gSubtipoActual').value);
let   edCapVis     = <?php echo max(1,count($caps)); ?>;

// ---- Control de secciones ----
function editarSec(id) {
    document.querySelectorAll('.sec-card.editando').forEach(c=>c.classList.remove('editando'));
    const card = document.getElementById('sec'+id);
    card.classList.add('editando');
    card.scrollIntoView({behavior:'smooth',block:'start'});
}
function cancelarSec(id) {
    document.getElementById('sec'+id).classList.remove('editando');
}

// ---- Sección 1: detectar cambio subtipo ----
function onCambioSubtipoVerif() {
    const sel    = document.getElementById('ed-subtipo');
    const nuevo  = parseInt(sel.value);
    document.getElementById('alerta-subtipo').style.display = (nuevo!==subtipActual)?'block':'none';
}

// ---- Sección 1: guardar ----
function guardarS1() {
    const sel     = document.getElementById('ed-subtipo');
    const resumen = document.getElementById('ed-resumen').value.trim();
    const nuevoId = parseInt(sel.value);
    const opt     = sel.options[sel.selectedIndex];
    if(!resumen){ Swal.fire({icon:'warning',title:'Atención',text:'El resumen no puede estar vacío.'}); return; }

    const cambio = (nuevoId !== subtipActual);
    if(cambio) {
        const perdidos=[];
        if(opt.dataset.pacientes!=='1') perdidos.push('datos de pacientes y capacitaciones');
        if(opt.dataset.debriefing!=='1') perdidos.push('datos de inducción/debriefing');
        if(opt.dataset.insumos!=='1' && <?php echo $cuadrilla['tiene_insumos'] ? 'true' : 'false'; ?>) perdidos.push('insumos');
        const msg = perdidos.length
            ? 'Se eliminarán: <strong>'+perdidos.join(', ')+'</strong>.'
            : 'Solo cambiará la configuración de campos visibles.';
        Swal.fire({
            icon:'warning', title:'¿Confirmar cambio de subtipo?',
            html: msg+'<br><br>Los datos se borran al guardar.',
            showCancelButton:true, confirmButtonColor:'#dc3545', cancelButtonColor:'#6c757d',
            confirmButtonText:'Sí, cambiar', cancelButtonText:'Cancelar'
        }).then(r=>{ if(r.isConfirmed) ejecutarS1(nuevoId,resumen,opt); });
    } else {
        ejecutarS1(nuevoId,resumen,opt);
    }
}

function ejecutarS1(idsubtipo,resumen,opt) {
    const btn=document.getElementById('btnGS1');
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    const fd=new FormData();
    fd.append('accion','editar_seccion1'); fd.append('idcuadrilla',idCuadrilla);
    fd.append('idsubtipo',idsubtipo);      fd.append('resumen',resumen);
    fetch('chc_p_cuadrilla_editar.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(data=>{
        btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección';
        if(data.success){
            subtipActual=idsubtipo;
            document.getElementById('gSubtipoActual').value=idsubtipo;
            document.getElementById('lbl-subtipo').textContent=opt.dataset.nombre;
            document.getElementById('lbl-resumen').innerHTML=resumen.replace(/\n/g,'<br>');
            document.getElementById('alerta-subtipo').style.display='none';
            cancelarSec(1);
            if(data.requiere_recarga){
                Swal.fire({icon:'success',title:'Guardado',text:'La página se recargará para reflejar los cambios.',timer:2000,showConfirmButton:false})
                .then(()=>location.reload());
            } else { toastOk('Sección 1 guardada'); }
        } else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección'; });
}

// ---- Sección 2A: guardar fechas ----
function guardarS2a() {
    const btn=document.getElementById('btnGS2a');
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    const filas=[];
    document.querySelectorAll('#tbody-ed-fechas tr').forEach(fila=>{
        const sp=fila.querySelector('.sel-ed-pac');
        const il=fila.querySelector('.inp-ed-link');
        const iu=fila.querySelector('.inp-ed-ubic');
        filas.push({
            idplanclases:fila.dataset.idplanclases,
            hora_inicio:fila.querySelector('.sel-ed-hi').value,
            hora_termino:fila.querySelector('.sel-ed-ht').value,
            nro_pacientes:sp?sp.value:'',
            link_actividad:il?il.value:'',
            ubicacion:iu?iu.value:''
        });
    });
    const fd=new FormData();
    fd.append('accion','editar_fechas'); fd.append('idcuadrilla',idCuadrilla);
    fd.append('filas',JSON.stringify(filas));
    fetch('chc_p_cuadrilla_editar.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(data=>{
        btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección';
        if(data.success){ cancelarSec('2a'); toastOk('Fechas guardadas'); setTimeout(()=>location.reload(),800); }
        else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección'; });
}

// ---- Sección 2B: capacitaciones ----
function edAgregarCap() {
    if(edCapVis>=5) return;
    edCapVis++;
    document.getElementById('ed-cap-'+edCapVis).classList.remove('cap-ed-oculta');
    if(edCapVis>=5) document.getElementById('btnEdAgregarCap').disabled=true;
}
function edQuitarCap(ord) {
    if(ord<=1) return;
    const f=document.getElementById('ed-cap-'+ord);
    f.querySelector('.ed-cap-mod').value='';
    f.querySelector('.ed-cap-fecha').value='';
    f.querySelector('.ed-cap-jor').value='';
    f.classList.add('cap-ed-oculta');
    edCapVis=ord-1;
    document.getElementById('btnEdAgregarCap').disabled=false;
}
function guardarS2b() {
    const mod1=document.querySelector('#ed-cap-1 .ed-cap-mod').value;
    const fec1=document.querySelector('#ed-cap-1 .ed-cap-fecha').value;
    if(!mod1||!fec1){ Swal.fire({icon:'warning',title:'Atención',text:'La primera fecha de capacitación es obligatoria.'}); return; }
    const btn=document.getElementById('btnGS2b');
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    const filas=[];
    for(let i=1;i<=5;i++){
        const f=document.getElementById('ed-cap-'+i);
        if(!f.classList.contains('cap-ed-oculta')){
            filas.push({orden:i,
                modalidad:f.querySelector('.ed-cap-mod').value,
                fecha:f.querySelector('.ed-cap-fecha').value,
                jornada:f.querySelector('.ed-cap-jor').value});
        }
    }
    const fd=new FormData();
    fd.append('accion','editar_capacitaciones'); fd.append('idcuadrilla',idCuadrilla);
    fd.append('filas',JSON.stringify(filas));
    fetch('chc_p_cuadrilla_editar.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(data=>{
        btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección';
        if(data.success){ cancelarSec('2b'); toastOk('Capacitaciones guardadas'); setTimeout(()=>location.reload(),800); }
        else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección'; });
}

// ---- Sección 2C: insumos ----
function guardarS2c() {
    const btn=document.getElementById('btnGS2c');
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    const fd=new FormData();
    fd.append('accion','editar_insumos'); fd.append('idcuadrilla',idCuadrilla);
    fd.append('insumos',document.getElementById('ed-insumos').value);
    fetch('chc_p_cuadrilla_editar.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(data=>{
        btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección';
        if(data.success){
            document.getElementById('lbl-insumos').innerHTML=
                document.getElementById('ed-insumos').value.replace(/\n/g,'<br>')||'<span class="dato-vacio">Sin insumos</span>';
            cancelarSec('2c'); toastOk('Insumos guardados');
        } else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección'; });
}

// ---- Sección 2D: debriefing ----
function guardarS2d() {
    const btn=document.getElementById('btnGS2d');
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    const fd=new FormData();
    fd.append('accion','editar_debriefing'); fd.append('idcuadrilla',idCuadrilla);
    fd.append('briefing',document.getElementById('ed-briefing').value);
    fd.append('debriefing',document.getElementById('ed-debriefing').value);
    fetch('chc_p_cuadrilla_editar.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(data=>{
        btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección';
        if(data.success){
            document.getElementById('lbl-briefing').innerHTML=
                document.getElementById('ed-briefing').value.replace(/\n/g,'<br>')||'<span class="dato-vacio">Sin registrar</span>';
            document.getElementById('lbl-debriefing').innerHTML=
                document.getElementById('ed-debriefing').value.replace(/\n/g,'<br>')||'<span class="dato-vacio">Sin registrar</span>';
            cancelarSec('2d'); toastOk('Debriefing guardado');
        } else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy me-1"></i>Guardar sección'; });
}

// ---- Envío final ----
function confirmarEnvio() {
    Swal.fire({
        icon:'question', title:'¿Enviar cuadrilla?',
        html:'Una vez enviada <strong>no podrá modificarla</strong>.<br>Los administradores CHC recibirán una notificación con el PDF.',
        showCancelButton:true, confirmButtonColor:'#198754', cancelButtonColor:'#6c757d',
        confirmButtonText:'<i class="bi bi-send-fill me-1"></i>Sí, enviar', cancelButtonText:'Cancelar'
    }).then(r=>{ if(r.isConfirmed) enviarCuadrilla(); });
}

function enviarCuadrilla() {
    Swal.fire({title:'Enviando...',html:'Generando PDF y notificando...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    const fd=new FormData();
    fd.append('accion','enviar_cuadrilla'); fd.append('idcuadrilla',idCuadrilla);
    fetch('chc_p_cuadrilla_enviar.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(data=>{
        if(data.success){
            Swal.fire({icon:'success',title:'¡Cuadrilla enviada!',text:'Los administradores CHC han sido notificados.',confirmButtonColor:'#198754'})
            .then(()=>location.reload());
        } else { Swal.fire({icon:'error',title:'Error',text:data.message}); }
    }).catch(()=>Swal.fire({icon:'error',title:'Error',text:'Error de conexión'}));
}

function toastOk(msg) {
    Swal.fire({toast:true,position:'top-end',icon:'success',title:msg,showConfirmButton:false,timer:2000,timerProgressBar:true});
}
</script>
</body>
</html>
