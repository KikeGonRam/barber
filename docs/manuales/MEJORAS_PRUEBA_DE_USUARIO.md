# Mejoras detectadas en la prueba de usuario

Resultado de recorrer la aplicación como **cliente**, **administrador**, **barbero**, **recepcionista** e **ingeniero** el 28 de octubre de 2026, en un entorno de demostración aislado (base de desarrollo, no Atlas) con cuentas ficticias. Las capturas están en `docs/assets/` y los recorridos se describen en el [Manual de Usuario](01-manual-de-usuario/manual-de-usuario.md).

Esta lista es un **punto de partida para el backlog**, no una auditoría exhaustiva: se probaron pantallas y flujos, no cada caso límite. Los hallazgos marcados *por confirmar* se vieron en la interfaz pero no se comprobaron en el código.

## Cómo leer la prioridad

| Prioridad | Criterio |
| --- | --- |
| Alta | Puede confundir, bloquear una tarea o poner en riesgo datos o permisos. |
| Media | Frena o ensucia la experiencia, pero hay una salida. |
| Baja | Detalle de redacción, consistencia o estética. |

## Hallazgos

| # | Rol | Hallazgo | Sugerencia | Prioridad |
| --- | --- | --- | --- | --- |
| 1 | Cliente | Si la reserva falla (por ejemplo, "Solo se permite una cita por día"), el mensaje aparece arriba, fuera de la vista, y la pantalla regresa al paso del horario. | Llevar la vista al mensaje y conservar el paso de confirmar. | Alta |
| 2 | Todos | Al abrir directamente Barberos, Perfil, Muro y Analítica, el menú lateral queda vacío y el avatar muestra "U". Se reprodujo en varias corridas y roles. | Resolver el estado de usuario en la carga directa de esas rutas. | Alta |
| 3 | Cliente | "Recomendado para ti" abre el "Centro de Análisis" con "No hay análisis… para tu rol". | Ocultar la opción o darle contenido para el cliente. | Alta |
| 15 | Administrador | En Inventario, los indicadores "Productos" y "Sin stock" salen vacíos y "Valor inventario" marca $0 aunque hay 4 productos (Reportes muestra $10,800). | Corregir el origen de los indicadores. | Alta |
| 16 | Administrador, recepción | La ficha de cliente dice "hace -4 días" para una cita futura y muestra a la vez "Nuevo" y "Nivel Regular". | Ignorar citas futuras en "última visita" y unificar segmento y nivel. | Alta |
| 28 | Recepción, ingeniero | Al abrir por URL una pantalla sin permiso, el sistema devuelve al panel sin ningún aviso. | Mostrar "No tienes permiso para esta pantalla". | Alta |
| 13 | Administrador | "Eliminar" (clientes) y "Anular" (pagos) quedan junto a "Ver ficha" y "PDF" en cada fila. | Confirmar la acción, pedir motivo y preferir borrado lógico. | Alta |
| 30 | Recepción | La recepción puede anular pagos y exportar un CSV de clientes con datos personales. | Confirmar si es intencional; si no, limitarlo al administrador o exigir motivo. | Alta |
| 14 | Administrador, recepción | En citas confirmadas del futuro solo "Iniciar" se deshabilita; "Completar", "No asistió" y "Cobrar" siguen activos. | Habilitarlas solo cuando corresponda por fecha. | Alta |
| 5 | Cliente | En los diálogos, "Cancelar" solo cierra la ventana y "Sí, cancelar" cancela la cita; el aviso no dice qué cita ni si tiene costo. | Renombrar "Cancelar" como "Volver" y mostrar la cita y la política. | Media |
| 4 | Cliente | El inicio de sesión no recuerda la reserva pendiente (solo la conserva en la dirección). | Mostrar una tarjeta con lo que eligió. | Media |
| 6 | Cliente | En Reagendar, el campo "Hora" abre vacío. | Preseleccionar la hora actual o la primera libre. | Media |
| 7 | Cliente | "Mis Recibos" dice "8 citas con recibo" pero lista 5 filas. | Alinear el conteo con la tabla. | Media |
| 8 | Cliente | "Descuento de membresía (5%)" es en realidad el descuento por nivel de lealtad. | Renombrarlo como "Descuento por nivel". | Media |
| 9 | Cliente, barbero, recepción | En móvil, el botón flotante del asistente tapa contenido. | Moverlo o permitir ocultarlo. | Media |
| 12 | Administrador | El panel muestra "Cargando dashboard…" varios segundos (unos 13 s en la demostración). | Mostrar el esqueleto del panel y medir la carga. | Media |
| 19 | Administrador | En Campañas, "Enviar ahora" no mostró confirmación ni vista previa (*por confirmar*). | Confirmar con el número de destinatarios. | Media |
| 20 | Administrador | El panel y Reportes calculan "ingresos" con definiciones distintas (pagos frente a citas cobradas). | Unificar o rotular la diferencia. | Media |
| 22 | Barbero | En móvil, los 4 contadores de Mi Agenda ocupan una pantalla entera y empujan las citas. | Compactarlos en 2×2. | Media |
| 23 | Barbero | En móvil, la tarjeta de aprobación trunca el cliente y el servicio ("So…", "Bar…"). | Apilar el texto y los botones. | Media |
| 29 | Recepción | En móvil, la tabla de Citas se corta y las acciones quedan fuera de pantalla. | Mostrar tarjetas por cita. | Media |
| 31 | Recepción | La ficha de cliente tardó unos 34 s en una corrida (unos 5 s en otras). | Revisar rendimiento y reintentos. | Media |
| 33 | Ingeniero | En el mapa del sistema las etiquetas se superponen y se truncan, sobre todo en móvil. | Separar etiquetas o mostrar solo las del nodo seleccionado. | Media |
| 34 | Ingeniero | El ingeniero ve ingresos y valor de inventario en el panel y en Reportes. | Confirmar que es intencional (mínimo privilegio). | Media |
| 17 | Administrador, ingeniero | Los logs muestran eventos y categorías en inglés ("created", "appointments", "payments"). | Traducir y mostrar el recurso afectado. | Media |
| 11 | Todos | Aparece "Hydration completed but contains mismatches" en varias pantallas. | Revisar las diferencias entre servidor y cliente. | Media |
| 10 | Cliente | Textos: "1 Servicios disponibles", título "Barbers" en inglés, "Urban Blade" separado en móvil, productos y barberos sin foto, y el aviso de cookies no se vio en la primera visita. | Corregir plurales, títulos y revisar el aviso. | Baja |
| 18 | Administrador | Servicio nuevo: la categoría por defecto es "Cortes" y las sugerencias son "corte", "barba" y "combo". | Usar el mismo valor en ambos. | Baja |
| 21 | Administrador | La columna "Especialidades" de Barberos sale vacía ("—") con los datos de demostración. | Revisar el campo (`especialidad` frente a `especialidades`). | Baja |
| 24 | Barbero | "Terminar servicio" (panel) y "Terminar ahora" (agenda) nombran la misma acción; "Ver mi agenda" aparece dos veces. | Unificar la redacción. | Baja |
| 25 | Barbero | El selector de archivos del portafolio sale en inglés ("Choose Files / No file chosen"). | Usar un botón propio en español. | Baja |
| 26 | Barbero | Mi Horario permite una sola franja por día; no hay forma de marcar un descanso (*por confirmar*). | Permitir dos franjas. | Baja |
| 27 | Barbero | El perfil muestra solo datos de cuenta, y el botón lateral cambia a "Nueva cita" en esa pantalla. | Mostrar especialidad y descripción y mantener el botón. | Baja |
| 32 | Recepción | Analítica muestra "No hay análisis históricos publicados para tu rol". | Ocultar el mensaje cuando hay indicadores del día. | Baja |
| 35 | Ingeniero | En Estado del servidor las tareas programadas aparecen todas "Sin uso", sin última ejecución (*por confirmar*). | Mostrar la última ejecución y su resultado. | Baja |

