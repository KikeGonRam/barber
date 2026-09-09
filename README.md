# UrbanBlade

<p align="center">
  <img src="docs/assets/landing.png" alt="UrbanBlade landing" width="1000" />
</p>

UrbanBlade es una plataforma operativa y analítica para barberías: administración,
atención al cliente, agenda, pagos, inventario y decisiones basadas en datos, con
experiencias diferenciadas para administrador, recepcionista, barbero y cliente.

**Este repositorio (`barber`) es hoy, funcionalmente, la API JSON que consume el
frontend real de la aplicación — [`frontend-urban`](https://github.com/KikeGonRam/frontend_Urbanblade)
(Nuxt 4)** — más un puñado cerrado de páginas Blade que Nuxt todavía no cubre: landing
pública, catálogo público de servicios/equipo, login/registro/recuperación de
contraseña, `/profile`, `/notifications`, el chatbot y la tarjeta de membresía en PDF.
El panel administrativo completo (los 4 dashboards por rol, citas, clientes, pagos,
pedidos, inventario, servicios, usuarios, campañas, reportes, etc.) se retiró de este
repo el 2026-09-06 una vez que Nuxt alcanzó paridad funcional confirmada — ver
`.claude/skills/urbanblade-guardrails/SKILL.md` (guardrail #18) para el historial
completo. Para la experiencia real del producto hace falta correr **ambos** repos.

## ✨ ¿Qué hace UrbanBlade?

- Expone una API JSON (Bearer token, no Sanctum) para citas, clientes, pagos,
  inventario, campañas, lealtad, notificaciones y analítica — consumida por
  `frontend-urban`.
- Separa flujos por rol para cada perfil del negocio (administrador, recepcionista,
  barbero, cliente, e "ingeniero" — este último solo lectura).
- Mantiene vivas las páginas que Nuxt no cubre todavía: landing, catálogo público,
  autenticación, perfil, notificaciones, chatbot, tarjeta de membresía.
- Cobros reales con Stripe (tarjeta, beta), transferencia con comprobante, y efectivo;
  programa de lealtad con puntos y descuentos por nivel.

## 🏗️ Stack

- PHP 8.3+, Laravel 13
- MongoDB con mongodb/laravel-mongodb (Atlas, compartida con `spark/`)
- Redis para caché, sesiones y cola (workers dedicados: `queue:work`, `schedule:work`)
- Vite + Tailwind CSS 3 + Alpine.js — solo para las páginas Blade que sobreviven; el
  frontend real es Nuxt 4 en `frontend-urban`
- Stripe, Socialite (login con Google), Laravel Pulse (panel de operación para
  "ingeniero"), Scribe (documentación de la API)
- Docker Compose para entorno local (`app`, `web`, `worker`, `scheduler`, `redis`,
  `mongo-test`, `mailpit`, `ollama`)

## 🧭 Roles del sistema

| Rol           | Visión principal                                                                   |
| ------------- | ---------------------------------------------------------------------------------- |
| Administrador | Dashboard global, reportes, KPIs, clientes, pagos, inventario y control operativo. |
| Recepcionista | Agenda del día, atención rápida, cobros y gestión de clientes.                     |
| Barbero       | Horario personal, citas, perfil, portafolio y seguimiento de actividad.            |
| Cliente       | Reservas, historial, tienda, facturas y membresía.                                 |
| Ingeniero     | Solo lectura: reportes, logs y estado del sistema (`/pulse`).                       |

Todas estas vistas viven hoy en **`frontend-urban`** (Nuxt), no en este repositorio.

## 📸 Vista previa

Estas dos capturas ya son de la interfaz real actual — Nuxt (`frontend-urban`), no el
panel Blade/Inertia retirado. El resto de las capturas (dashboard del cliente,
gestión de citas, modal de reserva con disponibilidad real) vive en el
[README de `frontend-urban`](https://github.com/KikeGonRam/frontend_Urbanblade#-vista-previa).

<p align="center">
  <img src="docs/assets/login.png" alt="Login de UrbanBlade" width="900" />
</p>

<p align="center">
  <img src="docs/assets/dashboard-admin.png" alt="Panel administrativo de UrbanBlade" width="1000" />
</p>

## 🚀 Arranque rápido

```powershell
git clone https://github.com/KikeGonRam/barber.git
cd barber
Copy-Item .env.example .env
```

Edita el archivo `.env` con los valores locales de tu entorno y luego levanta el proyecto:

```powershell
docker compose up -d --build
npm install
npm run build
docker compose exec app php artisan key:generate
docker compose exec app php artisan storage:link
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=RolePermissionSeeder
docker compose exec app php artisan db:seed --class=AdminUserSeeder
```

> ⚠️ **No uses `migrate --seed`** (siembra el `DatabaseSeeder` completo): eso
> incluye `BarberSeeder`/`ClientSeeder`, que generan 50 barberos y 1500
> clientes falsos, además de miles de citas/pagos/transacciones sintéticas —
> así fue como `barber_db` terminó con más de 200,000 registros de basura que
> hubo que limpiar. Los dos seeders de arriba son los únicos necesarios para
> que la app arranque (roles/permisos + una cuenta admin); el resto de
> cuentas de equipo se documentan en [docs/ACCESOS.md](docs/ACCESOS.md).

Abre la aplicación en:

- http://localhost:8000 — API + páginas Blade que sobreviven (landing, catálogo,
  auth, perfil, notificaciones, chatbot)
- Mailpit: http://localhost:8025

Para la experiencia real del producto (dashboards, citas, pagos, inventario, etc.),
clona y levanta también el frontend en un directorio hermano:

```powershell
git clone https://github.com/KikeGonRam/frontend_Urbanblade.git ../frontend-urban
cd ../frontend-urban
npm install --legacy-peer-deps
npm run dev
```

Nuxt sirve en http://localhost:3000 y apunta a este backend vía
`NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000/api/v1` (ver `.env.example` de ese repo).

## 🔐 Demo y acceso

Las credenciales reales del equipo (una cuenta por rol) viven en un único
lugar para no desincronizarse: **[docs/ACCESOS.md](docs/ACCESOS.md)**. La
guía de presentación está en [docs/DEMO_DEMOSTRACION.md](docs/DEMO_DEMOSTRACION.md).

Ruta de login:

- http://localhost:8000/login

> `barber_db` ya no viene precargada con datos de demo masivos (se limpió por
> completo el 2026-09-04) — solo existen las 4 cuentas documentadas en
> [docs/ACCESOS.md](docs/ACCESOS.md). No correr `BarberSeeder`/`ClientSeeder`
> completos salvo que de verdad se quiera repoblar con datos de prueba a gran
> escala (crean 50 barberos y 1500 clientes falsos respectivamente).

## 🧪 Validación y pruebas

Importante: en este proyecto no se deben ejecutar pruebas con `php artisan test` directo. La configuración real usa la base local de pruebas y la forma recomendada es:

```powershell
./test.ps1
```

Esto evita que Laravel use accidentalmente la base Atlas compartida con Spark.

## 🛠️ Comandos útiles

```powershell
docker compose ps
docker compose logs -f app
docker compose logs -f web
docker compose exec app php artisan validate:user-roles
docker compose exec app composer audit
docker compose exec app ./vendor/bin/pint --test
npm run build
```

También puedes usar Make:

```bash
make setup
make validate
make logs
make shell
```

## ⚙️ Configuración extra

### Chatbot con Ollama

```env
CHATBOT_AI_PROVIDER=ollama
OLLAMA_URL=http://host.docker.internal:11434
OLLAMA_MODEL=qwen2.5:3b
```

### Validación antes de entregar

```powershell
./test.ps1
docker compose exec app php artisan validate:user-roles
docker compose exec app php artisan view:cache
docker compose exec app composer audit
docker compose exec app composer validate --strict
docker compose exec app ./vendor/bin/pint --test
npm run build
```

Si todo queda en verde, el proyecto está listo para demo local.

## 📚 Documentación relevante

- [docs/ACCESOS.md](docs/ACCESOS.md)
- [docs/DEMO_DEMOSTRACION.md](docs/DEMO_DEMOSTRACION.md)
- [docs/DOCUMENTACION_TECNICA.md](docs/DOCUMENTACION_TECNICA.md)
- [docs/STRIPE_PRUEBAS_LOCALES.md](docs/STRIPE_PRUEBAS_LOCALES.md)
- [docs/MANUAL_USUARIO.md](docs/MANUAL_USUARIO.md)

## 🧩 Estado del repositorio

- Rama actual: `main`
- El proyecto comparte datos operativos con `spark` mediante MongoDB
- Las pruebas de integración usan una base local de pruebas configurada en `.env.testing`

---

UrbanBlade está pensado para presentar una barbería como una operación real, moderna y basada en métricas, no como un simple calendario de citas.
