# CHC Cuadrilla - Guia para Claude Code

## Proyecto
Modulo de cuadrilla (planificacion de actividades clinicas) para el sistema CHC
(Centro de Habilidades Clinicas) de la Facultad de Medicina, Universidad de Chile.

## Stack
- **Backend:** PHP 5.6 (OBLIGATORIO - ver restricciones abajo)
- **BD:** MySQL 5.7 en `dpimeduc_calendario` (localhost)
- **Frontend:** JavaScript Vanilla (ES5+), Bootstrap 5.3.2, SweetAlert2
- **PDF:** TCPDF (ruta: `__DIR__ . '/tcpdf/tcpdf.php'`)
- **Email:** PHPMailer (ruta: `__DIR__ . '/phpmailer/PHPMailerAutoload.php'`)

## Restricciones PHP 5.6 - CRITICAS

```php
// PROHIBIDO:
$x = $_POST['campo'] ?? '';           // operador ?? NO existe en PHP 5.6
$arr = ['a', 'b'];                     // array corta prohibida en json_encode
switch($x) { case 'a': $y = $z ? 1 : 0; } // ternarios en switch causan bugs
mysqli_stmt_get_result($stmt);         // puede fallar sin driver mysqlnd

// CORRECTO:
$x = isset($_POST['campo']) ? $_POST['campo'] : '';
$arr = array('a', 'b');
if($x === 'a') { $y = $z ? 1 : 0; }  // usar if/elseif
mysqli_query($conn, $sql);             // con mysqli_real_escape_string()
```

**Helper estandar para POST:**
```php
function post($key, $default) {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? $_POST[$key] : $default;
}
```

## Arquitectura SPA

El sistema se carga dentro de `index_clinico.php`. El modulo CHC vive en un `div#chc-list`.
Los archivos PHP de cuadrilla se cargan via `fetch()` y se inyectan con `innerHTML`.

**Regla critica:** Los `<script>` dentro de HTML inyectado NO se ejecutan automaticamente.
Se usa `ejecutarScriptsHTML(contenedor)` (definida en `chc.js` Seccion 13) para re-ejecutarlos.

**Funciones globales onclick** deben estar en `chc.js`, NUNCA en los PHP cargados via fetch.
Los PHP pueden definir funciones en sus `<script>` internos porque se ejecutan via `ejecutarScriptsHTML()`.

## Estructura de Archivos

### Archivos del modulo cuadrilla (prefijo `chc_p_`)
| Archivo | Tipo | Descripcion |
|---------|------|-------------|
| `chc_index.php` | PHP/HTML | Vista principal. Muestra lista de solicitudes con botones de cuadrilla |
| `chc.js` | JS Global | Funciones onclick globales. Seccion 13 = Cuadrilla |
| `chc_p_cuadrilla_crear.php` | PHP/HTML | Formulario wizard 3 pasos. Se carga en #chc-list via fetch |
| `chc_p_cuadrilla_guardar.php` | PHP API | Backend AJAX para guardado. 6 acciones |
| `chc_p_cuadrilla_verificar.php` | PHP/HTML | Vista verificacion con edicion inline. Se carga en #chc-list |
| `chc_p_cuadrilla_editar.php` | PHP API | Backend AJAX para ediciones en verificacion. 5 acciones |
| `chc_p_cuadrilla_enviar.php` | PHP API | Cambia estado a 3, genera PDF, envia correo |
| `chc_p_cuadrilla_pdf.php` | PHP Dual | Endpoint GET para PDF o funcion interna para adjunto |

### Archivos de produccion (solo lectura excepto idestadocuadrilla)
| Archivo | Descripcion |
|---------|-------------|
| `chc_cuadrilla.php` | Sistema antiguo de subida PDF (reemplazado por formulario) |
| `chc_correo_cuadrilla.php` | Correos del sistema antiguo |
| `chc_guardar_solicitud.php` | Guardado de solicitudes (no tocar) |

### Documentacion
| Archivo | Descripcion |
|---------|-------------|
| `Requerimientos_Cuadrilla_CHC.docx` | Documento de requerimientos original |
| `dpimeduc_calendario.sql` | Schema BD produccion (15 tablas) |
| `dpimeduc_calendario (1).sql` | Schema BD desarrollo (5 tablas nuevas chc_p_*) |

## Variables de Sesion
| Variable | Contenido |
|----------|-----------|
| `$_SESSION['sesion_idLogin']` | RUT del usuario (PEC) sin padding |
| `$_SESSION['sesion_usuario']` | Nombre del usuario |
| `$_SESSION['chc_idcurso']` | ID del curso en gestion |

