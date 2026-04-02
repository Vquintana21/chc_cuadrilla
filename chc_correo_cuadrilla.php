<?php

/**
 * =============================================================================
 * SISTEMA DE NOTIFICACIONES POR CORREO - CUADRILLAS CHC
 * =============================================================================
 */

//require_once(__DIR__ . "/phpmailer/PHPMailerAutoload.php");

/**
 * Envía correo de notificación cuando se sube una cuadrilla PDF
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $idsolicitud ID de la solicitud
 * @param string $rutaPDF Ruta completa al archivo PDF
 * @param string $nombreArchivo Nombre original del archivo
 * @param string $comentario Comentario opcional de la cuadrilla
 * @param string $rutUsuario RUT del usuario que sube
 * @return bool True si se envió correctamente, False en caso contrario
 */
function enviarCorreoCuadrillaCHC($conn, $idsolicitud, $rutaPDF, $nombreArchivo, $comentario = '', $rutUsuario = '') {
	
     // =========================================================================
    // ASEGURAR ENCODING UTF-8
    // =========================================================================
    mysqli_set_charset($conn, "utf8");
    
    // =========================================================================
    // DESTINATARIO FIJO (temporal) solo para log
    // =========================================================================
    //$destinatarioFijo = 'antonio.arias@uchile.cl';
	
	$sqlAdmins = "SELECT correo, nombre FROM chc_usuario WHERE admin = 2 AND correo IS NOT NULL AND correo != ''";
	$resultAdmins = mysqli_query($conn, $sqlAdmins);
	if (!$resultAdmins || mysqli_num_rows($resultAdmins) == 0) {
	error_log("CORREO ADMIN CHC: No hay administradores configurados con admin=2");
	return false;
	}
	$admins = array();
	while ($row = mysqli_fetch_assoc($resultAdmins)) {
	$admins[] = $row;
	}
	
   
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
        error_log("CORREO CUADRILLA: No se encontro la solicitud - ID: $idsolicitud");
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
    // 3. CONFIGURACIÓN DEL CORREO
    // =========================================================================
    $tituloCorreo = 'Nueva Cuadrilla Subida';
    $colorEstado = '#17a2b8'; // Azul info
    $mensajeEstado = 'Se ha subido una nueva <strong>CUADRILLA</strong> para la siguiente solicitud CHC.';
    
    // =========================================================================
    // 4. CONSTRUIR CONTENIDO HTML DEL CORREO
    // =========================================================================
    $fechaActual = date('d/m/Y H:i');
    
    // Escapar datos para HTML
    $nombrePEC = htmlspecialchars($solicitud['nombrepec'], ENT_QUOTES, 'UTF-8');
    $codigoCurso = htmlspecialchars($solicitud['codigocurso'], ENT_QUOTES, 'UTF-8');
    $seccionCurso = htmlspecialchars($solicitud['seccion'], ENT_QUOTES, 'UTF-8');
    $nombreCurso = htmlspecialchars($solicitud['nombrecurso'], ENT_QUOTES, 'UTF-8');
    $modalidadCurso = htmlspecialchars($solicitud['modalidad'], ENT_QUOTES, 'UTF-8');
    $nombreArchivoEsc = htmlspecialchars($nombreArchivo, ENT_QUOTES, 'UTF-8');
    $rutUsuarioEsc = htmlspecialchars($rutUsuario, ENT_QUOTES, 'UTF-8');
    
    $contenidoCorreo = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $tituloCorreo . '</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
                font-family: Arial, sans-serif;
            }
            .container {
                width: 100%;
                max-width: 700px;
                margin: 0 auto;
                background-color: #ffffff;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
            .header {
                background-color: #0d6efd;
                color: #ffffff;
                padding: 20px;
                text-align: center;
            }
            .header img {
                width: 150px;
                margin-bottom: 10px;
            }
            .header h1 {
                margin: 10px 0 0 0;
                font-size: 22px;
                font-weight: normal;
            }
            .estado-badge {
                display: inline-block;
                background-color: ' . $colorEstado . ';
                color: white;
                padding: 12px 30px;
                border-radius: 5px;
                font-size: 16px;
                font-weight: bold;
                margin: 15px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .content {
                padding: 25px;
                color: #333333;
            }
            .content p {
                line-height: 1.6;
                margin: 10px 0;
            }
            .info-box {
                background-color: #f8f9fa;
                border-left: 4px solid #0d6efd;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 8px 8px 0;
            }
            .info-box h3 {
                margin: 0 0 10px 0;
                color: #0d6efd;
                font-size: 16px;
            }
            .info-box p {
                margin: 5px 0;
            }
            .cuadrilla-box {
                background-color: #d1ecf1;
                border-left: 4px solid #17a2b8;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 8px 8px 0;
            }
            .cuadrilla-box h3 {
                margin: 0 0 10px 0;
                color: #0c5460;
                font-size: 16px;
            }
            .comentario-box {
                background-color: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 8px 8px 0;
            }
            .comentario-box h3 {
                margin: 0 0 10px 0;
                color: #856404;
                font-size: 16px;
            }
            .footer {
                background-color: #0d6efd;
                color: #ffffff;
                text-align: center;
                padding: 15px;
                font-size: 12px;
            }
            .footer p {
                margin: 5px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
                font-size: 13px;
            }
            th, td {
                border: 1px solid #dee2e6;
                padding: 10px 8px;
                text-align: left;
            }
            th {
                background-color: #0d6efd;
                color: white;
                font-weight: bold;
            }
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            tr:nth-child(odd) {
                background-color: #ffffff;
            }
            .small {
                font-size: 12px;
                color: #6c757d;
            }
            h3.section-title {
                color: #333;
                font-size: 16px;
                margin: 20px 0 10px 0;
                padding-bottom: 5px;
                border-bottom: 2px solid #0d6efd;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://medicina.uchile.cl/.resources/portal-medicina/images/medicina-logo.png" alt="Logo Facultad de Medicina">
                <h1>' . $tituloCorreo . '</h1>
            </div>
            
            <div class="content">
                <p>Estimado/a Gestor CHC,</p>
                
                <p style="text-align: center;">
                    <span class="estado-badge">CUADRILLA RECIBIDA</span>
                </p>
                
                <p>' . $mensajeEstado . '</p>
                
                <div class="info-box">
                    <h3>Informacion del Curso</h3>
                    <p><strong>Codigo:</strong> ' . $codigoCurso . '</p>
                    <p><strong>Seccion:</strong> ' . $seccionCurso . '</p>
                    <p><strong>Nombre:</strong> ' . $nombreCurso . '</p>
                    <p><strong>Modalidad:</strong> ' . $modalidadCurso . '</p>
                    <p><strong>N° Solicitud:</strong> #' . $idsolicitud . '</p>
                    <p><strong>PEC:</strong> ' . $nombrePEC . '</p>
                </div>
                
                <div class="cuadrilla-box">
                    <h3>Documento Adjunto</h3>
                    <p><strong>Archivo:</strong> ' . $nombreArchivoEsc . '</p>
                    <p><strong>Subido por:</strong> ' . $rutUsuarioEsc . '</p>
                    <p><strong>Fecha:</strong> ' . $fechaActual . '</p>
                    <p><em>El documento PDF se encuentra adjunto a este correo.</em></p>
                </div>';
    
    // Agregar comentario si existe
    if (!empty($comentario)) {
        $comentarioEscapado = nl2br(htmlspecialchars($comentario, ENT_QUOTES, 'UTF-8'));
        $contenidoCorreo .= '
                <div class="comentario-box">
                    <h3>Comentario de Docente</h3>
                    <p>' . $comentarioEscapado . '</p>
                </div>';
    }
    
    // Agregar tabla de actividades
    if (count($actividades) > 0) {
        $contenidoCorreo .= '
                <h3 class="section-title">Actividades de la Solicitud (' . count($actividades) . ')</h3>
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
    // 5. CONFIGURAR Y ENVIAR CORREO CON PHPMAILER
    // =========================================================================
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
        
        // Asunto
        $mail->Subject = "Cuadrilla Recibida - " . $solicitud['codigocurso'] . " Sec. " . $solicitud['seccion'];
        
        // Destinatario fijo
        //$mail->addAddress($destinatarioFijo);
		
		foreach ($admins as $admin) {
		$mail->addAddress($admin['correo']);
		}    
        
        // Adjuntar el PDF
        if (file_exists($rutaPDF)) {
            $nombreArchivoFormateado = 'Cuadrilla_' . $idsolicitud . '_' . strtolower($solicitud['codigocurso']) . '_' . $solicitud['seccion'] . '.pdf';
			$mail->addAttachment($rutaPDF, $nombreArchivoFormateado);
        } else {
            error_log("CORREO CUADRILLA: Archivo PDF no encontrado - Ruta: $rutaPDF");
        }
        
        $mail->MsgHTML($contenidoCorreo);
        
        if (!$mail->Send()) {
            error_log("CORREO CUADRILLA ERROR: " . $mail->ErrorInfo . " - Solicitud: $idsolicitud");
            return false;
        }
        
        $mail->ClearAllRecipients();
        $mail->ClearAttachments();
        
        error_log("CORREO CUADRILLA ENVIADO: Solicitud $idsolicitud - Archivo: $nombreArchivo - Destinatario: $destinatarioFijo");
        return true;
        
    } catch (Exception $e) {
        error_log("CORREO CUADRILLA EXCEPCION: " . $e->getMessage() . " - Solicitud: $idsolicitud");
        return false;
    }
}

/**
 * Envía correo de notificación al PEC cuando se sube una cuadrilla PDF
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $idsolicitud ID de la solicitud
 * @param string $rutaPDF Ruta completa al archivo PDF
 * @param string $nombreArchivo Nombre original del archivo
 * @param string $comentario Comentario opcional de la cuadrilla
 * @param string $nombreGestor Nombre del gestor que sube la cuadrilla
 * @return bool True si se envió correctamente, False en caso contrario
 */
function enviarCorreoCuadrillaPEC($conn, $idsolicitud, $rutaPDF, $nombreArchivo, $comentario = '', $nombreGestor = '') {
    
	//$destinatarioFijo = 'antonio.arias@uchile.cl';
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
        error_log("CORREO CUADRILLA PEC: No se encontró la solicitud - ID: $idsolicitud");
        return false;
    }
    
    // Validar que existe correo del PEC
    if (empty($solicitud['correopec'])) {
        error_log("CORREO CUADRILLA PEC: No hay correo del PEC para solicitud $idsolicitud");
        return false;
    }
    
    // =========================================================================
    // 2. OBTENER ACTIVIDADES DE LA SOLICITUD
    // =========================================================================
    $sqlActividades = "SELECT 
                          p.pcl_tituloActividad,
                          DATE_FORMAT(p.pcl_Fecha, '%d/%m/%Y') as fecha,
                          p.dia,
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
    // 3. CONFIGURACIÓN DEL CORREO PARA PEC
    // =========================================================================
    $colorPrimario = '#28a745'; // Verde para diferenciarlo
    $colorSecundario = '#20c997';
    
    // =========================================================================
    // 4. CONSTRUIR CONTENIDO HTML DEL CORREO
    // =========================================================================
    $fechaActual = date('d/m/Y H:i');
    
    // Escapar datos para HTML
    $nombrePEC = htmlspecialchars($solicitud['nombrepec'], ENT_QUOTES, 'UTF-8');
    $codigoCurso = htmlspecialchars($solicitud['codigocurso'], ENT_QUOTES, 'UTF-8');
    $seccionCurso = htmlspecialchars($solicitud['seccion'], ENT_QUOTES, 'UTF-8');
    $nombreCurso = htmlspecialchars($solicitud['nombrecurso'], ENT_QUOTES, 'UTF-8');
    $modalidadCurso = htmlspecialchars($solicitud['modalidad'], ENT_QUOTES, 'UTF-8');
    $nombreArchivoEsc = htmlspecialchars($nombreArchivo, ENT_QUOTES, 'UTF-8');
    $nombreGestorEsc = htmlspecialchars($nombreGestor, ENT_QUOTES, 'UTF-8');
    
    // Obtener primera y última fecha de actividades
    $primeraFecha = count($actividades) > 0 ? $actividades[0]['fecha'] : 'N/A';
    $ultimaFecha = count($actividades) > 0 ? $actividades[count($actividades) - 1]['fecha'] : 'N/A';
    
    $contenidoCorreo = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cuadrilla Recepcionada - CHC</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
                font-family: Arial, sans-serif;
            }
            .container {
                width: 100%;
                max-width: 700px;
                margin: 0 auto;
                background-color: #ffffff;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
            .header {
                background: linear-gradient(135deg, ' . $colorPrimario . ' 0%, ' . $colorSecundario . ' 100%);
                color: #ffffff;
                padding: 25px;
                text-align: center;
            }
            .header img {
                width: 150px;
                margin-bottom: 10px;
            }
            .header h1 {
                margin: 10px 0 5px 0;
                font-size: 24px;
                font-weight: bold;
            }
            .header p {
                margin: 0;
                font-size: 14px;
                opacity: 0.9;
            }
            .estado-badge {
                display: inline-block;
                background-color: #ffffff;
                color: ' . $colorPrimario . ';
                padding: 15px 40px;
                border-radius: 50px;
                font-size: 18px;
                font-weight: bold;
                margin: 20px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            }
            .content {
                padding: 30px;
                color: #333333;
            }
            .content p {
                line-height: 1.7;
                margin: 12px 0;
                font-size: 15px;
            }
            .saludo {
                font-size: 16px;
                color: #333;
            }
            .curso-destacado {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-left: 5px solid ' . $colorPrimario . ';
                padding: 20px;
                margin: 25px 0;
                border-radius: 0 12px 12px 0;
            }
            .curso-destacado h2 {
                margin: 0 0 5px 0;
                color: ' . $colorPrimario . ';
                font-size: 20px;
            }
            .curso-destacado .curso-nombre {
                margin: 0;
                color: #666;
                font-size: 14px;
            }
            .info-grid {
                display: table;
                width: 100%;
                margin: 20px 0;
            }
            .info-item {
                display: table-cell;
                width: 33.33%;
                text-align: center;
                padding: 15px;
                background-color: #f8f9fa;
                border-right: 1px solid #dee2e6;
            }
            .info-item:last-child {
                border-right: none;
            }
            .info-item .numero {
                font-size: 28px;
                font-weight: bold;
                color: ' . $colorPrimario . ';
                display: block;
            }
            .info-item .etiqueta {
                font-size: 12px;
                color: #6c757d;
                text-transform: uppercase;
            }
            .cuadrilla-info {
                background-color: #d4edda;
                border: 1px solid #c3e6cb;
                border-radius: 10px;
                padding: 20px;
                margin: 25px 0;
                text-align: center;
            }
            .cuadrilla-info h3 {
                margin: 0 0 15px 0;
                color: #155724;
                font-size: 16px;
            }
            .cuadrilla-info .archivo {
                background-color: #ffffff;
                border: 2px dashed ' . $colorPrimario . ';
                border-radius: 8px;
                padding: 15px;
                margin: 10px 0;
            }
            .cuadrilla-info .archivo-icono {
                font-size: 40px;
                color: ' . $colorPrimario . ';
            }
            .cuadrilla-info .archivo-nombre {
                font-weight: bold;
                color: #333;
                word-break: break-all;
            }
            .comentario-box {
                background-color: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 8px 8px 0;
            }
            .comentario-box h3 {
                margin: 0 0 10px 0;
                color: #856404;
                font-size: 14px;
            }
            .comentario-box p {
                margin: 0;
                color: #856404;
            }
            .footer {
                background-color: #343a40;
                color: #ffffff;
                text-align: center;
                padding: 20px;
                font-size: 12px;
            }
            .footer p {
                margin: 5px 0;
            }
            .footer a {
                color: ' . $colorSecundario . ';
                text-decoration: none;
            }
            table.actividades {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 13px;
            }
            table.actividades th {
                background-color: ' . $colorPrimario . ';
                color: white;
                padding: 12px 8px;
                text-align: left;
                font-weight: bold;
            }
            table.actividades td {
                border: 1px solid #dee2e6;
                padding: 10px 8px;
            }
            table.actividades tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            table.actividades tr:nth-child(odd) {
                background-color: #ffffff;
            }
            .small {
                font-size: 12px;
                color: #6c757d;
            }
            h3.section-title {
                color: ' . $colorPrimario . ';
                font-size: 16px;
                margin: 25px 0 15px 0;
                padding-bottom: 8px;
                border-bottom: 2px solid ' . $colorPrimario . ';
            }
            .contacto-box {
                background-color: #e7f3ff;
                border: 1px solid #b6d4fe;
                border-radius: 8px;
                padding: 15px;
                margin: 20px 0;
                text-align: center;
            }
            .contacto-box p {
                margin: 5px 0;
                font-size: 13px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://medicina.uchile.cl/.resources/portal-medicina/images/medicina-logo.png" alt="Logo Facultad de Medicina">
                <h1>¡Cuadrilla Recepcionada!</h1>
                <p>Centro de Habilidades Clínicas - Universidad de Chile</p>
            </div>
            
            <div class="content">
                <p class="saludo">Estimado/a <strong>' . $nombrePEC . '</strong>,</p>
                
                <p style="text-align: center;">
                    <span class="estado-badge">✓ CUADRILLA LISTA</span>
                </p>
                
                <p>Le informamos que el equipo del Centro de Habilidades Clínicas ha <strong>recepcionado</strong> la cuadrilla para su solicitud de agenda CHC.</p>
                
                <div class="curso-destacado">
                    <h2>' . $codigoCurso . ' - Sección ' . $seccionCurso . '</h2>
                    <p class="curso-nombre">' . $nombreCurso . '</p>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="numero">' . count($actividades) . '</span>
                        <span class="etiqueta">Actividades</span>
                    </div>
                    <div class="info-item">
                        <span class="numero">' . $solicitud['npacientes'] . '</span>
                        <span class="etiqueta">Pacientes Sim.</span>
                    </div>
                    <div class="info-item">
                        <span class="numero">' . $solicitud['nestudiantesxsesion'] . '</span>
                        <span class="etiqueta">Estudiantes/Sesión</span>
                    </div>
                </div>
                
                <div class="cuadrilla-info">
                    <h3>Documento de Cuadrilla Adjunto</h3>
                    <div class="archivo">
                        <div class="archivo-icono">📄</div>
                        <p class="archivo-nombre">' . $nombreArchivoEsc . '</p>
                        <p class="small">Revise el PDF adjunto con el detalle de la cuadrilla asignada</p>
                    </div>
                    <p class="small" style="margin-top: 10px;">
                        Procesado el ' . $fechaActual . ' por ' . $nombreGestorEsc . '
                    </p>
                </div>';
    
    // Agregar comentario si existe
    if (!empty($comentario)) {
        $comentarioEscapado = nl2br(htmlspecialchars($comentario, ENT_QUOTES, 'UTF-8'));
        $contenidoCorreo .= '
                <div class="comentario-box">
                    <h3>📝 Comentario del Docente</h3>
                    <p>' . $comentarioEscapado . '</p>
                </div>';
    }
    
    // Agregar tabla de actividades
    if (count($actividades) > 0) {
        $contenidoCorreo .= '
                <h3 class="section-title">📅 Detalle de sus Actividades</h3>
                <table class="actividades">
                    <thead>
                        <tr>
                            <th>Actividad</th>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Horario</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($actividades as $act) {
            $tituloActividad = htmlspecialchars($act['pcl_tituloActividad'], ENT_QUOTES, 'UTF-8');
            $diaActividad = htmlspecialchars($act['dia'], ENT_QUOTES, 'UTF-8');
            
            $contenidoCorreo .= '
                        <tr>
                            <td>' . $tituloActividad . '</td>
                            <td>' . $act['fecha'] . '</td>
                            <td>' . $diaActividad . '</td>
                            <td>' . $act['hora_inicio'] . ' - ' . $act['hora_termino'] . '</td>
                        </tr>';
        }
        
        $contenidoCorreo .= '
                    </tbody>
                </table>';
    }
    
    $contenidoCorreo .= '
                <div class="contacto-box">
                    <p><strong>¿Tiene consultas sobre la cuadrilla?</strong></p>
                    <p>Contacte al Centro de Habilidades Clínicas: <a href="mailto:chc.med@uchile.cl">chc.med@uchile.cl</a></p>
                </div>
                
                <p class="small" style="margin-top: 25px; text-align: center;">
                    <em>Este es un mensaje automático del Sistema de Gestión CHC.<br>
                    Solicitud N° ' . $idsolicitud . ' | Modalidad: ' . $modalidadCurso . '</em>
                </p>
            </div>
            
            <div class="footer">
                <p><strong>Centro de Habilidades Clínicas</strong></p>
                <p>Facultad de Medicina - Universidad de Chile</p>
                <p>&copy; ' . date('Y') . ' - Sistema de Gestión de Agenda CHC</p>
            </div>
        </div>
    </body>
    </html>';
    
    // =========================================================================
    // 5. CONFIGURAR Y ENVIAR CORREO CON PHPMAILER
    // =========================================================================
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
        $mail->FromName = "Centro de Habilidades Clínicas";
        
        // Asunto diferenciado para el PEC
        $mail->Subject = "✓ Cuadrilla Lista - " . $solicitud['codigocurso'] . " Sec. " . $solicitud['seccion'];
        
        // Destinatario: correo del PEC
        $mail->addAddress($solicitud['correopec']);
		//$mail->addAddress($destinatarioFijo);
        
        // Adjuntar el PDF
        if (file_exists($rutaPDF)) {
            $nombreArchivoFormateado = 'Cuadrilla_' . $codigoCurso . '_Sec' . $seccionCurso . '.pdf';
            $mail->addAttachment($rutaPDF, $nombreArchivoFormateado);
        } else {
            error_log("CORREO CUADRILLA PEC: Archivo PDF no encontrado - Ruta: $rutaPDF");
        }
        
        $mail->MsgHTML($contenidoCorreo);
        
        if (!$mail->Send()) {
            error_log("CORREO CUADRILLA PEC ERROR: " . $mail->ErrorInfo . " - Solicitud: $idsolicitud");
            return false;
        }
        
        $mail->ClearAllRecipients();
        $mail->ClearAttachments();
        
        error_log("CORREO CUADRILLA PEC ENVIADO: Solicitud $idsolicitud - Destinatario: " . $solicitud['correopec']);
        return true;
        
    } catch (Exception $e) {
        error_log("CORREO CUADRILLA PEC EXCEPCION: " . $e->getMessage() . " - Solicitud: $idsolicitud");
        return false;
    }
}
?>