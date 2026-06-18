# Data Map: Requerimientos -> Estructura de Datos
# Modulo Cuadrilla CHC

Este documento mapea cada campo del formulario de cuadrilla a su tabla/columna
en la BD, la API que lo guarda, y las reglas de negocio asociadas.

---

## Mapa General

```
FORMULARIO                    TABLA BD                        API (guardar.php)
===========                   ========                        =================
Paso 1:
  Subtipo ----------------->  chc_p_cuadrilla.idsubtipo       guardar_seccion1
  Resumen ----------------->  chc_p_cuadrilla.resumen_act.    guardar_seccion1

Paso 2A (por cada fecha):
  Hora inicio ------------->  chc_p_cuadrilla_fecha.hora_inicio      guardar_fecha
  Hora termino ------------>  chc_p_cuadrilla_fecha.hora_termino     guardar_fecha
  Nro pacientes ----------->  chc_p_cuadrilla_fecha.nro_pacientes    guardar_fecha
  Link actividad ---------->  chc_p_cuadrilla_fecha.link_actividad   guardar_fecha
  Ubicacion --------------->  chc_p_cuadrilla_fecha.ubicacion        guardar_fecha

Paso 2B (1-5 filas):
  Modalidad cap ----------->  chc_p_cuadrilla_capacitacion.modalidad     guardar_capacitacion
  Fecha cap --------------->  chc_p_cuadrilla_capacitacion.fecha         guardar_capacitacion
  Jornada cap ------------->  chc_p_cuadrilla_capacitacion.jornada       guardar_capacitacion

Paso 2C:
  Insumos ----------------->  chc_p_cuadrilla.insumos        guardar_insumos

Paso 2D:
  Briefing ---------------->  chc_p_cuadrilla_debriefing.implementacion_briefing    guardar_debriefing
  Debriefing -------------->  chc_p_cuadrilla_debriefing.implementacion_debriefing  guardar_debriefing

Paso 3:
  (PENDIENTE)

Campos automaticos:
  Estado ------------------>  chc_p_cuadrilla.estado (1->3)
  RUT PEC ----------------->  chc_p_cuadrilla.rut_pec (sesion)
  Fecha creacion ---------->  chc_p_cuadrilla.fecha_creacion (NOW)
  Fecha modificacion ------>  chc_p_cuadrilla.fecha_modificacion (NOW en cada save)
  Fecha envio ------------->  chc_p_cuadrilla.fecha_envio (NOW al enviar)
  Estado solicitud -------->  chc_solicitud.idestadocuadrilla (1->3)
```

---

## Detalle por Tabla

### chc_p_cuadrilla (cabecera)
| Columna | Origen | Quien escribe | Cuando |
|---------|--------|--------------|--------|
| idcuadrilla | AUTO_INCREMENT | MySQL | Al crear |
| idsolicitud | URL param ?solicitud= | guardar_seccion1 | Al crear |
| idsubtipo | SELECT del formulario | guardar_seccion1 / editar_seccion1 | Paso 1 |
| resumen_actividad | TEXTAREA del formulario | guardar_seccion1 / editar_seccion1 | Paso 1 |
| insumos | TEXTAREA del formulario | guardar_insumos / editar_insumos | Paso 2C |
| estado | Automatico | guardar_seccion1(=1), enviar(=3) | Crear / Enviar |
| rut_pec | $_SESSION['sesion_idLogin'] | guardar_seccion1 | Al crear |
| fecha_creacion | NOW() | guardar_seccion1 | Al crear |
| fecha_modificacion | NOW() | Todos los guardar/editar | Cada guardado |
| fecha_envio | NOW() | enviar_cuadrilla | Al enviar |

### chc_p_cuadrilla_fecha (una fila por actividad programada)
| Columna | Origen | Restricciones |
|---------|--------|--------------|
| idcuadrilla | FK | Cascade delete |
| idplanclases | De chc_solicitud_actividad | No se puede agregar/quitar filas. Las filas son fijas segun actividades de la solicitud |
| hora_inicio | SELECT 15min | Rango: pcl_Inicio a (pcl_Termino - 15min). Formato HH:mm:ss |
| hora_termino | SELECT 15min | Rango: (pcl_Inicio + 15min) a pcl_Termino. Formato HH:mm:ss |
| nro_pacientes | SELECT 1-N | Solo si subtipo.tiene_pacientes=1. Max = chc_solicitud.npacientes |
| link_actividad | INPUT URL | Solo si subtipo.tiene_link=1 (subtipo 7) |
| ubicacion | TEXTAREA | Solo si subtipo.tiene_ubicacion=1 (subtipo 8) |

**UNIQUE(idcuadrilla, idplanclases)** - Una fila por actividad por cuadrilla.
**UPSERT** con ON DUPLICATE KEY UPDATE.

### chc_p_cuadrilla_capacitacion (1 a 5 filas por cuadrilla)
| Columna | Origen | Restricciones |
|---------|--------|--------------|
| idcuadrilla | FK | Cascade delete |
| orden | 1 a 5 | Orden=1 es obligatorio y no eliminable. 2-5 opcionales |
| modalidad | SELECT | 'Presencial' o 'Virtual'. Fila 1 obligatoria |
| fecha | DATE input | Fecha tentativa de capacitacion. Fila 1 obligatoria |
| jornada | SELECT | 'AM', 'PM', 'Todo el dia'. Opcional |

**UNIQUE(idcuadrilla, orden)** - Un orden por cuadrilla.
**Solo visible si** subtipo.tiene_pacientes=1 (subtipos 1, 5, 7, 8).