Normalizacion RUT: `$rut = str_pad($_SESSION['sesion_idLogin'], 10, '0', STR_PAD_LEFT);`

## Flujo de la Cuadrilla

```
chc_index.php (boton aparece si idestadoagenda=2)
    |
    v
irACuadrilla(idsolicitud, 0)  -->  chc_p_cuadrilla_crear.php
    |                                    |
    |  Paso 1: Subtipo + Resumen         |-- guardar_seccion1 --> chc_p_cuadrilla_guardar.php
    |  Paso 2: Fechas + Caps + etc       |-- guardar_fecha, guardar_capacitacion, etc
    |  Paso 3: Estaciones (PENDIENTE)    |
    |                                    |
    v                                    v
irAVerificarCuadrilla(id)  -->  chc_p_cuadrilla_verificar.php
    |                                    |
    |  Edicion inline por secciones      |-- editar_seccion1 --> chc_p_cuadrilla_editar.php
    |                                    |-- editar_fechas, editar_capacitaciones, etc
    |                                    |
    v                                    v
"Enviar Cuadrilla"  -->  chc_p_cuadrilla_enviar.php
    |
    |-- UPDATE estado=3
    |-- Genera PDF via chc_p_cuadrilla_pdf.php
    |-- Envia correo a admins (admin=2) con PDF adjunto
    v
Solo lectura (estado=3)
```

## Estados

| chc_p_cuadrilla.estado | chc_solicitud.idestadocuadrilla | Nombre | Descripcion |
|------------------------|-------------------------------|--------|-------------|
| 1 | 1 | En creacion | PEC completando formulario |
| 2 | 2 | En verificacion | (futuro, no usado aun) |
| 3 | 3 | Enviada | Solo lectura, admins notificados |

## Tablas de Base de Datos

### Tablas nuevas del modulo (prefijo chc_p_)
- `chc_p_cuadrilla` - Cabecera. Una por solicitud (UNIQUE idsolicitud)
- `chc_p_cuadrilla_fecha` - Fechas programadas. FK a chc_p_cuadrilla + planclases_test
- `chc_p_cuadrilla_capacitacion` - Capacitaciones PS. 1-5 filas por cuadrilla
- `chc_p_cuadrilla_debriefing` - Briefing/debriefing. 1 fila por cuadrilla (UNIQUE)
- `chc_p_cuadrilla_subtipo` - Tabla parametrica con flags. 9 registros fijos

### Tablas de produccion usadas (solo lectura)
- `chc_solicitud` - Solicitudes (ESCRITURA solo en campo idestadocuadrilla)
- `chc_solicitud_actividad` - Actividades asociadas
- `chc_solicitud_modalidad` - Modalidad de la solicitud
- `planclases_test` - Calendario academico (MyISAM, sin FK posible)
- `chc_usuario` - Usuarios. admin=2 son destinatarios del correo
- `chc_modalidad` - Catalogo de modalidades
- `chc_estado_cuadrilla` - Catalogo de estados

## Pendientes / Stand By

1. **Seccion 3 - Estaciones:** STAND BY. Pendiente definicion con el cliente
2. **Edicion post-envio:** PENDIENTE. Flujo para correccion tras envio (estado=3)
3. **Vista del Gestor CHC:** PENDIENTE. Vista para admins que vean cuadrillas enviadas
4. **location.reload() en verificar.php:** Recarga pagina completa en vez de refrescar #chc-list via SPA. Mejora UX pendiente
5. **Formato PDF definitivo:** El cliente definira plantilla institucional final

## Correcciones Aplicadas (sesion anterior)
- Agregadas funciones JS: irACuadrilla, irAVerificarCuadrilla, ejecutarScriptsHTML (chc.js Seccion 13)
- Eliminado operador ?? en 4 archivos (33 ocurrencias)
- Reemplazado mysqli_stmt_get_result en 5 archivos (19 ocurrencias)
- Convertido json_encode con array corta [] a array()
- Corregida navegacion SPA en crear.php y verificar.php

## Comandos utiles
```bash
# Verificar compatibilidad PHP 5.6 (no debe haber resultados)
grep -rn '??' chc_p_cuadrilla_*.php --include="*.php" | grep -v '//'
grep -rn 'mysqli_stmt_get_result' chc_p_cuadrilla_*.php
grep -rn 'json_encode(\[' chc_p_cuadrilla_*.php

# Verificar sintaxis PHP
for f in chc_p_cuadrilla_*.php; do php -l "$f"; done
```
