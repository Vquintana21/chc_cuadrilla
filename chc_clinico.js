/**
 * CHC - Validaciones para Actividades Clínicas
 * 
 * Este archivo contiene las funciones de validación para determinar
 * si una actividad clínica puede ser editada o eliminada según las
 * reglas de negocio del sistema CHC.
 * 
 * Reglas:
 * 1. Actividad SIN agenda CHC → Puede editarse y eliminarse
 * 2. Actividad CON agenda CHC → NO puede eliminarse (nunca)
 * 3. Actividad CON agenda CHC + período vigente → Puede editarse
 * 4. Actividad CON agenda CHC + período vencido + agenda activa → NO puede editarse
 * 5. Actividad CON agenda CHC + período vencido + agenda cancelada → Puede editarse
 */

// =====================================================
// CACHE PARA EVITAR CONSULTAS REPETIDAS
// =====================================================
const chcClinicoCacheVerificacion = new Map();
const CHC_CACHE_DURACION = 30000; // 30 segundos

/**
 * Limpia el caché de verificaciones
 */
function chcClinicoClearCache() {
    chcClinicoCacheVerificacion.clear();
    console.log('🗑️ CHC Clínico: Caché limpiado');
}

/**
 * Obtiene datos del caché si aún son válidos
 */
function chcClinicoGetFromCache(idPlanClase) {
    const cached = chcClinicoCacheVerificacion.get(idPlanClase);
    if (cached && (Date.now() - cached.timestamp < CHC_CACHE_DURACION)) {
        return cached.data;
    }
    return null;
}

/**
 * Guarda datos en el caché
 */
function chcClinicoSetCache(idPlanClase, data) {
    chcClinicoCacheVerificacion.set(idPlanClase, {
        data: data,
        timestamp: Date.now()
    });
}

// =====================================================
// FUNCIÓN PRINCIPAL DE VERIFICACIÓN
// =====================================================

/**
 * Verifica el estado CHC de una actividad clínica
 * @param {number} idPlanClase - ID de la actividad
 * @returns {Promise<object>} - Resultado de la verificación
 */
async function chcClinicoVerificarActividad(idPlanClase) {
    // Verificar caché primero
    const cached = chcClinicoGetFromCache(idPlanClase);
    if (cached) {
        console.log('📦 CHC Clínico: Usando datos en caché para actividad', idPlanClase);
        return cached;
    }
    
    try {
        const response = await fetch('chc_verificar_actividad_clinica.php?id=' + idPlanClase);
        
        if (!response.ok) {
            throw new Error('Error de conexión: ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Guardar en caché
            chcClinicoSetCache(idPlanClase, data);
        }
        
        return data;
        
    } catch (error) {
        console.error('❌ CHC Clínico: Error verificando actividad:', error);
        return {
            success: false,
            error: error.message,
            puede_editar: true, // En caso de error, permitir por defecto
            puede_eliminar: true
        };
    }
}

// =====================================================
// FUNCIONES DE EDICIÓN
// =====================================================

/**
 * Wrapper para editActivity que verifica permisos CHC primero
 * @param {number} idplanclases - ID de la actividad a editar
 */
async function chcClinicoEditActivity(idplanclases) {
    console.log('🔍 CHC Clínico: Verificando permisos para editar actividad', idplanclases);
    
    // Mostrar indicador de carga
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Verificando...',
            html: '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Verificando permisos de edición...</p></div>',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false
        });
    }
    
    try {
        const verificacion = await chcClinicoVerificarActividad(idplanclases);
        
        // Cerrar loading
        if (typeof Swal !== 'undefined') {
            Swal.close();
        }
        
        if (!verificacion.success) {
            console.error('❌ Error en verificación:', verificacion.error);
            // En caso de error, permitir edición pero notificar
            if (typeof editActivity === 'function') {
                editActivity(idplanclases);
            }
            return;
        }
        
        // Si NO tiene agenda CHC o SÍ puede editar → Proceder
        if (!verificacion.tiene_agenda_chc || verificacion.puede_editar) {
            console.log('✅ CHC Clínico: Edición permitida');
            if (typeof editActivity === 'function') {
                editActivity(idplanclases);
            }
            return;
        }
        
        // NO puede editar → Mostrar mensaje
        console.log('🚫 CHC Clínico: Edición bloqueada');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Edición no permitida',
                html: `
                    <div class="text-start">
                        <p>${verificacion.mensaje_editar || 'Esta actividad no puede ser editada.'}</p>
                        ${verificacion.solicitud_chc ? `
                            <div class="alert alert-info mt-3 mb-0">
                                <small>
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Solicitud CHC:</strong> #${verificacion.solicitud_chc.id}<br>
                                    <strong>Estado:</strong> ${verificacion.solicitud_chc.estado_nombre}<br>
                                    <strong>Curso:</strong> ${verificacion.solicitud_chc.curso}
                                </small>
                            </div>
                        ` : ''}
                    </div>
                `,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#6c757d'
            });
        } else {
            alert(verificacion.mensaje_editar || 'Esta actividad no puede ser editada.');
        }
        
    } catch (error) {
        console.error('❌ CHC Clínico: Error inesperado:', error);
        
        // Cerrar loading si existe
        if (typeof Swal !== 'undefined') {
            Swal.close();
        }
        
        // En caso de error inesperado, permitir edición
        if (typeof editActivity === 'function') {
            editActivity(idplanclases);
        }
    }
}

