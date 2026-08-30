# UrbanBlade

<p align="center">
  <img src="docs/assets/landing.png" alt="UrbanBlade landing" width="1000" />
</p>

UrbanBlade es una plataforma operativa y analítica para barberías, pensada para centralizar administración, atención al cliente, agenda, pagos, inventario y decisiones basadas en datos. El sistema combina un dashboard premium para el negocio con experiencias diferenciadas para administrador, recepcionista, barbero y cliente.

## ✨ ¿Qué hace UrbanBlade?

- Gestiona citas, clientes, pagos e inventario desde un mismo panel.
- Separa flujos por rol para cada perfil del negocio.
- Ofrece analítica accionable con insights operativos y de negocio.
- Alinea la experiencia del cliente con reservas, historial, tienda y membresía.
- Está preparado para demo local, validación y presentación comercial.

## 🏗️ Stack

- PHP 8.2+
- Laravel 12
- MongoDB con mongodb/laravel-mongodb
- Redis para caché, sesiones y cola
- Vite + Tailwind CSS 3 + Alpine.js
- Chart.js, FullCalendar y componentes de dashboard premium
- Docker Compose para entorno local

## 🧭 Roles del sistema

| Rol | Visión principal |
| --- | --- |
| Administrador | Dashboard global, reportes, KPIs, clientes, pagos, inventario y control operativo. |
| Recepcionista | Agenda del día, atención rápida, cobros y gestión de clientes. |
| Barbero | Horario personal, citas, perfil, portafolio y seguimiento de actividad. |
| Cliente | Reservas, historial, tienda, facturas y membresía. |

## 📸 Vista previa

<p align="center">
  <img src="docs/assets/login.png" alt="Login UrbanBlade" width="900" />
</p>

<p align="center">
  <img src="docs/assets/dashboard-admin.png" alt="Dashboard UrbanBlade" width="1000" />
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
docker compose exec app php artisan migrate --seed
```

Abre la aplicación en:

- http://localhost:8000
- Mailpit: http://localhost:8025

## 🔐 Demo y acceso

El proyecto ya viene sembrado con usuarios demo listos para usar. Revisa la documentación de accesos en [docs/ACCESOS.md](docs/ACCESOS.md) y la guía de presentación en [docs/DEMO_DEMOSTRACION.md](docs/DEMO_DEMOSTRACION.md).

Ruta de login:

- http://localhost:8000/login

### Credenciales demo

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Administrador | kikermairez160418@gmail.com | UrbanBlade2026! |
| Recepción | manuela.andres78@gmail.com | UrbanBlade2026! |
| Barbero | omar.tamayo.juan.b1@outlook.com | UrbanBlade2026! |
| Cliente | jordi.curiel.medina.c1@yahoo.com.mx | UrbanBlade2026! |

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
- [docs/MANUAL_USUARIO.md](docs/MANUAL_USUARIO.md)

## 🧩 Estado del repositorio

- Rama actual: `main`
- El proyecto comparte datos operativos con `spark` mediante MongoDB
- Las pruebas de integración usan una base local de pruebas configurada en `.env.testing`

---

UrbanBlade está pensado para presentar una barbería como una operación real, moderna y basada en métricas, no como un simple calendario de citas.
