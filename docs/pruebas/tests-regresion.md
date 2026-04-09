# Pruebas de Regresión (Regression Tests)

Estas pruebas aseguran que las funcionalidades core del sistema sigan operativas tras actualizaciones, corrección de bugs o cambios en la infraestructura.

---

## 1. Módulo: Persistencia y SoftDeletes
*Objetivo: Verificar que los registros eliminados lógicamente no reaparezcan ni causen errores.*

| # | Escenario de Regresión | Acción | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 1.1 | Recuperación de citas "canceladas". | GET /appointments | Las citas con `deleted_at` no deben aparecer en el listado estándar. | Sí |
| 1.2 | Re-registro de email eliminado. | Register con email de User con SoftDelete | El sistema debe permitir el re-uso del email o restaurar la cuenta según política. | Sí |
| 1.3 | Integridad de productos eliminados. | Consultar movimiento de producto borrado | El movimiento debe seguir mostrando el nombre del producto vía SoftDelete. | Sí |
| 1.4 | Restauración de usuario (Admin). | Restore de User | El usuario debe recuperar sus roles y perfiles (Client/Barber) asociados. | Sí |
| 1.5 | Citas huérfanas por borrado de servicio. | DELETE /services/1 | No se debe permitir borrar servicios que tengan citas pendientes (RestrictOnDelete). | Sí |
| 1.6 | Limpieza de tokens tras borrado. | DELETE /users/1 | Los tokens activos en `mobile_api_tokens` deben invalidarse inmediatamente. | Sí |

---

## 2. Módulo: Ciclo de Vida de Citas
*Objetivo: Validar que los cambios de estado no rompan la lógica de negocio.*

| # | Escenario de Regresión | Flujo | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 2.1 | Cambio masivo de estados (Batch). | 10 citas a 'no_asistio' | El sistema debe procesar todas sin bloqueos de base de datos. | Sí |
| 2.2 | Re-confirmación de cita ya confirmada. | PATCH status: confirmada | No debe disparar una segunda notificación de confirmación. | Sí |
| 2.3 | Flujo de estados lineal. | Pendiente -> Confirmada -> En Proceso | El sistema debe permitir el avance lógico sin errores de transición. | Sí |
| 2.4 | Notas persistentes en cambios. | Editar notas en cada cambio de estado | Las notas originales deben conservarse o concatenarse correctamente. | Sí |
| 2.5 | Cálculo de `hora_fin` tras cambio de servicio. | Update service_id en Appointment | La `hora_fin` debe recalculase según la duración del nuevo servicio. | Sí |
| 2.6 | Notificación al barbero tras cancelación. | El cliente cancela su cita | El barbero asignado debe recibir una notificación (si está configurado). | Sí |

---

## 3. Módulo: Sincronización de Inventario
*Objetivo: Asegurar que el stock sea siempre veraz.*

| # | Escenario de Regresión | Operación | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 3.1 | Ajuste de stock manual vs Automático. | Entrada manual + Salida por cita | El balance final en `products.stock_actual` debe ser matemático. | Sí |
| 3.2 | Edición de producto (Nombre/Precio). | Update Product | Los movimientos históricos deben seguir referenciando el ID correcto. | Sí |
| 3.3 | Cambio de tipo de producto. | Insumo -> Venta | Debe permitir el cambio sin afectar el stock acumulado. | Sí |
| 3.4 | Carga de fotos masiva en Inventario. | 10 fotos en 1 min | El almacenamiento en S3/Local debe ser consistente y no dejar basura. | Sí |
| 3.5 | Sincronización de `min_stock` alertas. | Cambio de stock mínimo | Las alertas de Dashboard deben aparecer/desaparecer en tiempo real. | Sí |
| 3.6 | Registro de IVA/Impuestos en precios. | Cambio de precio_venta | El cálculo en reportes de ventas debe reflejar el precio vigente al momento. | Sí |

---

## 4. Módulo: Regresión del Menú Administrador
*Objetivo: Garantizar que releases no rompan las acciones de administración visibles en UI.*

| # | Escenario de Regresión | Acción del Menú | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 4.1 | Post-despliegue del Dashboard. | Abrir Dashboard y KPIs | Se mantienen métricas, gráficas y telemetría del chatbot. | Sí |
| 4.2 | Cambios en permisos de usuarios. | Abrir Usuarios/Logs/Reportes | El administrador conserva acceso total; otros roles siguen restringidos. | Sí |
| 4.3 | Refactor de inventario y catálogos. | Productos, Movimientos y Almacén | No se pierden filtros, paginación ni cálculos de stock. | Sí |
| 4.4 | Actualización de módulo de reportes. | Exportar PDF y Excel | Exportaciones siguen compatibles y no alteran formato de salida. | Sí |
| 4.5 | Cambios en chatbot y contexto AI. | Query, History, Clear, Train | Persistencia de historial y calidad de respuesta se mantienen. | Sí |
| 4.6 | Cambios en configuración global. | Settings + Maintenance toggle | Persistencia correcta y efecto visible al refrescar sesión. | Sí |

---

## 5. Módulo: Regresión Operativa de Recepción
*Objetivo: Evitar rompimientos en procesos de atención diaria tras despliegues.*

| # | Escenario de Regresión | Acción del Menú Recepción | Resultado Esperado | ¿Pasa? |
| :--- | :--- | :--- | :--- | :--- |
| 5.1 | Post-release del módulo de citas. | Crear/editar/cancelar cita en mostrador | El flujo completo mantiene validaciones y notificaciones. | Sí |
| 5.2 | Cambios en esquema de clientes. | Alta/edición de clientes | No se rompen formularios ni filtros del CRM. | Sí |
| 5.3 | Refactor en pagos y recibos. | Registrar pago y consultar historial | Persisten montos, métodos y referencias sin desviaciones. | Sí |
| 5.4 | Ajustes de inventario operativos. | Registrar movimientos entrada/salida | Stock y bitácora siguen sincronizados. | Sí |
| 5.5 | Cambios de permisos por rol. | Navegar módulos de recepción | Acceso operativo se mantiene y no hereda privilegios admin. | Sí |
| 5.6 | Actualización de chatbot. | Query/History/Clear desde sesión recepción | Historial y respuestas se conservan tras despliegue. | Sí |

