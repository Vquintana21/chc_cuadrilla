<?php
session_start();
require_once('conexion.php');

header('Content-Type: application/json');

// ===== CARGAR ESTADO PERÍODO CHC =====
$sqlPeriodoCHC = "SELECT inicio, fin, fin_revision FROM chc_periodosys WHERE activo = 1 LIMIT 1";
$resultPeriodoCHC = mysqli_query($conn, $sqlPeriodoCHC);
$periodoCHC = mysqli_fetch_assoc($resultPeriodoCHC);

$fechaActualCHC = date('Y-m-d H:i:s');
$chcPuedeEditar = false;
$chcEnRevision = false;
$chcPeriodoVencido = false; // NUEVO: true cuando ya pasó la fecha 'fin'

if($periodoCHC) {
    if($fechaActualCHC >= $periodoCHC['inicio'] && $fechaActualCHC <= $periodoCHC['fin']) {
        $chcPuedeEditar = true;
    } elseif($fechaActualCHC > $periodoCHC['fin'] && $fechaActualCHC <= $periodoCHC['fin_revision']) {
        $chcEnRevision = true;
        $chcPeriodoVencido = true; // Ya pasó fin
    } elseif($fechaActualCHC > $periodoCHC['fin']) {
        // Después de fin_revision, el periodo sigue vencido
        $chcPeriodoVencido = true;
    }
}

echo json_encode([
    'puedeEditar' => $puedeEditar,
    'enRevision' => $enRevision,
    'periodo' => $periodoActivo
]);