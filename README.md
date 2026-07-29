# UrbanBlade

Dashboard administrativo y sistema operativo para barbería, construido con Laravel, MongoDB, Docker, Redis y Chart.js. Incluye paneles por rol, gestión de citas, pagos, clientes, inventario, reportes, portafolio de barberos, tienda para clientes y centro de análisis con hallazgos exportados desde Spark.

## Requisitos

- Docker Desktop
- Git
- Node.js 20+ y npm
- Acceso a MongoDB Atlas o a una instancia MongoDB compatible
- Opcional: Ollama local si se usará el chatbot con IA local

## Clonar y levantar en Docker

```powershell
git clone https://github.com/KikeGonRam/barber.git
cd barber
git checkout feature/mongodb-migration
Copy-Item .env.example .env
```

Edita `.env` antes de levantar contenedores:

```env
APP_NAME=UrbanBlade
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mongodb
MONGODB_URI=mongodb+srv://USUARIO:PASSWORD@CLUSTER.mongodb.net/
MONGO_DATABASE=barber_db

REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
```

Levanta el proyecto:

```powershell
docker compose up -d --build
npm install
npm run build
docker compose exec app php artisan key:generate
docker compose exec app php artisan storage:link
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan optimize:clear
```

Abre:

- Aplicación: http://localhost:8000
- Mailpit: http://localhost:8025

## Comandos útiles

```powershell
docker compose ps
docker compose logs -f app
docker compose logs -f web
docker compose exec app php artisan test
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

## Roles principales

| Rol | Funciones principales |
| --- | --- |
| Administrador | Dashboard completo, analítica, reportes, usuarios, barberos, servicios, inventario, pagos, clientes, logs y configuración. |
| Recepcionista | Citas, calendario, clientes, pagos, pedidos, movimientos de inventario y analítica operativa recortada. |
| Barbero | Agenda propia, estado de citas, perfil, horario, portafolio y analítica personal. |
| Cliente | Reserva de citas, tienda, carrito, pedidos, facturas, membresía, barberos disponibles y recomendaciones. |

## Centro de análisis

La ruta `/analitica` muestra los resultados calculados por Spark en lenguaje claro para usuarios finales. Los datos se leen desde la colección `analytics_insights` y se filtran por rol desde Laravel:

- resumen ejecutivo;
- operación y equipo;
- clientes y ventas;
- predicción y cancelaciones;
- diagnóstico de datos.

Laravel no recalcula esos hallazgos: solo los presenta, filtra y visualiza. La lógica de análisis viene del proyecto Spark.

## Chatbot con Ollama

Si vas a usar Ollama desde Docker, ejecuta Ollama en el host y configura:

```env
CHATBOT_AI_PROVIDER=ollama
OLLAMA_URL=http://host.docker.internal:11434
OLLAMA_MODEL=qwen2.5:3b
```

## Validación antes de entregar

Antes de hacer commit o entregar una demo, ejecuta:

```powershell
docker compose exec app php artisan test
docker compose exec app php artisan validate:user-roles
docker compose exec app php artisan view:cache
docker compose exec app composer audit
docker compose exec app composer validate --strict
docker compose exec app ./vendor/bin/pint --test
npm run build
```

Si todo queda en verde, el proyecto está listo para demo local.
