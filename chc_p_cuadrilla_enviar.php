<?php
session_start();
require_once('conexion.php');
header('Content-Type: application/json; charset=utf-8');

if(empty($_SESSION['sesion_idLogin'])) {
    echo json_encode(array('success'=>false,'message'=>'Sesión no válida')); exit;
}
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success'=>false,'message'=>'Método no permitido')); exit;
}

function post($key, $default) {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? $_POST[$key] : $default;
}

$rutPEC      = str_pad($_SESSION['sesion_idLogin'], 10, "0", STR_PAD_LEFT);
$accion      = trim(post('accion', ''));
$idcuadrilla = intval(post('idcuadrilla', 0));

if($accion !== 'enviar_cuadrilla' || $idcuadrilla <= 0) {
    echo json_encode(array('success'=>false,'message'=>'Datos inválidos')); exit;
}

// ============================================================
// CARGAR Y VERIFICAR CUADRILLA
// ============================================================
$rutPECesc = mysqli_real_escape_string($conn, $rutPEC);
$sqlCuad = "
    SELECT cq.idcuadrilla, cq.idsolicitud, cq.estado, cq.idsubtipo,
           cq.resumen_actividad,
           sol.codigocurso, sol.seccion, sol.nombrecurso,
           sol.correopec, sol.nombrepec,
           m.modalidad, cs.nombre AS nombre_subtipo
    FROM chc_p_cuadrilla cq
    INNER JOIN chc_solicitud sol            ON cq.idsolicitud = sol.idsolicitud
    LEFT  JOIN chc_solicitud_modalidad sm   ON sol.idsolicitud = sm.idsolicitud
    LEFT  JOIN chc_modalidad m              ON sm.idmodalidad  = m.idmodalidad
    INNER JOIN chc_p_cuadrilla_subtipo cs   ON cq.idsubtipo   = cs.idsubtipo
    WHERE cq.idcuadrilla = $idcuadrilla AND cq.rut_pec = '$rutPECesc'
    LIMIT 1";
$cuadrilla = mysqli_fetch_assoc(mysqli_query($conn, $sqlCuad));

if(!$cuadrilla) {
    echo json_encode(array('success'=>false,'message'=>'Cuadrilla no encontrada o sin acceso')); exit;
}
if($cuadrilla['estado'] == 3) {
    echo json_encode(array('success'=>false,'message'=>'Esta cuadrilla ya fue enviada')); exit;
}

// ============================================================
// VALIDACIÓN: al menos una fecha con horas completas
// ============================================================
$sqlVer = "SELECT COUNT(*) AS total,
                  SUM(CASE WHEN hora_inicio IS NOT NULL AND hora_termino IS NOT NULL THEN 1 ELSE 0 END) AS completas
           FROM chc_p_cuadrilla_fecha WHERE idcuadrilla = $idcuadrilla";
$ver = mysqli_fetch_assoc(mysqli_query($conn, $sqlVer));

if($ver['total'] == 0) {
    echo json_encode(array('success'=>false,'message'=>'No hay fechas en la sección 2. Complete la cuadrilla antes de enviar.')); exit;
}
if($ver['completas'] < $ver['total']) {
    echo json_encode(array('success'=>false,'message'=>'Hay fechas sin hora de inicio o término. Revise la sección 2.')); exit;
}

// ============================================================
// CAMBIAR ESTADO
// ============================================================
$idsolicitud = intval($cuadrilla['idsolicitud']);
mysqli_begin_transaction($conn);
try {
    $sqlUpC = "UPDATE chc_p_cuadrilla
               SET estado = 3, fecha_envio = NOW(), fecha_modificacion = NOW()
               WHERE idcuadrilla = $idcuadrilla";
    if(!mysqli_query($conn, $sqlUpC)) throw new Exception('Error al actualizar cuadrilla');

    $sqlUpS = "UPDATE chc_solicitud SET idestadocuadrilla = 3 WHERE idsolicitud = $idsolicitud";
    if(!mysqli_query($conn, $sqlUpS)) throw new Exception('Error al actualizar solicitud');

    mysqli_commit($conn);
} catch(Exception $e) {
    mysqli_rollback($conn);
    error_log('CHC Enviar Cuadrilla: ' . $e->getMessage());
    echo json_encode(array('success'=>false,'message'=>'Error al registrar el envío: '.$e->getMessage())); exit;
}

// ============================================================
// GENERAR PDF
// ============================================================
define('CHC_PDF_MODO_FUNCION', true);
require_once('chc_p_cuadrilla_pdf.php');