## Estado al 29 de octubre de 2026

Después de la prueba se corrigieron y desplegaron a staging la mayoría de los hallazgos. Este resumen dice qué pasó con cada número.

| Estado | Hallazgos |
| --- | --- |
| Corregidos y desplegados | 1, 2, 3, 4, 5, 7, 8, 9, 12, 13, 14, 15, 16, 17, 18, 19, 20, 22, 23, 24, 28, 29, 30, 32, 33 |
| Corregidos en parte | 10 (se arregló el plural; quedan el título en inglés, las fotos y el aviso de cookies), 13 (anular pide motivo y queda en la bitácora; el cobro sigue borrándose, no hay anulación lógica) |
| Decididos sin cambio de código | 34 (el panel y Reportes del ingeniero muestran datos agregados por diseño: "consulta agregados y salud"), 21 (era un efecto de los datos de demostración: se sembró `especialidad` y la pantalla lee `especialidades`), 25 (el texto del selector de archivos lo pone el navegador) |
| Por verificar | 6 (el código ya conserva la hora actual al reagendar; falta reproducirlo en un navegador) |
| Pendientes | 11 (avisos de hidratación), 26 (dos franjas por día en el horario del barbero), 27 (perfil público del barbero: especialidad y descripción), 31 (ficha de cliente lenta en una corrida), 35 (tareas programadas "Sin uso": falta aplicar en staging el script del scheduler) |

Decisiones de producto tomadas: anular un cobro y exportar clientes a CSV son solo del administrador; anular exige un motivo.

## Lo que funciona bien

- La reserva se puede empezar sin cuenta y se entiende en cuatro pasos, con un resumen siempre visible y horarios agrupados en mañana, tarde y noche.
- El panel del cliente (próxima cita, nivel, puntos) y el del barbero (aprobar o rechazar, avance del día, "Iniciar" con explicación) comunican qué hacer ahora.
- El panel de recepción responde a "qué conviene atender hoy" y el corte de caja se entiende de un vistazo.
- Los permisos de recepción están acotados: no ve "Eliminar" en clientes ni los segmentos.
- El estado del servidor ofrece resumen, mapa y lista, con actualización automática.

## Límites de esta prueba

- Los datos son de demostración (productos y barberos sin foto, cobros sembrados con la fecha de hoy), así que algunas cifras raras pueden ser efecto de los datos.
- No se probó el pago con tarjeta (la demostración no tiene claves de Stripe) ni el correo real.
- Solo se abrieron los formularios de alta y edición; no se guardaron cambios.
- Las capturas usan el tema *Sastrería Nocturna*.
