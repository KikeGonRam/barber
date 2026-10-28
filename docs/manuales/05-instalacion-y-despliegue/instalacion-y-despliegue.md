# Instalación y Despliegue

Cómo preparar un entorno local de UrbanBlade y cómo publicar una nueva versión en el entorno de pruebas de AWS.

| Dato | Valor |
| --- | --- |
| Documento | Instalación y Despliegue de UrbanBlade |
| Versión | 2.0 · 28 de octubre de 2026 |
| Audiencia | Desarrolladores y responsables de publicar |
| Fuente | `docs/referencia/CLONAR_PROYECTOS.md`, `docs/referencia/DESPLIEGUE_AWS_STAGING.md`, los `Dockerfile` y el estado de AWS leído en solo lectura el 28 de octubre de 2026 |
| Clasificación | Uso interno. Sin credenciales ni valores de secretos |

## 1. Visión general

UrbanBlade son repositorios independientes. Para trabajar en la API basta clonar `barber`; el frontend, Android y Spark se instalan por separado.

| Componente | Repositorio | Puerto local |
| --- | --- | --- |
| API Laravel | `KikeGonRam/barber` | 8000 |
| Web Nuxt | `KikeGonRam/frontend_Urbanblade` | 3000 |
| Android | `al140605/UrbanBladeMobile` | Emulador o USB |
| Analítica (Spark) | `KikeGonRam/spark` | 8501 |

> **Importante** Una instalación local **nunca debe apuntar a la base de datos real**. Usa siempre los entornos aislados de desarrollo (`urbanblade_dev`) y pruebas (`barber_db_test`).

## 2. Requisitos

| Herramienta | Para qué | Nota |
| --- | --- | --- |
| Git | Clonar y trabajar con ramas. | |
| Docker con Compose | API, Nginx, worker, scheduler, Redis, Mailpit, Ollama y MongoDB. | En Windows, Docker Desktop abierto. |
| Node.js y npm | Recursos de Laravel y frontend Nuxt. | El frontend se construye en CI con Node 24. |
| PowerShell | `setup.ps1`, `test.ps1` y scripts. | Windows. |
| AWS CLI (opcional) | Solo para lecturas y publicación en staging. | Perfil con permisos acotados. |
| Android Studio (opcional) | Compilar la app móvil. | |

## 3. Instalación local del backend

### 3.1 Clonar y configurar

```powershell
git clone https://github.com/KikeGonRam/barber.git barber
cd barber
Copy-Item .env.example .env
Copy-Item .env.development.example .env.development
```

1. Revisa `.env.development.example`, `docker-compose.yml` y `docker-compose.development.yml`.
2. Define una `APP_KEY` **exclusiva de desarrollo** en `.env.development`. No copies la clave de otro entorno.
3. Mantén ambos archivos fuera de Git. No sobrescribas un `.env` ya existente.

### 3.2 Arrancar

```powershell
docker compose --env-file .env.development -f docker-compose.yml -f docker-compose.development.yml up -d --build
npm ci
npm run build
docker compose --env-file .env.development -f docker-compose.yml -f docker-compose.development.yml ps
```

El overlay de desarrollo añade `mongo-dev` (replica set `rsdev`, base `urbanblade_dev`), separado del `mongo-test` de las pruebas. No necesita credenciales de Atlas.

| Servicio | Función | Dirección |
| --- | --- | --- |
| `app` | API PHP-FPM | |
| `web` | Nginx | http://localhost:8000 |
| `worker` | Cola de trabajos | |
| `scheduler` | Tareas programadas | |
| `redis` | Caché y colas | |
| `mailpit` | Bandeja de correos de prueba | http://localhost:8025 |
| `ollama` | Asistente local | |
| `mongo-test` | Base de pruebas | |

### 3.3 Datos iniciales

Para arrancar solo se necesitan los semillas de roles y del administrador (`RolePermissionSeeder` y `AdminUserSeeder`). Ejecuta migraciones o semillas **únicamente después de confirmar a qué base apunta el `.env`**. Las semillas generan contraseñas aleatorias y las imprimen una sola vez, salvo que las fijes en variables `*_SEED_PASSWORD`.

