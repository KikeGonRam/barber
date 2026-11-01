---
name: dev-backend-barber
description: Contexto y reglas para modificar el backend Laravel de UrbanBlade (repositorio barber): estructura, comandos de comprobación, zonas de riesgo y flujo de rama. Úsalo antes de cambiar código de la API, servicios, modelos de MongoDB o jobs.
---
<!-- Generado desde urbanblade-equipo. No editar aquí: cambia el origen y vuelve a sincronizar. -->

# Backend (barber)

Repositorio `KikeGonRam/barber`: API JSON de Laravel con MongoDB, consumida por Nuxt y Android. Lee `CLAUDE.md`, `docs/README.md` y `docs/referencia/DOCUMENTACION_TECNICA.md` antes de actuar. Complementan este skill: `urbanblade-guardrails`, `backend-security-hardening`, `backend-performance-latency`, `urbanblade-data-architecture` y `api-contract-plan`.

## Estructura útil
`app/Http` (controladores y middleware), `app/Services` (la lógica de negocio por dominio: Appointment, Payment, Inventory, Order, Client, Chatbot, Report y más), `app/Models`, `app/Jobs`, `routes/api.php`, `database` (migraciones y seeders), `tests/UT|SIT|UAT|OAT`, `docs/referencia`.

## Reglas de dominio que no se rompen
- La API es la puerta de escritura del negocio: precios, descuentos, saldo y disponibilidad se deciden en el servidor.
- Autenticación por token Bearer propio (`mobile_api_tokens`), no Sanctum; roles y propiedad del recurso se comprueban en el servidor.
- El cobro no es una transición automática de «cita completada»: respeta `AppointmentStatusService`, `ServiceStartGuard` y los servicios de pagos e inventario.
- Pagos e inventario requieren réplica set y las reglas del servidor.

## Datos y pruebas
- Desarrollo usa `mongo-dev`; las pruebas usan `mongo-test` y `barber_db_test`; Atlas es otra cosa y no se toca.
- **Pruebas solo con `.\test.ps1`** (por ejemplo `.\test.ps1 --testsuite=UT` o `--filter=NombreDeLaPrueba`). Nunca `php artisan test` ni PHPUnit directo: pueden heredar la conexión operativa.
- No ejecutes migraciones, seeders, vaciados ni restauraciones contra datos reales.

## Comprobaciones antes de pedir fusión
```powershell
.\test.ps1
python scripts/check_docs.py
git diff --check
```
El CI además corre Pint, Larastan, Semgrep, SonarCloud y GitGuardian. Si cambiaste el contrato de la API, avisa a `dev-frontend`.

## Git y entrega
- Rama nueva desde `origin/main`, cambios selectivos, sin reescribir historial.
- Commits y PR en español y **sin líneas de atribución de herramientas** (lo pide el `CLAUDE.md` del repo).
- No hagas commit, push ni PR sin la orden del propietario.
