# Roadmap - Modulo Cuadrilla CHC

## Estado Actual: v0.9 (MVP funcional con correcciones PHP 5.6)

---

## Fase 1: Estabilizacion (COMPLETADO)
- [x] Modelo de datos (5 tablas chc_p_*)
- [x] Formulario wizard crear.php (Pasos 1 y 2)
- [x] API guardado sincronico (guardar.php - 6 acciones)
- [x] Pagina de verificacion con edicion inline (verificar.php)
- [x] API edicion con cascada (editar.php - 5 acciones)
- [x] Envio con PDF y correo (enviar.php + pdf.php)
- [x] Funciones JS globales en chc.js (Seccion 13)
- [x] Compatibilidad PHP 5.6 (eliminado ??, get_result, array corta)
- [x] Navegacion SPA (fetch + ejecutarScriptsHTML)

## Fase 2: Mejoras UX (PENDIENTE - prioridad media)
- [ ] **location.reload() en verificar.php** - Cambiar por irAVerificarCuadrilla()
      para recargar solo el componente #chc-list sin perder contexto SPA.
      Archivos: chc_p_cuadrilla_verificar.php lineas 631, 661, 705, 767
- [ ] **Validacion visual en crear.php** - Mostrar indicadores de campos faltantes
      antes de permitir avanzar entre pasos
- [ ] **Indicador de progreso** - Badge visual en la lista de solicitudes
      mostrando porcentaje de completitud de la cuadrilla
- [ ] **Boton "Volver a solicitudes"** - En crear.php y verificar.php, agregar
      boton para regresar a la lista (cargar chc_index.php via fetch)

## Fase 3: Seccion 3 - Estaciones (STAND BY - requiere definicion cliente)
- [ ] **Definir tipos de estacion** con el cliente
- [ ] **Modelo de datos** para estaciones (tabla chc_p_cuadrilla_estacion?)
- [ ] **Formulario dinamico** segun tipo de estacion
- [ ] **Circuitos** - Agrupacion logica de estaciones
- [ ] **Inmobiliario** - Listado de mobiliario/equipamiento por estacion
- [ ] Solo aplica cuando `chc_p_cuadrilla_subtipo.tiene_seccion3 = 1`

## Fase 4: Vista Gestor CHC (PENDIENTE - requiere diseno)
- [ ] **Vista de cuadrillas enviadas** para administradores (admin=2)
- [ ] **Filtros** por curso, periodo, modalidad, estado
- [ ] **Descarga de PDF** desde la lista
- [ ] **Dashboard** con metricas (cuadrillas por periodo, pendientes, etc.)
- [ ] Sistema separado o pestana adicional en el modulo CHC

## Fase 5: Edicion Post-Envio (PENDIENTE - requiere analisis)
- [ ] **Flujo de solicitud de correccion** tras envio (estado=3)
- [ ] **Notificacion a CHC** cuando PEC solicita edicion
- [ ] **Estado intermedio** (estado=4? "En correccion")
- [ ] **Bloqueo de gestion** mientras cuadrilla esta en correccion
- [ ] **Re-envio** con nueva notificacion y PDF actualizado

## Fase 6: Produccion (PENDIENTE)
- [ ] **Renombrar tablas** quitando prefijo `_p_` (chc_p_cuadrilla -> chc_cuadrilla)
- [ ] **Migrar planclases_test -> planclases** (tabla real de produccion)
- [ ] **Formato PDF institucional** segun plantilla del cliente
- [ ] **Tests de integracion** con datos reales
- [ ] **Desactivar sistema antiguo** (chc_cuadrilla.php de subida PDF)

---

## Deuda Tecnica

| Item | Prioridad | Detalle |
|------|-----------|---------|
| Credenciales SMTP hardcodeadas | Baja | Mover a archivo de configuracion externo |
| Sin logs estructurados | Baja | Solo usa error_log(), no hay sistema de logging |
| JS inline en PHP | Baja | Los scripts de crear.php y verificar.php son inline, no modularizados |
| Sin validacion de acceso en PDF | Media | Cualquier usuario autenticado puede ver cualquier PDF por ID |
| Sin retry en correo | Baja | Si PHPMailer falla, no hay reintento |

---

## Notas para la proxima sesion

1. Leer `CLAUDE.md` para restricciones PHP 5.6 y arquitectura SPA
2. Leer `docs/SDD_Cuadrilla_CHC.md` para modelo de datos y APIs completas
3. El archivo `chc_p_cuadrilla_guardar.php` es el **patron de referencia** para PHP 5.6
4. Verificar que el PR #1 fue mergeado antes de trabajar sobre main
5. Branch de desarrollo: `claude/crew-upload-form-module-ZrRjA`
