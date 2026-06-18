# SDD - Software Design Document
# Modulo Cuadrilla CHC
**Version:** 1.1 | **Fecha:** Junio 2026
**Sistema:** Gestion de Agenda CHC - Facultad de Medicina - Universidad de Chile

---

## 1. Introduccion

### 1.1 Proposito
Este documento especifica el diseno tecnico del modulo de cuadrilla del sistema CHC.
La cuadrilla es un formulario estructurado que reemplaza la subida de PDF, permitiendo
a los profesores (PEC) declarar la planificacion detallada de actividades clinicas.

### 1.2 Alcance
El modulo cubre:
- Creacion de cuadrilla via formulario wizard de 3 pasos
- Guardado sincronico campo por campo via AJAX
- Verificacion con edicion inline por secciones
- Envio con generacion de PDF y notificacion por correo
- Logica de cascada al cambiar subtipo de actividad

### 1.3 Stack Tecnologico
| Componente | Tecnologia | Version |
|------------|-----------|---------|
| Servidor | PHP | 5.6 (OBLIGATORIO) |
| Base de datos | MySQL | 5.7.44 |
| Frontend | JavaScript Vanilla | ES5+ |
| UI Framework | Bootstrap | 5.3.2 |
| Notificaciones UI | SweetAlert2 | 11.x |
| PDF | TCPDF | Ultimo estable |
| Email | PHPMailer | 5.x (Autoload) |

---

## 2. Modelo de Datos

### 2.1 Diagrama Entidad-Relacion

```
chc_solicitud (PROD)          chc_p_cuadrilla_subtipo (PARAM)
  |idsolicitud                  |idsubtipo
  |idestadocuadrilla (1,2,3)    |idmodalidad --> chc_modalidad
  |                             |nombre
  |                             |tiene_pacientes (0/1)
  v                             |tiene_insumos (0/1)
chc_p_cuadrilla                 |tiene_debriefing (0/1)
  |idcuadrilla (PK)             |tiene_link (0/1)
  |idsolicitud (FK, UNIQUE)     |tiene_ubicacion (0/1)
  |idsubtipo (FK) ------------->|tiene_seccion3 (0/1)
  |resumen_actividad            |activo (0/1)
  |insumos
  |estado (1=creacion, 3=enviada)
  |rut_pec
  |fecha_creacion
  |fecha_modificacion
  |fecha_envio
  |
  +---> chc_p_cuadrilla_fecha
  |       |idcuadrilla (FK)
  |       |idplanclases (FK ref) --> planclases_test (MyISAM)
  |       |hora_inicio (TIME)
  |       |hora_termino (TIME)
  |       |nro_pacientes (TINYINT, si tiene_pacientes=1)
  |       |link_actividad (VARCHAR, si tiene_link=1)
  |       |ubicacion (TEXT, si tiene_ubicacion=1)
  |       UNIQUE(idcuadrilla, idplanclases)
  |
  +---> chc_p_cuadrilla_capacitacion
  |       |idcuadrilla (FK, CASCADE)
  |       |orden (1-5, TINYINT)
  |       |modalidad ('Presencial'/'Virtual')
  |       |fecha (DATE)
  |       |jornada ('AM'/'PM'/'Todo el dia')
  |       UNIQUE(idcuadrilla, orden)
  |
  +---> chc_p_cuadrilla_debriefing
          |idcuadrilla (FK, CASCADE, UNIQUE)
          |implementacion_briefing (TEXT)
          |implementacion_debriefing (TEXT)
```