> **Riesgo** `setup.ps1` puede ejecutar migraciones. No lo uses sobre datos operativos ni para revisar documentación.

## 4. Instalación del frontend

```powershell
git clone https://github.com/KikeGonRam/frontend_Urbanblade.git frontend-urban
cd frontend-urban
npm ci --legacy-peer-deps
Copy-Item .env.example .env
npm run dev
```

La aplicación abre en http://localhost:3000 y consume la API local en `/api/v1`. Todo valor `NUXT_PUBLIC_*` se **expone al navegador**: nunca pongas secretos ahí.

## 5. Android y analítica

Siguen las instrucciones de sus propios repositorios. Dependen de sus herramientas y contratos; no reutilices recetas antiguas del backend. La variante `staging` de Android apunta a `api.urbanblade.com.mx`.

## 6. Verificar la instalación

| Comprobación | Resultado esperado |
| --- | --- |
| `GET http://localhost:8000/up` | Responde 200. |
| http://localhost:3000 | Abre la página principal. |
| Registro de un cliente | El correo de verificación aparece en Mailpit (puerto 8025). |
| `.\test.ps1 --testsuite=UT` | Las pruebas unitarias pasan. |

Las pruebas del backend se ejecutan **solo con `.\test.ps1`**, tras comprobar que `barber-app` monta el repositorio y que `mongo-test` está sano.

## 7. Variables de entorno

Solo se listan nombres, nunca valores. Las plantillas están en `.env.example` y `.env.development.example`.