// =====================================================
// FUNCIONES DE ELIMINACIÓN
// =====================================================

/**
 * Wrapper para deleteActivity que verifica permisos CHC primero
 * @param {number} idplanclases - ID de la actividad a eliminar
 */
async function chcClinicoDeleteActivity(idplanclases) {
    console.log('🔍 CHC Clínico: Verificando permisos para eliminar actividad', idplanclases);
    
    // Mostrar indicador de carga
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Verificando...',
            html: '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Verificando permisos de eliminación...</p></div>',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false
        });
    }
    
    try {
        const verificacion = await chcClinicoVerificarActividad(idplanclases);
        
        // Cerrar loading
        if (typeof Swal !== 'undefined') {
            Swal.close();
        }
        
        if (!verificacion.success) {
            console.error('❌ Error en verificación:', verificacion.error);
            // En caso de error, permitir eliminación pero notificar
            if (typeof deleteActivity === 'function') {
                deleteActivity(idplanclases);
            }
            return;
        }
        
        // Si NO tiene agenda CHC → Puede eliminar
        //if (!verificacion.tiene_agenda_chc) {
        //    console.log('✅ CHC Clínico: Eliminación permitida (sin agenda CHC)');
        //    if (typeof deleteActivity === 'function') {
        //        deleteActivity(idplanclases);
        //    }
        //    return;
        //}
		
		// Si NO tiene agenda CHC o SÍ puede eliminar → Proceder
		if (!verificacion.tiene_agenda_chc || verificacion.puede_eliminar) {
			console.log('✅ CHC Clínico: Eliminación permitida');
			if (typeof deleteActivity === 'function') {
				deleteActivity(idplanclases);
			}
			return;
		}

		// NO puede eliminar → Mostrar mensaje
		console.log('🚫 CHC Clínico: Eliminación bloqueada');
        
        // TIENE agenda CHC → NUNCA puede eliminar
        console.log('🚫 CHC Clínico: Eliminación bloqueada (tiene agenda CHC)');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Eliminación no permitida',
                html: `
                    <div class="text-start">
                        <p><strong>${verificacion.mensaje_eliminar || 'Esta actividad no puede ser eliminada.'}</strong></p>
                        <p class="text-muted">Las actividades vinculadas a solicitudes CHC no pueden eliminarse para mantener la integridad de los registros.</p>
                        ${verificacion.solicitud_chc ? `
                            <div class="alert alert-warning mt-3 mb-0">
                                <small>
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <strong>Solicitud CHC:</strong> #${verificacion.solicitud_chc.id}<br>
                                    <strong>Estado:</strong> ${verificacion.solicitud_chc.estado_nombre}<br>
                                    <strong>Curso:</strong> ${verificacion.solicitud_chc.curso}
                                </small>
                            </div>
                        ` : ''}
                    </div>
                `,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#dc3545'
            });
        } else {
            alert(verificacion.mensaje_eliminar || 'Esta actividad no puede ser eliminada porque tiene una solicitud CHC asociada.');
        }
        
    } catch (error) {
        console.error('❌ CHC Clínico: Error inesperado:', error);
        
        // Cerrar loading si existe
        if (typeof Swal !== 'undefined') {
            Swal.close();
        }
        
        // En caso de error inesperado, permitir eliminación
        if (typeof deleteActivity === 'function') {
            deleteActivity(idplanclases);
        }
    }
}