### 2.2 Tabla: chc_p_cuadrilla
```sql
CREATE TABLE chc_p_cuadrilla (
    idcuadrilla      INT(11) NOT NULL AUTO_INCREMENT,
    idsolicitud      INT(11) NOT NULL,
    idsubtipo        INT(11) NOT NULL,
    resumen_actividad TEXT DEFAULT NULL,
    insumos          TEXT DEFAULT NULL,
    estado           TINYINT(1) DEFAULT 1,
    rut_pec          VARCHAR(15) NOT NULL,
    fecha_creacion   DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME DEFAULT NULL,
    fecha_envio      DATETIME DEFAULT NULL,
    PRIMARY KEY (idcuadrilla),
    UNIQUE KEY unique_p_cuadrilla_solicitud (idsolicitud),
    CONSTRAINT fk_p_cuadrilla_solicitud
        FOREIGN KEY (idsolicitud) REFERENCES chc_solicitud(idsolicitud) ON DELETE CASCADE,
    CONSTRAINT fk_p_cuadrilla_subtipo
        FOREIGN KEY (idsubtipo) REFERENCES chc_p_cuadrilla_subtipo(idsubtipo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 2.3 Tabla: chc_p_cuadrilla_fecha
```sql
CREATE TABLE chc_p_cuadrilla_fecha (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    idcuadrilla     INT(11) NOT NULL,
    idplanclases    BIGINT(20) NOT NULL,
    hora_inicio     TIME DEFAULT NULL,
    hora_termino    TIME DEFAULT NULL,
    nro_pacientes   TINYINT(3) DEFAULT NULL,
    link_actividad  VARCHAR(500) DEFAULT NULL,
    ubicacion       TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_p_fecha_planclase (idcuadrilla, idplanclases),
    CONSTRAINT fk_p_cf_cuadrilla
        FOREIGN KEY (idcuadrilla) REFERENCES chc_p_cuadrilla(idcuadrilla) ON DELETE CASCADE
    -- Sin FK a planclases_test porque es MyISAM
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 2.4 Tabla: chc_p_cuadrilla_capacitacion
```sql
CREATE TABLE chc_p_cuadrilla_capacitacion (
    id           INT(11) NOT NULL AUTO_INCREMENT,
    idcuadrilla  INT(11) NOT NULL,
    orden        TINYINT(1) NOT NULL,
    modalidad    VARCHAR(20) DEFAULT NULL,
    fecha        DATE DEFAULT NULL,
    jornada      VARCHAR(20) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_p_cap_orden (idcuadrilla, orden),
    CONSTRAINT fk_p_ccap_cuadrilla
        FOREIGN KEY (idcuadrilla) REFERENCES chc_p_cuadrilla(idcuadrilla) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 2.5 Tabla: chc_p_cuadrilla_debriefing
```sql
CREATE TABLE chc_p_cuadrilla_debriefing (
    id                         INT(11) NOT NULL AUTO_INCREMENT,
    idcuadrilla                INT(11) NOT NULL,
    implementacion_briefing    TEXT DEFAULT NULL,
    implementacion_debriefing  TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_p_debriefing_cuadrilla (idcuadrilla),
    CONSTRAINT fk_p_cdb_cuadrilla
        FOREIGN KEY (idcuadrilla) REFERENCES chc_p_cuadrilla(idcuadrilla) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 2.6 Tabla: chc_p_cuadrilla_subtipo (parametrica, 9 registros fijos)
```sql
CREATE TABLE chc_p_cuadrilla_subtipo (
    idsubtipo        INT(11) NOT NULL AUTO_INCREMENT,
    idmodalidad      INT(11) NOT NULL,
    nombre           VARCHAR(200) NOT NULL,
    tiene_seccion3   TINYINT(1) DEFAULT 1,
    tiene_pacientes  TINYINT(1) DEFAULT 0,
    tiene_insumos    TINYINT(1) DEFAULT 0,
    tiene_debriefing TINYINT(1) DEFAULT 1,
    tiene_link       TINYINT(1) DEFAULT 0,
    tiene_ubicacion  TINYINT(1) DEFAULT 0,
    observaciones    TEXT DEFAULT NULL,
    activo           TINYINT(1) DEFAULT 1,
    PRIMARY KEY (idsubtipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

**Datos de la tabla subtipo:**

| id | Modalidad | Nombre | sec3 | pac | ins | deb | link | ubic |
|----|-----------|--------|------|-----|-----|-----|------|------|
| 1 | Presencial | Con pacientes simulados | 1 | 1 | 0 | 1 | 0 | 0 |
| 2 | Presencial | Roleplay sin PS | 1 | 0 | 0 | 1 | 0 | 0 |
| 3 | Presencial | Procedimental | 1 | 0 | 0 | 1 | 0 | 0 |
| 4 | Presencial | Sim. alta fidelidad sin PS | 1 | 0 | 0 | 1 | 0 | 0 |
| 5 | Presencial | Sim. alta fidelidad con PS | 1 | 1 | 0 | 1 | 0 | 0 |
| 6 | Presencial | Sim. realidad virtual | 1 | 0 | 0 | 1 | 0 | 0 |
| 7 | Virtual | Sim. con PS por videoconf. | 0 | 1 | 0 | 0 | 1 | 0 |
| 8 | Exterior CHC | Con pacientes simulados | 0 | 1 | 0 | 0 | 0 | 1 |
| 9 | Exterior CHC | Prestamo de insumos | 1 | 0 | 1 | 0 | 0 | 0 |

**Interpretacion de flags:**
- `tiene_pacientes=1`: Muestra columna Nro Pacientes en tabla fechas + seccion Capacitaciones PS
- `tiene_insumos=1`: Muestra seccion Insumos y Equipamiento
- `tiene_debriefing=1`: Muestra seccion Debriefing (SOLO si ademas `chc_solicitud.uso_debriefing=1`)
- `tiene_link=1`: Muestra columna Link en tabla fechas (subtipo 7 = Virtual)
- `tiene_ubicacion=1`: Muestra columna Ubicacion en tabla fechas (subtipo 8 = Exterior)
- `tiene_seccion3=1`: Habilita Paso 3 Estaciones (PENDIENTE implementacion)

---

## 3. Arquitectura de Componentes

### 3.1 Flujo de Navegacion SPA

```
index_clinico.php
  |
  +-- carga estatica: chc.js (funciones globales)
  |
  +-- div#chc-list (contenedor dinamico)
       |
       +-- fetch('chc_index.php') --> HTML con lista de solicitudes
       |     |
       |     +-- onclick="irACuadrilla(id,0)" --> fetch('chc_p_cuadrilla_crear.php')
       |     +-- onclick="irACuadrilla(id,X)" --> fetch('chc_p_cuadrilla_crear.php?cuadrilla=X')
       |     +-- onclick="irAVerificarCuadrilla(X)" --> fetch('chc_p_cuadrilla_verificar.php')
       |
       +-- chc_p_cuadrilla_crear.php (inyectado en #chc-list)
       |     +-- AJAX POST --> chc_p_cuadrilla_guardar.php (6 acciones)
       |     +-- onclick="irAVerificacion()" --> irAVerificarCuadrilla(id)
       |
       +-- chc_p_cuadrilla_verificar.php (inyectado en #chc-list)
             +-- AJAX POST --> chc_p_cuadrilla_editar.php (5 acciones)
             +-- AJAX POST --> chc_p_cuadrilla_enviar.php (envio final)
```

### 3.2 chc.js - Seccion 13 (Cuadrilla)

```javascript
function irACuadrilla(idsolicitud, idcuadrilla)
// Carga crear.php en #chc-list via fetch. Si idcuadrilla>0 agrega parametro.

function irAVerificarCuadrilla(idcuadrilla)
// Carga verificar.php en #chc-list via fetch.

function ejecutarScriptsHTML(contenedor)
// Recrea <script> tags para que se ejecuten tras innerHTML.
```

### 3.3 API Backend: chc_p_cuadrilla_guardar.php

| Accion | Parametros POST | Operacion BD |
|--------|----------------|--------------|
| `guardar_seccion1` | idsolicitud, idsubtipo, resumen, idcuadrilla(0=nuevo) | INSERT/UPDATE chc_p_cuadrilla |
| `guardar_fecha` | idcuadrilla, idplanclases, hora_inicio, hora_termino, nro_pacientes, link_actividad, ubicacion | UPSERT chc_p_cuadrilla_fecha |
| `guardar_capacitacion` | idcuadrilla, orden(1-5), modalidad, fecha, jornada | UPSERT chc_p_cuadrilla_capacitacion |
| `eliminar_capacitacion` | idcuadrilla, orden(>=2) | DELETE chc_p_cuadrilla_capacitacion |
| `guardar_insumos` | idcuadrilla, insumos | UPDATE chc_p_cuadrilla.insumos |
| `guardar_debriefing` | idcuadrilla, briefing, debriefing | UPSERT chc_p_cuadrilla_debriefing |

**Todas las respuestas:** `{ success: true/false, message: "...", [idcuadrilla: N] }`

### 3.4 API Backend: chc_p_cuadrilla_editar.php

| Accion | Parametros POST | Operacion BD |
|--------|----------------|--------------|
| `editar_seccion1` | idcuadrilla, idsubtipo, resumen | UPDATE + cascada si subtipo cambia |
| `editar_fechas` | idcuadrilla, filas(JSON array) | UPSERT todas las fechas en transaccion |
| `editar_capacitaciones` | idcuadrilla, filas(JSON array) | DELETE all + INSERT en transaccion |
| `editar_insumos` | idcuadrilla, insumos | UPDATE chc_p_cuadrilla.insumos |
| `editar_debriefing` | idcuadrilla, briefing, debriefing | UPSERT chc_p_cuadrilla_debriefing |

**Respuesta editar_seccion1 incluye:** `requiere_recarga: true/false`

### 3.5 Logica de Cascada (editar_seccion1)

Cuando el PEC cambia el subtipo en la pagina de verificacion, los datos incompatibles se eliminan:

| Flag del nuevo subtipo | Accion en BD |
|----------------------|-------------|
| `tiene_pacientes=0` | SET nro_pacientes=NULL en fechas + DELETE ALL capacitaciones |
| `tiene_link=0` | SET link_actividad=NULL en fechas |
| `tiene_ubicacion=0` | SET ubicacion=NULL en fechas |
| `tiene_debriefing=0` | DELETE FROM chc_p_cuadrilla_debriefing |
| `tiene_insumos=0` | SET insumos=NULL en chc_p_cuadrilla |

Se ejecuta dentro de una transaccion (BEGIN/COMMIT/ROLLBACK).

---

## 4. Formulario Wizard (crear.php)

### 4.1 Paso 1 - Descripcion General
| Campo | Tipo | Requerido | Notas |
|-------|------|-----------|-------|
| Subtipo de modalidad | SELECT | Si | Filtra por idmodalidad de la solicitud. Cambia flags dinamicamente |
| Resumen de la actividad | TEXTAREA | Si | Texto libre |

**Auto-guardado:** Al hacer blur en el textarea o cambiar el select, se dispara `guardarSeccion1()`.

### 4.2 Paso 2 - Logistica y Programacion

#### 4.2A - Tabla de Fechas Programadas
Las filas vienen de `chc_solicitud_actividad JOIN planclases_test`. No se pueden agregar/quitar filas.

| Columna | Tipo | Visible si | Notas |
|---------|------|-----------|-------|
| Fecha | Texto | Siempre | Read-only, viene de planclases_test.pcl_Fecha |
| Bloque agendado | Texto | Siempre | Read-only, pcl_Inicio - pcl_Termino |
| Hora inicio | SELECT (15min) | Siempre | Opciones desde pcl_Inicio hasta pcl_Termino-15min |
| Hora termino | SELECT (15min) | Siempre | Opciones desde pcl_Inicio+15min hasta pcl_Termino |
| Nro pacientes | SELECT (1-N) | tiene_pacientes=1 | Max = chc_solicitud.npacientes |
| Link actividad | INPUT URL | tiene_link=1 | Subtipo 7 (Virtual) |
| Ubicacion | TEXTAREA | tiene_ubicacion=1 | Subtipo 8 (Exterior CHC) |

**Auto-guardado:** Cada fila se guarda al hacer blur en cualquier campo.

#### 4.2B - Capacitacion Paciente Simulado
Visible solo si `tiene_pacientes=1`.

| Campo | Tipo | Notas |
|-------|------|-------|
| Modalidad | SELECT | Presencial / Virtual |
| Fecha | DATE | Fecha tentativa |
| Jornada | SELECT | AM / PM / Todo el dia |

- Minimo 1 fila OBLIGATORIA, maximo 5
- Fila 1 siempre visible, sin boton eliminar
- Filas 2-5 se agregan con boton "Agregar fecha"
- Validacion: fila 1 debe tener modalidad y fecha

#### 4.2C - Insumos y Equipamiento
Visible solo si `tiene_insumos=1` (subtipo 9).

| Campo | Tipo | Notas |
|-------|------|-------|
| Listado de insumos | TEXTAREA | Texto libre |

#### 4.2D - Induccion y Retroalimentacion (Debriefing)
Visible solo si `tiene_debriefing=1` Y `chc_solicitud.uso_debriefing=1`.

| Campo | Tipo | Notas |
|-------|------|-------|
| Induccion (briefing) | TEXTAREA | Como se implementara el briefing |
| Retroalimentacion (debriefing) | TEXTAREA | Como se implementara el debriefing |

### 4.3 Paso 3 - Estaciones
**ESTADO: STAND BY** - Pendiente de definicion con el cliente.

Se habilita solo si `tiene_seccion3=1` para el subtipo seleccionado.
Actualmente muestra placeholder: "Esta seccion esta en proceso de definicion."

---

## 5. Verificacion y Envio

### 5.1 Pagina de Verificacion (verificar.php)
- Muestra todas las secciones en modo lectura
- Cada seccion tiene boton "Editar seccion" (si estado < 3)
- Al editar, se muestra formulario inline con "Guardar seccion" y "Cancelar"
- Cancelar revierte visualmente sin tocar la BD
- Los cambios se envian a chc_p_cuadrilla_editar.php

### 5.2 Envio (enviar.php)
**Validacion antes de enviar:**
1. Cuadrilla existe y pertenece al PEC logueado
2. Estado != 3 (no enviada previamente)
3. Al menos una fecha registrada
4. TODAS las fechas tienen hora_inicio Y hora_termino

**Proceso de envio:**
1. UPDATE chc_p_cuadrilla.estado = 3, fecha_envio = NOW()
2. UPDATE chc_solicitud.idestadocuadrilla = 3
3. Genera PDF via TCPDF
4. Envia correo HTML a todos los usuarios con admin=2
5. El correo/PDF NO bloquea el envio si falla (se loguea el error)

### 5.3 Configuracion SMTP
```
Host: mail.dpi.med.uchile.cl
Port: 465 (SSL)
From: _mainaccount@dpi.med.uchile.cl
```

---

## 6. Reglas de Negocio

1. Solo se puede crear cuadrilla si la solicitud tiene `idestadoagenda=2` (confirmada)
2. Una solicitud tiene como maximo UNA cuadrilla (UNIQUE constraint)
3. La tabla `chc_solicitud` es de produccion. Solo se modifica el campo `idestadocuadrilla`
4. `planclases_test` es MyISAM - no se pueden crear FK formales hacia ella
5. Los correos se envian a usuarios con `admin=2` en `chc_usuario` (no admin=1)
6. Las horas se seleccionan en intervalos de 15 minutos
7. Nro de pacientes maximo = `chc_solicitud.npacientes`
8. La primera capacitacion es obligatoria y no se puede eliminar
9. Debriefing visible SOLO cuando AMBAS condiciones: `subtipo.tiene_debriefing=1` Y `solicitud.uso_debriefing=1`
10. Post-envio (estado=3) es solo lectura. No hay flujo de edicion post-envio aun

---

## 7. Seguridad

- Verificacion de sesion activa en cada archivo PHP
- Verificacion de propiedad (rut_pec) antes de cualquier escritura
- `intval()` para parametros enteros en SQL
- `mysqli_real_escape_string()` para strings en SQL
- Verificacion de estado editable (estado != 3) antes de permitir ediciones
- Validacion de rango de orden (1-5) para capacitaciones
- Credenciales SMTP hardcodeadas (patron existente en el sistema)