| Grupo | Variables representativas |
| --- | --- |
| Aplicación | `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY`, `FRONTEND_URL`, `SPARK_URL` |
| Datos | `MONGODB_URI`, `MONGO_DATABASE`, `ANALYTICS_MONGODB_URI`, `ANALYTICS_MONGO_DATABASE` |
| Sesión, caché y colas | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `CACHE_LIMITER_STORE`, `SCHEDULE_CACHE_STORE` |
| Red y seguridad | `CORS_ALLOWED_ORIGINS`, `TRUSTED_PROXY_HOPS`, `FIRST_USER_ADMIN_ENABLED` |
| Pagos | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` |
| Acceso con Google | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` |
| Correo | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` |
| Push | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `FIREBASE_PROJECT_ID`, `FIREBASE_CREDENTIALS_PATH` |
| Archivos | `UPLOADS_BUCKET`, `RECEIPTS_BUCKET`, `AWS_DEFAULT_REGION` |
| Asistente | `CHATBOT_AI_PROVIDER`, `OLLAMA_URL`, `OLLAMA_MODEL` |
| Tareas | `RUN_SCHEDULER` (activa el planificador dentro del contenedor de staging) |
| Frontend | `NUXT_PUBLIC_API_BASE`, `NUXT_PUBLIC_STRIPE_KEY`, `TRUSTED_PROXY_HOPS`, `ABUSE_BLOCK_ENABLED` |

## 8. Despliegue en el entorno de pruebas (AWS)

El runbook completo, con todos los comandos, es `docs/referencia/DESPLIEGUE_AWS_STAGING.md`. Esta sección resume el flujo.

### 8.1 Antes de publicar

1. Confirma con el propietario **el alcance y la autorización** para publicar.
2. Revisa el commit exacto, el PR y los checks verdes.
3. Identifica migraciones, índices, variables y efectos de arranque.
4. Confirma la **imagen de respaldo** para revertir.
5. Revisa integraciones externas conectadas al repositorio: un cambio solo de Markdown puede disparar una vista previa en Vercel en el frontend.

> **Aviso** Un cambio solo de documentación **no se despliega**. Publicar es una decisión aparte de fusionar.

> **Riesgo** El arranque de la imagen de staging de `barber` ejecuta `php artisan migrate --force`, prepara las cachés y puede iniciar el planificador. **Recrear o reemplazar una tarea puede modificar datos**; por eso no se usa como prueba de humo contra una base operativa.

### 8.2 Imágenes

| Servicio | Repositorio de imágenes | Dockerfile |
| --- | --- | --- |
| `barber` | `urbanblade/barber` | `.docker/staging/Dockerfile` (PHP-FPM + Caddy). El `Dockerfile` de la raíz es el de desarrollo. |
| `frontend-urban` | `urbanblade/frontend-urban` | `Dockerfile` de la raíz (Node 22). No existe `.docker/staging/`. |
| `spark` | `urbanblade/spark` | Su propio `Dockerfile`. |
| `ollama` | `urbanblade/ollama` | Imagen con el modelo `qwen2.5:0.5b`. |

> **Riesgo** Construir `barber` con el `Dockerfile` equivocado deja la tarea sin Caddy: falla la verificación de `/up` y el despliegue se queda en curso. Reconstruye con el correcto de inmediato.

### 8.3 Publicar una imagen

Construye desde `git archive <sha>` en un directorio temporal, no desde la carpeta de trabajo (`COPY . .` copiaría archivos sin versionar).

```powershell
$repo = "<cuenta>.dkr.ecr.us-east-1.amazonaws.com/urbanblade/barber"
$sha  = git rev-parse --short HEAD
docker build -f .docker/staging/Dockerfile -t "${repo}:latest" -t "${repo}:$sha" .
(aws ecr get-login-password --region us-east-1) | docker login --username AWS --password-stdin <cuenta>.dkr.ecr.us-east-1.amazonaws.com
docker push "${repo}:$sha"
docker push "${repo}:latest"
aws ecs update-service --cluster urbanblade-staging --service uba-stg-barber --force-new-deployment --region us-east-1
```

Antes de empujar `latest`, guarda la imagen anterior con una etiqueta de respaldo (`rollback-AAAAMMDD-pre-<sha>`). Cada imagen se sube con **dos etiquetas**: `latest` (la que usa la definición de tarea) y el hash corto del commit.

### 8.4 Verificar

```powershell
aws ecs wait services-stable --cluster urbanblade-staging --services uba-stg-barber --region us-east-1
curl.exe -s -o NUL -w "%{http_code}" https://api.urbanblade.com.mx/up
```

Durante el despliegue conviven tareas viejas y nuevas; verifica solo después de `services-stable`. Un `200` no basta: recorre las pantallas afectadas con el rol correspondiente. Un CI en verde no sustituye esa evidencia.

### 8.5 Revertir

Vuelve a etiquetar la imagen de respaldo como `latest`, súbela y fuerza el despliegue. La reversión de una imagen **no deshace migraciones ni datos**.

### 8.6 Variables y secretos

Cambiar una variable o un secreto exige registrar una definición de tarea nueva (variables) o forzar un despliegue (secretos). Los secretos viven en Secrets Manager con el prefijo `urbanblade/staging/…`. Con más de una réplica de `barber` agrega `CACHE_LIMITER_STORE=mongodb` y `SCHEDULE_CACHE_STORE=mongodb`; el script `scripts/Compartir-EstadoTareasStaging.ps1` lo prepara (simulación por defecto).

## 9. Si el despliegue falla

| Síntoma | Causa probable | Qué hacer |
| --- | --- | --- |
| El despliegue queda "en curso" | Falla la verificación de salud. | Revisa `/ecs/urbanblade-staging/<servicio>` en CloudWatch. |
| `/up` no responde | Imagen construida con el Dockerfile equivocado. | Reconstruye con `.docker/staging/Dockerfile`. |
| 0 tareas y sin eventos | Tarea Spot interrumpida (pasó con el frontend el 24-oct-2026). | Respalda la configuración y recrea el servicio con autorización del propietario. |
| 502 o 504 | Balanceador o tarea; CloudFront no cachea nada. | Revisa tareas y logs; no invalides caché. |
| Recordatorios repetidos | Más de una réplica con `RUN_SCHEDULER=true`. | Aplica `SCHEDULE_CACHE_STORE` o deja una sola réplica. |

## 10. Qué no hacer

- No ejecutar migraciones, semillas ni limpieza de colecciones como preparación de otra tarea.
- No pegar secretos en comandos, capturas, issues ni documentos.
- No reutilizar la `APP_KEY` de otro entorno.
- No publicar para "probar" un cambio de documentación.
- No tratar `staging` como producción.
