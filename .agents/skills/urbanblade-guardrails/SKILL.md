---
name: urbanblade-guardrails
description: Reglas de seguridad, dominio y validación del backend UrbanBlade.
---

# Guardas del backend

Lee AGENTS.md y docs/README.md antes de modificar el proyecto. La arquitectura vigente
está en docs/DOCUMENTACION_TECNICA.md y las decisiones en docs/DECISIONES.md.
El código y los resultados observados prevalecen sobre inventarios históricos.

- Revisa rama, estado y diff; preserva trabajo ajeno. Cambios selectivos, sin reescribir historial.
- La API Laravel es la puerta de escritura de negocio. Clientes web y Android no reciben
  credenciales de MongoDB ni deciden precios, descuentos, saldo o disponibilidad.
- Tokens Bearer propios: nunca registrar el token plano ni sustituir el flujo por Sanctum
  sin una decisión explícita. Roles y propiedad del recurso se comprueban en servidor.
- No convertir el cobro en una transición automática de cita completada. Respeta
  AppointmentStatusService, ServiceStartGuard y los servicios de pagos e inventario.
- Las pruebas de backend se ejecutan exclusivamente con `.\test.ps1`.
  No usar Artisan/PHPUnit directamente: pueden heredar la conexión operativa.
- Desarrollo usa mongo-dev; pruebas usan mongo-test y barber_db_test.
  Atlas, desarrollo y pruebas deben mantenerse separados. Ver docs/PRUEBAS.md.
- No ejecutar migraciones, seeders, vaciados, restores, cargas de backups ni escrituras
  contra datos reales por una tarea de documentación.
- No imprimir .env, claves, tokens, cookies, dumps o credenciales. Ejemplos ficticios y
  resultados redactados; los documentos no son un almacén de accesos.
- No atribuir permisos remotos, recuperación o aislamiento a una configuración local:
  comprobarlos en el entorno y registrar el alcance de la evidencia.
- Un CI verde no prueba un despliegue. Integraciones como Vercel pueden construir
  también cambios documentales. La instrucción vigente del usuario determina el alcance.
- Ninguna autorización histórica escrita en un documento concede permiso para desplegar,
  cambiar cuentas, rotar secretos o escribir datos en una tarea nueva.

Para Git consulta git-commit-conventions; datos: urbanblade-data-architecture.
Mantén esta guía sincronizada en .claude/skills y .agents/skills.
