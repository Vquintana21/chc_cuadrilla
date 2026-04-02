<?php
/**
 * =============================================================================
 * SISTEMA DE NOTIFICACIONES POR CORREO - MÓDULO CHC (Lado Profesor)
 * =============================================================================
 */

require_once(__DIR__ . "/phpmailer/PHPMailerAutoload.php");

/**
 * Envía correo de notificación cuando el profesor envía una solicitud CHC (Paso 3)
 * Se envía tanto al profesor como a los gestores CHC
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $idsolicitud ID de la solicitud recién creada
 * @return bool True si se envió correctamente
 */
function enviarCorreoSolicitudEnviada($conn, $idsolicitud) {
    
    // =========================================================================
    // ASEGURAR ENCODING UTF-8
    // =========================================================================
    mysqli_set_charset($conn, "utf8");
    
    // =========================================================================
    // 1. OBTENER DATOS DE LA SOLICITUD
    // =========================================================================
    $sqlSolicitud = "SELECT 
                        s.idsolicitud,
                        s.codigocurso,
                        s.seccion,
                        s.nombrecurso,
                        s.correopec,
                        s.nombrepec,
                        s.npacientes,
                        s.nestudiantesxsesion,
                        s.nboxes,
                        s.uso_fantoma,
                        s.fantoma_capacitado,
                        s.uso_debriefing,
                        s.comentarios,
                        s.espacio_requerido_otros,
                        m.modalidad
                     FROM chc_solicitud s
                     LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
                     LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
                     WHERE s.idsolicitud = ?";
    
    $stmt = mysqli_prepare($conn, $sqlSolicitud);
    mysqli_stmt_bind_param($stmt, "i", $idsolicitud);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $solicitud = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$solicitud) {
        error_log("CORREO SOLICITUD ENVIADA: No se encontro la solicitud - ID: $idsolicitud");
        return false;
    }
    
    // =========================================================================
    // 2. OBTENER ACTIVIDADES DE LA SOLICITUD
    // =========================================================================
    $sqlActividades = "SELECT 
                          p.pcl_tituloActividad,
                          DATE_FORMAT(p.pcl_Fecha, '%d/%m/%Y') as fecha,
                          DATE_FORMAT(p.pcl_Inicio, '%H:%i') as hora_inicio,
                          DATE_FORMAT(p.pcl_Termino, '%H:%i') as hora_termino,
                          p.pcl_TipoSesion,
                          p.pcl_SubTipoSesion
                       FROM chc_solicitud_actividad sa
                       INNER JOIN planclases p ON sa.idplanclases = p.idplanclases
                       WHERE sa.idsolicitud = ?
                       ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC";
    
    $stmtAct = mysqli_prepare($conn, $sqlActividades);
    mysqli_stmt_bind_param($stmtAct, "i", $idsolicitud);
    mysqli_stmt_execute($stmtAct);
    $resultAct = mysqli_stmt_get_result($stmtAct);
    
    $actividades = array();
    while ($row = mysqli_fetch_assoc($resultAct)) {
        $actividades[] = $row;
    }
    mysqli_stmt_close($stmtAct);
    
    // =========================================================================
    // 3. OBTENER ESPACIOS SELECCIONADOS (solo Presencial)
    // =========================================================================
    $espaciosTexto = '';
    $sqlEspacios = "SELECT e.espacio 
                    FROM chc_solicitud_espacio se
                    INNER JOIN chc_espacio e ON se.idespacio = e.idespacio
                    WHERE se.idsolicitud = ?";
    $stmtEsp = mysqli_prepare($conn, $sqlEspacios);
    mysqli_stmt_bind_param($stmtEsp, "i", $idsolicitud);
    mysqli_stmt_execute($stmtEsp);
    $resultEsp = mysqli_stmt_get_result($stmtEsp);
    
    $espacios = array();
    while ($row = mysqli_fetch_assoc($resultEsp)) {
        $espacios[] = $row['espacio'];
    }
    mysqli_stmt_close($stmtEsp);
    
    if (count($espacios) > 0) {
        $espaciosTexto = implode(', ', $espacios);
    }
    
    // =========================================================================
    // 4. OBTENER GESTORES CHC (admin = 1)
    // =========================================================================
    $sqlGestores = "SELECT correo, nombre FROM chc_usuario WHERE admin = 1 AND correo IS NOT NULL AND correo != ''";
    $resultGestores = mysqli_query($conn, $sqlGestores);
    
    $gestores = array();
    if ($resultGestores) {
        while ($row = mysqli_fetch_assoc($resultGestores)) {
            $gestores[] = $row;
        }
    }
    
    // =========================================================================
    // 5. CONFIGURACIÓN DEL CORREO
    // =========================================================================
    $tituloCorreo = 'Agenda CHC Enviada';
    $colorEstado = '#ffc107'; // Amarillo warning
    
    // =========================================================================
    // 6. CONSTRUIR CONTENIDO HTML DEL CORREO
    // =========================================================================
    $fechaActual = date('d/m/Y H:i');
    
    // Escapar datos para HTML
    $nombrePEC = htmlspecialchars($solicitud['nombrepec'], ENT_QUOTES, 'UTF-8');
    $codigoCurso = htmlspecialchars($solicitud['codigocurso'], ENT_QUOTES, 'UTF-8');
    $seccionCurso = htmlspecialchars($solicitud['seccion'], ENT_QUOTES, 'UTF-8');
    $nombreCurso = htmlspecialchars($solicitud['nombrecurso'], ENT_QUOTES, 'UTF-8');
    $modalidadCurso = htmlspecialchars($solicitud['modalidad'], ENT_QUOTES, 'UTF-8');
    
    $contenidoCorreo = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $tituloCorreo . '</title>
        <style>
            body { margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif; }
            .container { width: 100%; max-width: 700px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
            .header { background-color: #0d6efd; color: #ffffff; padding: 20px; text-align: center; }
            .header img { width: 150px; margin-bottom: 10px; }
            .header h1 { margin: 10px 0 0 0; font-size: 22px; font-weight: normal; }
            .estado-badge { display: inline-block; background-color: ' . $colorEstado . '; color: #333; padding: 12px 30px; border-radius: 5px; font-size: 16px; font-weight: bold; margin: 15px 0; text-transform: uppercase; letter-spacing: 1px; }
            .content { padding: 25px; color: #333333; }
            .content p { line-height: 1.6; margin: 10px 0; }
            .info-box { background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0; }
            .info-box h3 { margin: 0 0 10px 0; color: #0d6efd; font-size: 16px; }
            .info-box p { margin: 5px 0; }
            .recursos-box { background-color: #e7f3ff; border-left: 4px solid #17a2b8; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0; }
            .recursos-box h3 { margin: 0 0 10px 0; color: #17a2b8; font-size: 16px; }
            .comentario-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0; }
            .comentario-box h3 { margin: 0 0 10px 0; color: #856404; font-size: 16px; }
            .footer { background-color: #0d6efd; color: #ffffff; text-align: center; padding: 15px; font-size: 12px; }
            .footer p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
            th, td { border: 1px solid #dee2e6; padding: 10px 8px; text-align: left; }
            th { background-color: #0d6efd; color: white; font-weight: bold; }
            tr:nth-child(even) { background-color: #f8f9fa; }
            tr:nth-child(odd) { background-color: #ffffff; }
            .small { font-size: 12px; color: #6c757d; }
            h3.section-title { color: #333; font-size: 16px; margin: 20px 0 10px 0; padding-bottom: 5px; border-bottom: 2px solid #0d6efd; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://medicina.uchile.cl/.resources/portal-medicina/images/medicina-logo.png" alt="Logo Facultad de Medicina">
                <h1>' . $tituloCorreo . '</h1>
            </div>
            
            <div class="content">
                <p>Estimado/a <strong>' . $nombrePEC . '</strong>,</p>
                
                <p style="text-align: center;">
                    <span class="estado-badge">SOLICITUD ENVIADA</span>
                </p>
                
                <p>Su solicitud de agenda CHC ha sido <strong>enviada correctamente</strong> y esta pendiente de revision por el equipo de gestion.</p>
                
                <div class="info-box">
                    <h3>Informacion del Curso</h3>
                    <p><strong>Codigo:</strong> ' . $codigoCurso . '</p>
                    <p><strong>Seccion:</strong> ' . $seccionCurso . '</p>
                    <p><strong>Nombre:</strong> ' . $nombreCurso . '</p>
                    <p><strong>Modalidad:</strong> ' . $modalidadCurso . '</p>
                    <p><strong>N° Solicitud:</strong> #' . $idsolicitud . '</p>
                </div>
                
                <div class="recursos-box">
                    <h3>Recursos Solicitados</h3>
                    <p><strong>N° Pacientes:</strong> ' . $solicitud['npacientes'] . '</p>
                    <p><strong>N° Estudiantes por sesion:</strong> ' . $solicitud['nestudiantesxsesion'] . '</p>';
    
    // Solo mostrar campos de Presencial si aplica
    if ($solicitud['nboxes'] > 0) {
        $contenidoCorreo .= '
                    <p><strong>N° Boxes:</strong> ' . $solicitud['nboxes'] . '</p>';
    }
    
    if ($solicitud['uso_fantoma'] == 1) {
        $contenidoCorreo .= '
                    <p><strong>Uso Fantoma:</strong> Si</p>
                    <p><strong>Capacitado:</strong> ' . ($solicitud['fantoma_capacitado'] == 1 ? 'Si' : 'No, requiere capacitacion') . '</p>';
    }
    
    if ($solicitud['uso_debriefing'] == 1) {
        $contenidoCorreo .= '
                    <p><strong>Uso Debriefing:</strong> Si</p>';
    }
    
    if (!empty($espaciosTexto)) {
        $contenidoCorreo .= '
                    <p><strong>Espacios:</strong> ' . htmlspecialchars($espaciosTexto, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    
    if (!empty($solicitud['espacio_requerido_otros'])) {
        $contenidoCorreo .= '
                    <p><strong>Espacio/Ubicacion:</strong> ' . htmlspecialchars($solicitud['espacio_requerido_otros'], ENT_QUOTES, 'UTF-8') . '</p>';
    }
    
    $contenidoCorreo .= '
                </div>';
    
    // Agregar comentarios si existen
    if (!empty($solicitud['comentarios'])) {
        $comentarioEscapado = nl2br(htmlspecialchars($solicitud['comentarios'], ENT_QUOTES, 'UTF-8'));
        $contenidoCorreo .= '
                <div class="comentario-box">
                    <h3>Comentarios del Solicitante</h3>
                    <p>' . $comentarioEscapado . '</p>
                </div>';
    }
    
    // Agregar tabla de actividades
    if (count($actividades) > 0) {
        $contenidoCorreo .= '
                <h3 class="section-title">Actividades Solicitadas (' . count($actividades) . ')</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Actividad</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($actividades as $act) {
            $tipoSesion = htmlspecialchars($act['pcl_TipoSesion'], ENT_QUOTES, 'UTF-8');
            $subTipoSesion = htmlspecialchars($act['pcl_SubTipoSesion'], ENT_QUOTES, 'UTF-8');
            
            if (!empty($subTipoSesion) && $subTipoSesion != $tipoSesion) {
                $tipoMostrar = $tipoSesion . ' - ' . $subTipoSesion;
            } else {
                $tipoMostrar = $tipoSesion;
            }
            
            $tituloActividad = htmlspecialchars($act['pcl_tituloActividad'], ENT_QUOTES, 'UTF-8');
            
            $contenidoCorreo .= '
                        <tr>
                            <td>' . $tituloActividad . '</td>
                            <td>' . $act['fecha'] . '</td>
                            <td>' . $act['hora_inicio'] . ' - ' . $act['hora_termino'] . '</td>
                            <td>' . $tipoMostrar . '</td>
                        </tr>';
        }
        
        $contenidoCorreo .= '
                    </tbody>
                </table>';
    }
    
    $contenidoCorreo .= '
                <div class="comentario-box">
                    <h3>Proximos Pasos</h3>
                    <p>Su solicitud sera revisada por el equipo de gestion del CHC. Recibira una notificacion cuando sea <strong>confirmada</strong> o si se requiere alguna modificacion.</p>
                </div>
                
                <p class="small">
                    <br>
                    Fecha de envio: ' . $fechaActual . '
                </p>
                
                <p class="small" style="margin-top: 20px;">
                    <em>Este es un mensaje automatico del Sistema de Gestion CHC. Por favor no responda directamente a este correo.</em>
                </p>
            </div>
            
            <div class="footer">
                <p>Centro de Habilidades Clinicas - Facultad de Medicina - Universidad de Chile</p>
                <p>&copy; ' . date('Y') . ' - Sistema de Gestion de Agenda CHC</p>
            </div>
        </div>
    </body>
    </html>';
    
    // =========================================================================
// 7. ENVIAR CORREO AL PROFESOR (PEC)
// =========================================================================
$enviadoPEC = false;
$enviadoGestores = false;

try {
    $mail = new PHPMailer;
    $mail->CharSet = "UTF-8";
    $mail->Encoding = "base64";
    $mail->IsSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = "ssl";
    $mail->Host = "mail.dpi.med.uchile.cl";
    $mail->Port = 465;
    $mail->Username = "_mainaccount@dpi.med.uchile.cl";
    $mail->Password = "gD5T4)N1FDj1";
    $mail->From = "_mainaccount@dpi.med.uchile.cl";
    $mail->FromName = "Gestion Agenda CHC";
    
    // Asunto para PEC
    $mail->Subject = $tituloCorreo . " - " . $solicitud['codigocurso'] . " Sec. " . $solicitud['seccion'];
    
    // Destinatario: el PEC (profesor)
    $mail->addAddress($solicitud['correopec']);
    //$mail->addAddress('antonio.arias@uchile.cl'); // Temporal para pruebas
    
    // Contenido para PEC (sin modificar)
    $mail->MsgHTML($contenidoCorreo);
    
    if ($mail->Send()) {
        $enviadoPEC = true;
        error_log("CORREO SOLICITUD ENVIADA (PEC): Solicitud $idsolicitud - Destinatario: " . $solicitud['correopec']);
    } else {
        error_log("CORREO SOLICITUD ENVIADA ERROR (PEC): " . $mail->ErrorInfo);
    }
    
    $mail->ClearAllRecipients();
    
} catch (Exception $e) {
    error_log("CORREO SOLICITUD ENVIADA EXCEPCION (PEC): " . $e->getMessage());
}

// =========================================================================
// 8. ENVIAR CORREO A GESTORES CHC
// =========================================================================
if (count($gestores) > 0) {
    
    // --- Construir contenido modificado para GESTOR ---
    $infoPEC = '
                <div class="info-box" style="background-color: #e8f4fd; border-left-color: #17a2b8;">
                    <h3 style="color: #17a2b8;">Informacion del Solicitante (PEC)</h3>
                    <p><strong>Nombre:</strong> ' . $nombrePEC . '</p>
                    <p><strong>Correo:</strong> ' . htmlspecialchars($solicitud['correopec'], ENT_QUOTES, 'UTF-8') . '</p>
                </div>';
    
    // Reemplazar saludo y agregar info del PEC
    $contenidoCorreoGestor = str_replace(
        '<p>Estimado/a <strong>' . $nombrePEC . '</strong>,</p>',
        '<p>Estimado/a <strong>Gestor CHC</strong>,</p>' . $infoPEC,
        $contenidoCorreo
    );
    
    // Cambiar el mensaje inicial
    $contenidoCorreoGestor = str_replace(
        'Su solicitud de agenda CHC ha sido <strong>enviada correctamente</strong> y esta pendiente de revision por el equipo de gestion.',
        'Se ha recibido una <strong>nueva solicitud</strong> de agenda CHC que requiere revision.',
        $contenidoCorreoGestor
    );
    
    // Quitar la sección de "Próximos Pasos" (no aplica para gestores)
    $contenidoCorreoGestor = str_replace(
        '<div class="comentario-box">
                    <h3>Proximos Pasos</h3>
                    <p>Su solicitud sera revisada por el equipo de gestion del CHC. Recibira una notificacion cuando sea <strong>confirmada</strong> o si se requiere alguna modificacion.</p>
                </div>',
        '',
        $contenidoCorreoGestor
    );
    
    try {
        $mail2 = new PHPMailer;
        $mail2->CharSet = "UTF-8";
        $mail2->Encoding = "base64";
        $mail2->IsSMTP();
        $mail2->SMTPAuth = true;
        $mail2->SMTPSecure = "ssl";
        $mail2->Host = "mail.dpi.med.uchile.cl";
        $mail2->Port = 465;
        $mail2->Username = "_mainaccount@dpi.med.uchile.cl";
        $mail2->Password = "gD5T4)N1FDj1";
        $mail2->From = "_mainaccount@dpi.med.uchile.cl";
        $mail2->FromName = "Gestion Agenda CHC";
        
        // Asunto diferente para gestores
        $mail2->Subject = "Nueva Solicitud CHC - " . $solicitud['codigocurso'] . " Sec. " . $solicitud['seccion'] . " - #" . $idsolicitud;
        
        // Agregar todos los gestores como destinatarios
         foreach ($gestores as $gestor) {
             $mail2->addAddress($gestor['correo']);
         }
        //$mail2->addAddress('antonio.arias@uchile.cl'); // Temporal para pruebas
        
        // Contenido modificado para gestores
        $mail2->MsgHTML($contenidoCorreoGestor);
        
        if ($mail2->Send()) {
            $enviadoGestores = true;
            $destinatarios = implode(', ', array_column($gestores, 'correo'));
            error_log("CORREO SOLICITUD ENVIADA (GESTORES): Solicitud $idsolicitud - Destinatarios: $destinatarios");
        } else {
            error_log("CORREO SOLICITUD ENVIADA ERROR (GESTORES): " . $mail2->ErrorInfo);
        }
        
        $mail2->ClearAllRecipients();
        
    } catch (Exception $e) {
        error_log("CORREO SOLICITUD ENVIADA EXCEPCION (GESTORES): " . $e->getMessage());
    }
}

return ($enviadoPEC || $enviadoGestores);
}