// =====================================================
// FUNCIÓN PARA ACTUALIZAR BOTONES EN LA TABLA
// =====================================================

/**
 * Actualiza el estado visual de los botones de una actividad
 * según sus permisos CHC (útil al cargar la página)
 * @param {number} idPlanClase - ID de la actividad
 */
async function chcClinicoActualizarBotones(idPlanClase) {
    try {
        const verificacion = await chcClinicoVerificarActividad(idPlanClase);
        
        if (!verificacion.success || !verificacion.tiene_agenda_chc) {
            return; // No hacer nada si no tiene agenda CHC
        }
        
        // Buscar la fila de la actividad
        const filas = document.querySelectorAll('table tbody tr');
        
        filas.forEach(fila => {
            const botonEditar = fila.querySelector(`button[onclick*="chcClinicoEditActivity(${idPlanClase})"], button[onclick*="editActivity(${idPlanClase})"]`);
            const botonEliminar = fila.querySelector(`button[onclick*="chcClinicoDeleteActivity(${idPlanClase})"], button[onclick*="deleteActivity(${idPlanClase})"]`);
            
            if (botonEditar || botonEliminar) {
                // Agregar indicador visual de agenda CHC
                const tdAcciones = fila.querySelector('td:last-child');
                if (tdAcciones && !tdAcciones.querySelector('.badge-chc')) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-success badge-chc ms-1';
                    badge.innerHTML = '<i class="bi bi-calendar-check"></i> CHC';
                    badge.title = 'Esta actividad tiene solicitud CHC';
                    tdAcciones.insertBefore(badge, tdAcciones.firstChild);
                }
                
                // Actualizar estado del botón eliminar
                if (botonEliminar) {
                    botonEliminar.classList.remove('btn-danger');
                    botonEliminar.classList.add('btn-secondary');
                    botonEliminar.title = 'No se puede eliminar: tiene solicitud CHC';
                }
                
                // Actualizar estado del botón editar si no puede editarse
                if (botonEditar && !verificacion.puede_editar) {
                    botonEditar.classList.remove('btn-primary');
                    botonEditar.classList.add('btn-secondary');
                    botonEditar.title = verificacion.mensaje_editar || 'Edición no permitida';
                }
            }
        });
        
    } catch (error) {
        console.error('Error actualizando botones:', error);
    }
}

/**
 * Actualiza todos los botones de la tabla al cargar la página
 * Útil para mostrar indicadores visuales de actividades con CHC
 */
async function chcClinicoActualizarTodosLosBotones() {
    console.log('🔄 CHC Clínico: Actualizando indicadores visuales...');
    
    // Obtener todos los IDs de actividades de la tabla
    const botonesEditar = document.querySelectorAll('button[onclick*="EditActivity"]');
    const idsUnicos = new Set();
    
    botonesEditar.forEach(boton => {
        const onclick = boton.getAttribute('onclick') || '';
        const match = onclick.match(/(\d+)/);
        if (match) {
            idsUnicos.add(parseInt(match[1]));
        }
    });
    
    // Verificar cada actividad
    for (const id of idsUnicos) {
        await chcClinicoActualizarBotones(id);
    }
    
    console.log('✅ CHC Clínico: Indicadores actualizados para', idsUnicos.size, 'actividades');
}

// =====================================================
// INICIALIZACIÓN
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ CHC Clínico: Módulo de validaciones cargado');
    
    // Opcional: Actualizar indicadores visuales al cargar
    // Descomentar la siguiente línea si se desea mostrar badges CHC al cargar
    // setTimeout(chcClinicoActualizarTodosLosBotones, 1000);
});

// Exponer funciones globalmente
window.chcClinicoEditActivity = chcClinicoEditActivity;
window.chcClinicoDeleteActivity = chcClinicoDeleteActivity;
window.chcClinicoVerificarActividad = chcClinicoVerificarActividad;
window.chcClinicoClearCache = chcClinicoClearCache;
window.chcClinicoActualizarTodosLosBotones = chcClinicoActualizarTodosLosBotones;