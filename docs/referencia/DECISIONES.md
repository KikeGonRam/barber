# Decisiones de arquitectura del backend

Revisión contra el código: 28 de octubre de 2026.
Este registro separa decisiones implementadas de propuestas operativas.
No constituye autorización para escribir datos, cambiar infraestructura o desplegar.

| ID | Decisión | Estado | Motivo y consecuencia |
| --- | --- | --- | --- |
| D01 | Backend JSON y clientes web/Android separados. | Implementada. | Permite clientes independientes; exige coordinar un contrato HTTP estable. |
| D02 | Tokens Bearer propios, almacenados como hash. | Implementada. | Conserva el mecanismo existente; no documentar Sanctum como autenticación activa. |
| D03 | Monolito modular con servicios de dominio. | Implementada. | Mantiene transacciones y operación conjunta sin añadir sistemas distribuidos. |
| D04 | Separar cargas de datos, no una base por entidad. | Aceptada e implementada en configuración. | Core operativo, derivados analíticos, Pulse, Redis y entornos aislados. Véase ADR-001. |
| D05 | Replica set para desarrollo y pruebas. | Implementada. | Pagos e inventario usan transacciones multi-documento. |
| D06 | Precio, descuentos, saldo y stock calculados en servidor. | Implementada por servicios. | Los importes del cliente no son una fuente de verdad. |
| D07 | Estado de cita separado del pago. | Implementada. | Cobrar no equivale siempre a completar; iniciar depende de margen y pago resuelto. |
| D08 | Recursos públicos separados de comprobantes. | Implementada con límites del fallback local. | S3 privado usa URLs temporales; el fallback local no ofrece el mismo aislamiento. |
| D09 | OpenAPI y ejemplos comprobables para clientes. | Implementada con cobertura parcial. | Rutas y tipos se verifican; no todos los ejemplos equivalen a una especificación completa del dominio. |
| D10 | Cambios por rama y PR; publicación separada. | Vigente. | CI verde no significa despliegue; integraciones externas pueden reaccionar incluso a Markdown. |

## Fuentes y consecuencias

- D01/D03: [arquitectura](DOCUMENTACION_TECNICA.md), rutas, controladores y servicios.
- D02: [MobileApiToken](../../app/Models/MobileApiToken.php),
  [middleware de autenticación](../../app/Http/Middleware/AuthenticateMobileApiToken.php)
  y [config/auth.php](../../config/auth.php).
- D04/D05: [ADR-001](ADR-001-ARQUITECTURA-DE-DATOS.md),
  [config/database.php](../../config/database.php) y Compose.
- D06/D07: [PaymentService](../../app/Services/Payment/PaymentService.php),
  [InventoryService](../../app/Services/Inventory/InventoryService.php),
  [AppointmentStatusService](../../app/Services/Appointment/AppointmentStatusService.php)
  y [ServiceStartGuard](../../app/Services/Appointment/ServiceStartGuard.php).
- D08: [ReceiptStorage](../../app/Support/ReceiptStorage.php) y filesystems.
- D09: [contrato](PLAN_CONTRATO_API.md) y [pruebas](PRUEBAS.md).
- D10: [CI/CD](CI_CD.md) y [contribución](../../CONTRIBUTING.md).

## Propuestas y comprobaciones pendientes

- Renombrar la base operativa a `urbanblade_core` fue una propuesta; no es el
  nombre que deba asumirse como implementado.
- Permisos mínimos, aislamiento de red y retención de backups requieren verificar
  el entorno remoto; una declaración antigua no los acredita.
- Los objetivos RPO/RTO descritos en [continuidad](CONTINUIDAD_OPERATIVA.md)
  son objetivos que deben acordarse y ensayarse; no métricas garantizadas.
- La configuración actual de Scribe mantiene `auth.enabled=false`; no cambia
  la protección real de rutas, pero puede generar una introducción de autenticación
  incorrecta. Corregir el generador requiere un cambio técnico separado y validación.
- No se infiere soporte de tipos Vue completo a partir de un build del frontend.

## Sustitución de documentación anterior

Se retiran de la documentación activa los planes de Oracle no utilizados,
prompts de traspaso entre proveedores, la guía TXT duplicada y reportes de fases
que se habían convertido en instrucciones operativas contradictorias.
Las decisiones útiles se consolidan aquí, en ADR-001, MongoDB y continuidad.
Las revisiones anteriores siguen disponibles en Git; no se conservan como
instrucciones vigentes ni se reescribe el historial.

Los Word técnicos antiguos se retiran para evitar mantener manuales
independientes de los Markdown. El documento Canvas académico se conserva como
entregable histórico, no como especificación de arquitectura o de operación.

| Documentación retirada | Fuente actual |
| --- | --- |
| Fases 0, 1 y 2; PLAN_ENDURECIMIENTO_ATLAS.md | ADR-001 y MONGODB_ATLAS.md. |
| Fase 5 y FASE-5C-S3.md | CONTINUIDAD_OPERATIVA.md. |
| PLAN_FLUJO_CITAS_V2.md | DOCUMENTACION_TECNICA.md y servicios actuales. |
| ORACLE_CLOUD_PRUEBAS.md y prompt de continuidad | Arquitectura y runbook vigentes. |
| COMO_DESCARGAR_LOS_PROYECTOS.txt | CLONAR_PROYECTOS.md. |
| Tres manuales Word y sus PDF | Arquitectura, PRUEBAS.md y MANUAL_USUARIO.md. |
| Skills de migración Inertia y roadmap completado | Guías estables de mantenimiento y este registro. |