### chc_p_cuadrilla_debriefing (0 o 1 fila por cuadrilla)
| Columna | Origen | Restricciones |
|---------|--------|--------------|
| idcuadrilla | FK UNIQUE | Cascade delete. Una fila max |
| implementacion_briefing | TEXTAREA | Texto libre |
| implementacion_debriefing | TEXTAREA | Texto libre |

**UNIQUE(idcuadrilla)** - Una fila por cuadrilla.
**Solo visible si** subtipo.tiene_debriefing=1 Y solicitud.uso_debriefing=1.

### chc_p_cuadrilla_subtipo (tabla parametrica, no se modifica por codigo)
| Columna | Tipo | Descripcion |
|---------|------|-------------|
| idsubtipo | PK | Identifica la variante de modalidad |
| idmodalidad | FK ref | 1=Presencial, 2=Virtual (asumido), 3=Exterior CHC (asumido) |
| nombre | VARCHAR | Nombre legible del subtipo |
| tiene_seccion3 | FLAG | Habilita Paso 3 (Estaciones) |
| tiene_pacientes | FLAG | Habilita columna Nro Pac. en fechas + seccion Capacitaciones |
| tiene_insumos | FLAG | Habilita seccion Insumos |
| tiene_debriefing | FLAG | Habilita seccion Debriefing (con doble condicion) |
| tiene_link | FLAG | Habilita columna Link en fechas |
| tiene_ubicacion | FLAG | Habilita columna Ubicacion en fechas |
| activo | FLAG | 1=disponible en SELECT, 0=oculto |

---

## Flujo de Datos: Creacion de Cuadrilla

```
1. PEC abre formulario (irACuadrilla)
   LECTURA: chc_solicitud, chc_solicitud_actividad, planclases_test,
            chc_solicitud_modalidad, chc_modalidad, chc_p_cuadrilla_subtipo
   Si idcuadrilla>0: tambien chc_p_cuadrilla + hijas

2. PEC selecciona subtipo y escribe resumen
   AJAX: guardar_seccion1
   ESCRITURA: INSERT chc_p_cuadrilla (nueva) o UPDATE (existente)
              UPDATE chc_solicitud.idestadocuadrilla = 1
   RETORNO: { success, idcuadrilla }

3. PEC completa tabla de fechas (campo por campo)
   AJAX: guardar_fecha (por cada fila)
   ESCRITURA: INSERT ... ON DUPLICATE KEY UPDATE chc_p_cuadrilla_fecha
              UPDATE chc_p_cuadrilla.fecha_modificacion

4. PEC agrega capacitaciones (si aplica)
   AJAX: guardar_capacitacion (por cada fila)
   ESCRITURA: INSERT ... ON DUPLICATE KEY UPDATE chc_p_cuadrilla_capacitacion

5. PEC escribe insumos (si aplica)
   AJAX: guardar_insumos
   ESCRITURA: UPDATE chc_p_cuadrilla.insumos

6. PEC escribe debriefing (si aplica)
   AJAX: guardar_debriefing
   ESCRITURA: INSERT ... ON DUPLICATE KEY UPDATE chc_p_cuadrilla_debriefing

7. PEC navega a verificacion (irAVerificarCuadrilla)
   LECTURA: Todas las tablas chc_p_* para renderizar resumen

8. PEC edita si necesario (por secciones)
   AJAX: editar_seccion1 / editar_fechas / editar_capacitaciones / etc.
   ESCRITURA: UPDATE/DELETE con logica de cascada si cambia subtipo

9. PEC envia cuadrilla
   AJAX: enviar_cuadrilla
   ESCRITURA: UPDATE estado=3, fecha_envio=NOW()
              UPDATE chc_solicitud.idestadocuadrilla = 3
   GENERA: PDF via TCPDF
   ENVIA: Correo HTML con PDF adjunto a admins (admin=2)
```

---

## Consultas SQL Clave

### Query principal de chc_index.php (lista de solicitudes)
```sql
SELECT s.*, cq.idcuadrilla, cq.estado as estado_cuadrilla
FROM chc_solicitud s
LEFT JOIN chc_p_cuadrilla cq ON s.idsolicitud = cq.idsolicitud
WHERE s.idcurso = $idCurso
GROUP BY s.idsolicitud
ORDER BY s.fecha_registro DESC
```

### Query de creacion (cargar datos para el formulario)
```sql
-- Solicitud confirmada
SELECT s.*, m.modalidad, m.idmodalidad
FROM chc_solicitud s
LEFT JOIN chc_solicitud_modalidad sm ON s.idsolicitud = sm.idsolicitud
LEFT JOIN chc_modalidad m ON sm.idmodalidad = m.idmodalidad
WHERE s.idsolicitud = $id AND s.idestadoagenda = 2

-- Subtipos para el SELECT
SELECT * FROM chc_p_cuadrilla_subtipo
WHERE idmodalidad = $idModalidad AND activo = 1

-- Actividades para la tabla de fechas
SELECT p.idplanclases, p.pcl_Fecha, p.pcl_Inicio, p.pcl_Termino
FROM chc_solicitud_actividad sa
INNER JOIN planclases_test p ON sa.idplanclases = p.idplanclases
WHERE sa.idsolicitud = $id
ORDER BY p.pcl_Fecha ASC
```

### Validacion antes de enviar
```sql
SELECT COUNT(*) AS total,
       SUM(CASE WHEN hora_inicio IS NOT NULL
                 AND hora_termino IS NOT NULL THEN 1 ELSE 0 END) AS completas
FROM chc_p_cuadrilla_fecha
WHERE idcuadrilla = $id
-- total debe ser > 0
-- completas debe ser = total
```
