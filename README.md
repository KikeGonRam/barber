# UrbanBlade

UrbanBlade es un dashboard administrativo y sistema operativo para barberías, construido con Laravel, MongoDB, Redis, Docker y Vite. El proyecto cubre roles de administrador, recepcionista, barbero y cliente, con gestión operativa, reportes, analítica, pagos, citas, clientes, inventario y experiencia del cliente.

## Stack

- PHP 8.2+
- Laravel 12
- MongoDB con mongodb/laravel-mongodb
- Redis para caché, sesiones y cola
- Vite, Tailwind CSS 3, Alpine.js, Chart.js, FullCalendar
- Docker Compose para entorno local

## Requisitos

- Docker Desktop
- Git
- Node.js 20+ y npm
- Acceso a MongoDB Atlas o una instancia MongoDB compatible
- Opcional: Ollama local para chatbot con IA

## Estado del repositorio

- Rama actual: main
- El proyecto usa una base MongoDB compartida con Spark para datos operativos
- Las pruebas de integración NO se ejecutan contra Atlas; usan la base local de pruebas configurada en .env.testing

## Arranque rápido

```powershell
git clone https://github.com/KikeGonRam/barber.git
cd barber
Copy-Item .env.example .env
```

Edita el archivo .env con los valores locales de tu entorno y luego levanta el proyecto:

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

## Demo y acceso

El proyecto ya viene sembrado con usuarios demo listos para usar. Revisa la documentación de accesos en [docs/ACCESOS.md](docs/ACCESOS.md) y la guía de presentación en [docs/DEMO_DEMOSTRACION.md](docs/DEMO_DEMOSTRACION.md).

### Vista previa visual

![Landing de UrbanBlade](docs/assets/landing.png)

![Login de UrbanBlade](docs/assets/login.png)

![Dashboard administrativo](docs/assets/dashboard-admin.png)

Ruta de login:

- http://localhost:8000/login

### Credenciales demo

| Rol           | Correo                              | Contraseña      |
| ------------- | ----------------------------------- | --------------- |
| Administrador | kikermairez160418@gmail.com         | UrbanBlade2026! |
| Recepción     | manuela.andres78@gmail.com          | UrbanBlade2026! |
| Barbero       | omar.tamayo.juan.b1@outlook.com     | UrbanBlade2026! |
| Cliente       | jordi.curiel.medina.c1@yahoo.com.mx | UrbanBlade2026! |

## Roles principales

| Rol           | Funciones principales                                                                                |
| ------------- | ---------------------------------------------------------------------------------------------------- |
| Administrador | Dashboard global, analítica, reportes, clientes, pagos, inventario, configuración y gestión general. |
| Recepcionista | Agenda, citas del turno, clientes, cobros, pedidos y flujo operativo.                                |
| Barbero       | Mi agenda, estado de citas, perfil, horario, portafolio y analítica personal.                        |
| Cliente       | Reserva citas, historial, tienda, carrito, facturas y membresía.                                     |

## Centro de análisis

La vista /analitica presenta insights generados por el proyecto Spark, adaptados al rol del usuario. Los datos se leen desde la colección analytics_insights y se muestran con formato claro para decisiones operativas.

## Tests y validación

Importante: no ejecutes pruebas con php artisan test directo en este proyecto. La configuración real del repositorio usa la base de pruebas local y la forma recomendada es:

```powershell
./test.ps1
```

Esto evita que Laravel use la base Atlas compartida por error.

## Comandos útiles

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

## Configuración extra

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

## Documentación relevante

- [docs/ACCESOS.md](docs/ACCESOS.md)
- [docs/DEMO_DEMOSTRACION.md](docs/DEMO_DEMOSTRACION.md)
- [docs/DOCUMENTACION_TECNICA.md](docs/DOCUMENTACION_TECNICA.md)
- [docs/MANUAL_USUARIO.md](docs/MANUAL_USUARIO.md)
