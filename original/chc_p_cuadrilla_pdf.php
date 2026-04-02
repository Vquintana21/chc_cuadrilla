<?php
/**
 * chc_p_cuadrilla_pdf.php
 * Genera el PDF de resumen de cuadrilla CHC usando TCPDF.
 *
 * Uso como endpoint (descarga/visualización):
 *   chc_p_cuadrilla_pdf.php?cuadrilla=X
 *
 * Uso interno (para adjuntar en correo):
 *   require_once('chc_p_cuadrilla_pdf.php');
 *   $ruta = generarPDFCuadrilla($conn, $idcuadrilla);
 *   // $ruta es la ruta del archivo temporal generado
 */

// ============================================================
// MODO: endpoint directo o llamado como función
// ============================================================
$modoFuncion = defined('CHC_PDF_MODO_FUNCION');

if(!$modoFuncion) {
    session_start();
    require_once('conexion.php');

    if(empty($_SESSION['sesion_idLogin'])) {
        http_response_code(403);
        echo 'Acceso no autorizado';
        exit;
    }

    $idCuadrilla = isset($_GET['cuadrilla']) ? intval($_GET['cuadrilla']) : 0;
    if($idCuadrilla <= 0) {
        http_response_code(400);
        echo 'Cuadrilla no especificada';
        exit;
    }

    // Generar y enviar al navegador
    $pdf = generarPDFCuadrilla($conn, $idCuadrilla);
    if($pdf === false) {
        http_response_code(404);
        echo 'No se pudo generar el PDF';
        exit;
    }
    // $pdf es el contenido binario del PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Cuadrilla_CHC_' . $idCuadrilla . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

// ============================================================
// FUNCIÓN PRINCIPAL
// ============================================================
function generarPDFCuadrilla($conn, $idCuadrilla, $guardarEnDisco = false) {

    // --- Cargar TCPDF (ajusta la ruta según tu servidor) ---
    $rutaTCPDF = __DIR__ . '/tcpdf/tcpdf.php';
    if(!file_exists($rutaTCPDF)) {
        error_log("CHC PDF: TCPDF no encontrado en $rutaTCPDF");
        return false;
    }
    require_once($rutaTCPDF);

    // --- Obtener datos de la cuadrilla ---
    $sqlCuad = "
        SELECT cq.*, cs.nombre AS nombre_subtipo,
               cs.tiene_pacientes, cs.tiene_insumos, cs.tiene_debriefing,
               cs.tiene_link, cs.tiene_ubicacion,
               sol.codigocurso, sol.seccion, sol.nombrecurso,
               sol.npacientes, sol.uso_debriefing, sol.nombrepec, sol.correopec,
               m.modalidad
        FROM chc_p_cuadrilla cq
        INNER JOIN chc_p_cuadrilla_subtipo cs ON cq.idsubtipo   = cs.idsubtipo
        INNER JOIN chc_solicitud sol           ON cq.idsolicitud = sol.idsolicitud
        LEFT  JOIN chc_solicitud_modalidad sm  ON sol.idsolicitud = sm.idsolicitud
        LEFT  JOIN chc_modalidad m             ON sm.idmodalidad  = m.idmodalidad
        WHERE cq.idcuadrilla = $idCuadrilla
        LIMIT 1";
    $resPdfC = mysqli_query($conn, $sqlCuad);
    $cuad    = $resPdfC ? mysqli_fetch_assoc($resPdfC) : null;

    if(!$cuad) return false;

    // Fechas
    $resPdfF = mysqli_query($conn,
        "SELECT cf.*,
                DATE_FORMAT(p.pcl_Fecha,'%d/%m/%Y') AS fecha_display,
                DATE_FORMAT(p.pcl_Inicio,'%H:%i')   AS bloque_inicio,
                DATE_FORMAT(p.pcl_Termino,'%H:%i')  AS bloque_termino
         FROM chc_p_cuadrilla_fecha cf
         INNER JOIN planclases p ON cf.idplanclases = p.idplanclases
         WHERE cf.idcuadrilla = $idCuadrilla
         ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC");
    $fechas = array();
    if($resPdfF) { while($r=mysqli_fetch_assoc($resPdfF)) $fechas[]=$r; }

    // Capacitaciones
    $resPdfCap = mysqli_query($conn,
        "SELECT * FROM chc_p_cuadrilla_capacitacion WHERE idcuadrilla = $idCuadrilla ORDER BY orden ASC");
    $caps = array();
    if($resPdfCap) { while($r=mysqli_fetch_assoc($resPdfCap)) $caps[]=$r; }

    // Debriefing
    $resPdfDeb = mysqli_query($conn,
        "SELECT * FROM chc_p_cuadrilla_debriefing WHERE idcuadrilla = $idCuadrilla LIMIT 1");
    $debrief = $resPdfDeb ? mysqli_fetch_assoc($resPdfDeb) : null;

    // ============================================================
    // CREAR PDF
    // ============================================================
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Metadatos
    $pdf->SetCreator('Sistema CHC - Universidad de Chile');
    $pdf->SetAuthor('Gestión CHC');
    $pdf->SetTitle('Cuadrilla CHC #' . $idCuadrilla);
    $pdf->SetSubject($cuad['codigocurso'] . '-' . $cuad['seccion'] . ' ' . $cuad['nombrecurso']);

    // Márgenes y fuente
    $pdf->SetMargins(15, 45, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->SetFont('helvetica', '', 10);

    // ---- HEADER personalizado ----
    $pdf->setHeaderCallback(function($pdf) use ($cuad, $idCuadrilla) {
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetTextColor(26, 82, 118);
        $pdf->Cell(0, 8, 'Cuadrilla CHC — ' . $cuad['codigocurso'] . '-' . $cuad['seccion'], 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, $cuad['nombrecurso'] . ' | Cuadrilla #' . $idCuadrilla . ' | ' .
            date('d/m/Y', strtotime($cuad['fecha_envio'] ?? $cuad['fecha_creacion'])), 0, 1, 'L');
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(2);
    });

    // ---- FOOTER ----
    $pdf->setFooterCallback(function($pdf) {
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 10,
            'Centro de Habilidades Clínicas — Facultad de Medicina — Universidad de Chile  |  Página ' .
            $pdf->getAliasNumPage() . ' de ' . $pdf->getAliasNbPages(), 0, 0, 'C');
    });

    $pdf->AddPage();

    // Colores
    $azulOscuro  = array(26, 82, 118);
    $azulClaro   = array(213, 234, 248);
    $verdeOscuro = array(20, 108, 67);
    $verdeClaro  = array(209, 231, 221);
    $grisFondo   = array(248, 249, 250);
    $negro       = array(33, 37, 41);

    // ---- Helper: título de sección ----
    $seccionTitulo = function($pdf, $texto) use ($azulOscuro, $azulClaro) {
        $pdf->SetFillColor($azulClaro[0], $azulClaro[1], $azulClaro[2]);
        $pdf->SetTextColor($azulOscuro[0], $azulOscuro[1], $azulOscuro[2]);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, $texto, 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);
    };

    // ---- Helper: campo label + valor ----
    $campo = function($pdf, $label, $valor, $ancho = 0) use ($grisFondo, $negro) {
        if($ancho === 0) $ancho = 165;
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 5, strtoupper($label), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor($negro[0], $negro[1], $negro[2]);
        $pdf->SetFillColor($grisFondo[0], $grisFondo[1], $grisFondo[2]);
        $pdf->MultiCell(0, 7, $valor ?: '—', 1, 'L', true);
        $pdf->Ln(2);
    };

    // ============================================================
    // DATOS DE IDENTIFICACIÓN
    // ============================================================
    $seccionTitulo($pdf, '  Información General');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0,0,0);

    // Tabla de identificación 2 columnas
    $pdf->SetFillColor(240, 244, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $anchoCelda = 82.5;

    $filaId = function($pdf, $l1, $v1, $l2, $v2) use ($anchoCelda, $grisFondo) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(108,117,125);
        $pdf->Cell($anchoCelda, 5, strtoupper($l1), 0, 0, 'L');
        $pdf->Cell($anchoCelda, 5, strtoupper($l2), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(33,37,41);
        $pdf->SetFillColor($grisFondo[0],$grisFondo[1],$grisFondo[2]);
        $pdf->Cell($anchoCelda, 7, $v1 ?: '—', 1, 0, 'L', true);
        $pdf->Cell($anchoCelda, 7, $v2 ?: '—', 1, 1, 'L', true);
        $pdf->Ln(1);
    };

    $filaId($pdf, 'Código de Curso', $cuad['codigocurso'] . '-' . $cuad['seccion'],
                  'Modalidad', $cuad['modalidad']);
    $filaId($pdf, 'Nombre del Curso', $cuad['nombrecurso'],
                  'Subtipo', $cuad['nombre_subtipo']);
    $filaId($pdf, 'Profesor (PEC)', $cuad['nombrepec'],
                  'Correo PEC', $cuad['correopec']);
    $filaId($pdf, 'Cuadrilla #', $idCuadrilla,
                  'Fecha envío', $cuad['fecha_envio'] ? date('d/m/Y H:i', strtotime($cuad['fecha_envio'])) : '—');

    $pdf->Ln(3);

    // ============================================================
    // SECCIÓN 1: Resumen
    // ============================================================
    $seccionTitulo($pdf, '  1. Descripción General de la Actividad');
    $campo($pdf, 'Resumen de la actividad', $cuad['resumen_actividad']);

    // ============================================================
    // SECCIÓN 2: Logística y Programación
    // ============================================================
    $seccionTitulo($pdf, '  2. Logística y Programación');

    // 2A: Tabla de fechas
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(33,37,41);
    $pdf->Cell(0, 6, 'Fechas Programadas', 0, 1, 'L');
    $pdf->Ln(1);

    if(empty($fechas)) {
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(220, 53, 69);
        $pdf->Cell(0, 6, 'Sin fechas registradas', 0, 1, 'L');
    } else {
        // Cabecera tabla fechas
        $pdf->SetFillColor($azulClaro[0], $azulClaro[1], $azulClaro[2]);
        $pdf->SetTextColor($azulOscuro[0], $azulOscuro[1], $azulOscuro[2]);
        $pdf->SetFont('helvetica', 'B', 8);

        $anchos = [42, 32, 22, 22];
        $cols   = ['Fecha', 'Bloque agendado', 'H. Inicio', 'H. Término'];

        if($cuad['tiene_pacientes']) { $anchos[] = 20; $cols[] = 'Nro Pac.'; }
        if($cuad['tiene_link'])      { $anchos[] = 27; $cols[] = 'Link'; }
        if($cuad['tiene_ubicacion']) { $anchos[] = 27; $cols[] = 'Ubicación'; }

        foreach($cols as $k => $col) {
            $pdf->Cell($anchos[$k], 7, $col, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Filas
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $alternado = false;
        foreach($fechas as $f) {
            $fill = $alternado ? array(248,249,250) : array(255,255,255);
            $pdf->SetFillColor($fill[0],$fill[1],$fill[2]);

            $hi = $f['hora_inicio']  ? date('H:i',strtotime($f['hora_inicio']))  : '—';
            $ht = $f['hora_termino'] ? date('H:i',strtotime($f['hora_termino'])) : '—';

            $pdf->Cell($anchos[0], 6, $f['fecha_display'], 1, 0, 'L', true);
            $pdf->Cell($anchos[1], 6, $f['bloque_inicio'].' — '.$f['bloque_termino'], 1, 0, 'C', true);
            $pdf->Cell($anchos[2], 6, $hi, 1, 0, 'C', true);
            $pdf->Cell($anchos[3], 6, $ht, 1, 0, 'C', true);

            $col4 = 4;
            if($cuad['tiene_pacientes']) {
                $pdf->Cell($anchos[$col4], 6, $f['nro_pacientes'] ?? '—', 1, 0, 'C', true);
                $col4++;
            }
            if($cuad['tiene_link']) {
                $link = $f['link_actividad'] ? 'Ver link' : '—';
                $pdf->Cell($anchos[$col4], 6, $link, 1, 0, 'C', true);
                $col4++;
            }
            if($cuad['tiene_ubicacion']) {
                $ubic = $f['ubicacion'] ? mb_substr($f['ubicacion'],0,20,'UTF-8').(strlen($f['ubicacion'])>20?'...':'') : '—';
                $pdf->Cell($anchos[$col4], 6, $ubic, 1, 0, 'L', true);
            }
            $pdf->Ln();
            $alternado = !$alternado;
        }
    }
    $pdf->Ln(4);

    // 2B: Capacitaciones
    if($cuad['tiene_pacientes']) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(33,37,41);
        $pdf->Cell(0, 6, 'Capacitación de Paciente Simulado', 0, 1, 'L');
        $pdf->Ln(1);

        if(empty($caps)) {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(220,53,69);
            $pdf->Cell(0, 6, 'Sin fechas de capacitación', 0, 1, 'L');
        } else {
            $pdf->SetFillColor($azulClaro[0],$azulClaro[1],$azulClaro[2]);
            $pdf->SetTextColor($azulOscuro[0],$azulOscuro[1],$azulOscuro[2]);
            $pdf->SetFont('helvetica', 'B', 8);
            foreach(['#','Modalidad','Fecha','Jornada'] as $k=>$h) {
                $pdf->Cell([8,45,40,52][$k], 7, $h, 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica','',8);
            $pdf->SetTextColor(0,0,0);
            $alt=false;
            foreach($caps as $cap) {
                $fill=$alt?array(248,249,250):array(255,255,255);
                $pdf->SetFillColor($fill[0],$fill[1],$fill[2]);
                $pdf->Cell(8,  6, $cap['orden'],0, 0,'C',true);
                $pdf->Cell(45, 6, $cap['modalidad']??'—', 1, 0,'L',true);
                $pdf->Cell(40, 6, $cap['fecha']?date('d/m/Y',strtotime($cap['fecha'])):'—', 1, 0,'C',true);
                $pdf->Cell(52, 6, $cap['jornada']??'—', 1, 1,'L',true);
                $alt=!$alt;
            }
        }
        $pdf->Ln(4);
    }

    // 2C: Insumos
    if($cuad['tiene_insumos']) {
        $pdf->SetFont('helvetica','B',9);
        $pdf->SetTextColor(33,37,41);
        $pdf->Cell(0,6,'Insumos y Equipamiento',0,1,'L');
        $pdf->Ln(1);
        $campo($pdf,'Listado de insumos', $cuad['insumos']);
    }

    // 2D: Debriefing
    if($cuad['tiene_debriefing'] && $cuad['uso_debriefing'] == 1) {
        $pdf->SetFont('helvetica','B',9);
        $pdf->SetTextColor(33,37,41);
        $pdf->Cell(0,6,'Implementación Física — Inducción y Retroalimentación',0,1,'L');
        $pdf->Ln(1);
        $campo($pdf, 'Inducción (briefing)',        $debrief['implementacion_briefing']   ?? '');
        $campo($pdf, 'Retroalimentación (debriefing)', $debrief['implementacion_debriefing'] ?? '');
    }

    // ============================================================
    // SALIDA DEL PDF
    // ============================================================
    $nombre = 'Cuadrilla_CHC_' . $idCuadrilla . '_' .
              $cuad['codigocurso'] . '_Sec' . $cuad['seccion'] . '.pdf';

    if($guardarEnDisco) {
        // Guardar en archivo temporal y devolver la ruta
        $dir  = __DIR__ . '/uploads/cuadrillas/';
        if(!is_dir($dir)) mkdir($dir, 0755, true);
        $ruta = $dir . $nombre;
        $pdf->Output($ruta, 'F');
        return $ruta;
    } else {
        // Devolver el contenido binario del PDF como string
        return $pdf->Output($nombre, 'S');
    }
}
?>