$rutaPDF    = false;
$nombrePDF  = 'Cuadrilla_CHC_' . $idcuadrilla . '_' .
              $cuadrilla['codigocurso'] . '_Sec' . $cuadrilla['seccion'] . '.pdf';

try {
    $rutaPDF = generarPDFCuadrilla($conn, $idcuadrilla, true); // true = guardar en disco
    if(!$rutaPDF) throw new Exception('generarPDFCuadrilla devolvió false');
    error_log('CHC PDF generado: ' . $rutaPDF);
} catch(Exception $e) {
    error_log('CHC PDF Error: ' . $e->getMessage());
    // No bloqueamos el envío si falla el PDF, solo se notifica sin adjunto
}

// ============================================================
// OBTENER ADMINS CHC (admin = 2)
// ============================================================
$sqlAdmins = "SELECT correo, nombre FROM chc_usuario WHERE admin = 2 AND correo IS NOT NULL AND correo != ''";
$resAdmins = mysqli_query($conn, $sqlAdmins);
$admins    = array();
while($row = mysqli_fetch_assoc($resAdmins)) $admins[] = $row;

if(empty($admins)) {
    error_log('CHC Envío: No hay admins con admin=2 configurados');
    echo json_encode(array('success'=>true,'message'=>'Cuadrilla enviada. Sin destinatarios de correo configurados.'));
    exit;
}

// ============================================================
// CONTENIDO DEL CORREO
// ============================================================
// Obtener fechas para el correo
$sqlFechas = "SELECT DATE_FORMAT(p.pcl_Fecha,'%d/%m/%Y') AS fecha,
                     DATE_FORMAT(cf.hora_inicio,'%H:%i')  AS hi,
                     DATE_FORMAT(cf.hora_termino,'%H:%i') AS ht
              FROM chc_p_cuadrilla_fecha cf
              INNER JOIN planclases_test p ON cf.idplanclases = p.idplanclases
              WHERE cf.idcuadrilla = $idcuadrilla
              ORDER BY p.pcl_Fecha ASC";
$resFechas = mysqli_query($conn, $sqlFechas);
$filasHtml = '';
while($row = mysqli_fetch_assoc($resFechas)) {
    $filasHtml .= "<tr>
        <td style='padding:5px 10px;border:1px solid #dee2e6;'>{$row['fecha']}</td>
        <td style='padding:5px 10px;border:1px solid #dee2e6;'>{$row['hi']} — {$row['ht']} hrs.</td>
    </tr>";
}

$tablaFechas = $filasHtml ? "
    <p style='font-weight:bold;margin-top:15px;'>Fechas programadas:</p>
    <table style='border-collapse:collapse;width:100%;'>
        <thead><tr style='background:#e8f4f8;'>
            <th style='padding:7px 10px;border:1px solid #dee2e6;text-align:left;'>Fecha</th>
            <th style='padding:7px 10px;border:1px solid #dee2e6;text-align:left;'>Horario</th>
        </tr></thead>
        <tbody>$filasHtml</tbody>
    </table>" : '';

