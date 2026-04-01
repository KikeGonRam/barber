# Siguiente Sprint Tecnico - 2026-04-01

Objetivo: mover el proyecto de "estable" a "listo para operar en produccion" con foco en observabilidad, chatbot, UX y endurecimiento final.

## Prioridad 1: Observabilidad y alertas

### Meta
Saber cuando algo critico falla o se degrada sin revisar logs manualmente.

### Alcance
- Registrar eventos de negocio: citas creadas, canceladas, completadas, pagadas, stock bajo y errores del chatbot.
- Consolidar logs con contexto util: usuario, rol, ruta, modelo afectado, accion.
- Definir alertas internas para eventos criticos.

### Entregables
- Servicio de logging de negocio.
- Eventos de dominio estandarizados.
- Notificaciones o alertas para stock bajo y fallos relevantes.

### Criterio de cierre
- Un incidente relevante queda trazado desde la accion hasta el log.
- Los casos criticos tienen al menos una alerta visible o notificable.

## Prioridad 2: Chatbot en produccion

### Meta
Hacer el chatbot resistente a fallos externos y controlable en costo.

### Alcance
- Fallback cuando Gemini falle o responda lento.
- Rate limiting por usuario y/o ventana de tiempo.
- Mensajes de error mas humanos.
- Persistencia de contexto mas precisa para evitar ruido en conversaciones largas.

### Entregables
- Estrategia de fallback.
- Limites de uso configurables.
- Respuestas seguras ante error de proveedor.

### Criterio de cierre
- Si el proveedor AI falla, el usuario recibe una respuesta util.
- El sistema no queda expuesto a abuso o costos descontrolados.

## Prioridad 3: UX operativa

### Meta
Hacer mas clara la operacion diaria de recepcion, barbero y admin.

### Alcance
- Dashboard mas accionable.
- Widgets de prioridad: citas de hoy, pendientes, stock critico, pagos pendientes.
- Mejorar pantalla de agenda y notificaciones.
- Reducir dependencias de textos frágiles en vistas.

### Entregables
- Ajustes visuales en dashboard por rol.
- Accesos rapidos a tareas frecuentes.
- Mejoras de microcopy en flujos clave.

### Criterio de cierre
- Cada rol ve su siguiente accion mas importante sin buscarla.

## Prioridad 4: Endurecimiento funcional

### Meta
Cerrar huecos entre rutas, policies, notificaciones y exports.

### Alcance
- Revisar permisos y policies pendientes.
- Verificar coherencia entre rutas y vistas.
- Endurecer exports, PDFs y storage.
- Confirmar rate limiting y protecciones en endpoints sensibles.

### Entregables
- Checklist de permisos por modulo.
- Ajustes finales en rutas/vistas.
- Validaciones para storage y exports.

### Criterio de cierre
- No hay rutas criticas expuestas sin autorizacion correcta.
- Los flujos de PDF/Excel/archivos funcionan de forma predecible.

## Prioridad 5: Mantenibilidad

### Meta
Reducir ruido y deuda para que los siguientes cambios sean mas rapidos.

### Alcance
- Eliminar tests de ejemplo o legacy que no aporten valor.
- Extraer helpers repetidos en tests y factories.
- Unificar criterios de fecha/hora y formato.
- Documentar decisiones de negocio importantes.

### Entregables
- Refactor de helpers comunes.
- Simplificacion de tests redundantes.
- Documentacion tecnica actualizada.

### Criterio de cierre
- Menos duplicacion en pruebas.
- Menos friccion al agregar nuevos casos.

## Orden recomendado de ejecucion

1. Observabilidad y alertas.
2. Chatbot en produccion.
3. UX operativa.
4. Endurecimiento funcional.
5. Mantenibilidad.

## Regla de trabajo

Cada item debe cerrar con:
- prueba automatizada nueva o ajustada,
- documentacion breve del cambio,
- validacion en Docker,
- commit/push del bloque estable.
