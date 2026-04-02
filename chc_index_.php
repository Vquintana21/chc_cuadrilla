<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccione bloques para la actividad Presencial</title>
    <style>
        .chc-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
        }
        
        .chc-container h3 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .info-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        #tablaActividadesCHC {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        #tablaActividadesCHC thead {
            background-color: #f8f9fa;
        }
        
        #tablaActividadesCHC th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        #tablaActividadesCHC td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .checkbox-cell {
            width: 50px;
            text-align: center;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 24px;
            background-color: #2196F3;
            border-radius: 12px;
            cursor: pointer;
            display: inline-block;
        }
        
        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }
        
        input[type="checkbox"] {
            display: none;
        }
        
        input[type="checkbox"]:checked + .toggle-switch::after {
            transform: translateX(26px);
        }
        
        .btn-siguiente-chc {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-siguiente-chc:hover {
            background-color: #1976D2;
        }
        
        #tablaActividadesCHC tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="chc-container">
        <h3>Seleccione bloques para la actividad Presencial</h3>
        <p class="info-text">// Por defecto estarán todas marcadas</p>
        
        <table id="tablaActividadesCHC">
            <thead>
                <tr>
                    <th class="checkbox-cell"></th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Hora Inicio</th>
                    <th>Hora Término</th>
                    <th>Actividad</th>
                    <th>Tipo de Actividad</th>
                </tr>
            </thead>
            <tbody id="bodyActividades">
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <button class="btn-siguiente-chc" onclick="siguienteCHC()">Siguiente</button>
    </div>
</body>
</html>