$contenidoHTML = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#333;margin:0;padding:0;background:#f4f6f9;">
<div style="max-width:620px;margin:20px auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
    <div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:25px 30px;color:white;">
        <h2 style="margin:0 0 5px;">Nueva Cuadrilla CHC Recibida</h2>
        <p style="margin:0;opacity:.85;">Sistema de Gestión de Agenda CHC</p>
    </div>
    <div style="padding:25px 30px;">
        <p>Se ha <strong>enviado una nueva cuadrilla</strong> y está disponible para revisión y preparación de espacios:</p>
        <table style="width:100%;border-collapse:collapse;margin:15px 0;">
            <tr><td style="padding:7px;background:#f8f9fa;font-weight:bold;width:40%;border:1px solid #dee2e6;">Curso</td>
                <td style="padding:7px;border:1px solid #dee2e6;">' . $cuadrilla['codigocurso'].'-'.$cuadrilla['seccion'] . '</td></tr>
            <tr><td style="padding:7px;background:#f8f9fa;font-weight:bold;border:1px solid #dee2e6;">Nombre</td>
                <td style="padding:7px;border:1px solid #dee2e6;">' . htmlspecialchars($cuadrilla['nombrecurso']) . '</td></tr>
            <tr><td style="padding:7px;background:#f8f9fa;font-weight:bold;border:1px solid #dee2e6;">PEC</td>
                <td style="padding:7px;border:1px solid #dee2e6;">' . htmlspecialchars($cuadrilla['nombrepec']) . ' — ' . $cuadrilla['correopec'] . '</td></tr>
            <tr><td style="padding:7px;background:#f8f9fa;font-weight:bold;border:1px solid #dee2e6;">Modalidad</td>
                <td style="padding:7px;border:1px solid #dee2e6;">' . $cuadrilla['modalidad'] . '</td></tr>
            <tr><td style="padding:7px;background:#f8f9fa;font-weight:bold;border:1px solid #dee2e6;">Subtipo</td>
                <td style="padding:7px;border:1px solid #dee2e6;">' . htmlspecialchars($cuadrilla['nombre_subtipo']) . '</td></tr>
            <tr><td style="padding:7px;background:#f8f9fa;font-weight:bold;border:1px solid #dee2e6;">Cuadrilla #</td>
                <td style="padding:7px;border:1px solid #dee2e6;">' . $idcuadrilla . '</td></tr>
            <tr><td style="padding:7px;background:#f8f9fa;font-weight:bold;border:1px solid #dee2e6;">Fecha de envío</td>
                <td style="padding:7px;border:1px solid #dee2e6;">' . date('d/m/Y H:i') . '</td></tr>
        </table>
        ' . $tablaFechas . '
        <div style="background:#d1e7dd;border-radius:6px;padding:10px 15px;margin-top:15px;">
            <strong>Resumen:</strong> ' .
            nl2br(htmlspecialchars(mb_substr($cuadrilla['resumen_actividad'],0,400,'UTF-8'))) .
            (mb_strlen($cuadrilla['resumen_actividad'],'UTF-8')>400?'...':'') . '
        </div>
        ' . ($rutaPDF ? '<p style="margin-top:15px;color:#666;font-size:.9em;"><i>Se adjunta el PDF de la cuadrilla completa.</i></p>' : '') . '
    </div>
    <div style="background:#f8f9fa;padding:12px 30px;text-align:center;color:#6c757d;font-size:.82rem;">
        Centro de Habilidades Clínicas — Facultad de Medicina — Universidad de Chile<br>
        © ' . date('Y') . ' Sistema de Gestión de Agenda CHC
    </div>
</div></body></html>';

// ============================================================
// ENVIAR CORREO CON PHPMAILER
// ============================================================
$correoOk = false;
$phpmailerPath = __DIR__ . '/phpmailer/PHPMailerAutoload.php';

if(file_exists($phpmailerPath)) {
    require_once($phpmailerPath);
    try {
        $mail = new PHPMailer();
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->IsSMTP();
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = 'ssl';
        $mail->Host       = 'mail.dpi.med.uchile.cl';
        $mail->Port       = 465;
        $mail->Username   = '_mainaccount@dpi.med.uchile.cl';
        $mail->Password   = 'gD5T4)N1FDj1';
        $mail->From       = '_mainaccount@dpi.med.uchile.cl';
        $mail->FromName   = 'Gestión Agenda CHC';
        $mail->Subject    = 'Nueva Cuadrilla #'.$idcuadrilla.' — '.$cuadrilla['codigocurso'].' Sec.'.$cuadrilla['seccion'];

        foreach($admins as $admin) {
            $mail->addAddress($admin['correo'], $admin['nombre']);
        }

        // Adjuntar PDF si fue generado
        if($rutaPDF && file_exists($rutaPDF)) {
            $mail->addAttachment($rutaPDF, $nombrePDF);
        }

        $mail->MsgHTML($contenidoHTML);

        if($mail->Send()) {
            $correoOk = true;
            error_log('CHC Cuadrilla #'.$idcuadrilla.' enviada — correo OK a '.count($admins).' admin(s)');
        } else {
            error_log('CHC Correo error: '.$mail->ErrorInfo);
        }

        $mail->ClearAllRecipients();
        $mail->ClearAttachments();

    } catch(Exception $e) {
        error_log('CHC Correo excepción: '.$e->getMessage());
    }
} else {
    // Fallback: mail() nativo
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Gestion Agenda CHC <_mainaccount@dpi.med.uchile.cl>\r\n";
    $asunto   = 'Nueva Cuadrilla #'.$idcuadrilla.' — '.$cuadrilla['codigocurso'].' Sec.'.$cuadrilla['seccion'];
    foreach($admins as $admin) {
        mail($admin['correo'], $asunto, $contenidoHTML, $headers);
    }
    $correoOk = true;
    error_log('CHC Cuadrilla #'.$idcuadrilla.' — correo vía mail() nativo (sin PDF adjunto)');
}

echo json_encode(array(
    'success'       => true,
    'message'       => 'Cuadrilla enviada exitosamente',
    'idcuadrilla'   => $idcuadrilla,
    'correo_ok'     => $correoOk,
    'pdf_generado'  => ($rutaPDF !== false)
));
?>
