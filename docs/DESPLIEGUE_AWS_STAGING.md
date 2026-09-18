# Despliegue de staging en AWS

Estado al 18 de septiembre de 2026. Entorno de **pruebas** (no producción) que corre
los tres proyectos activos —`barber` (API), `frontend-urban` (Nuxt) y `spark`
(dashboard)— en AWS. Sustituye al plan de Oracle Cloud de
[`ORACLE_CLOUD_PRUEBAS.md`](ORACLE_CLOUD_PRUEBAS.md), que nunca llegó a desplegar la
aplicación.

- Cuenta AWS `209479293733`, región `us-east-1`.
- Sin dominio propio todavía: HTTPS lo da CloudFront con su certificado (`*.cloudfront.net`).
- Credenciales de acceso: no viven aquí. Las de la aplicación están en Secrets Manager
  (nombres abajo, nunca valores); las de las cuentas de prueba, en [`ACCESOS.md`](ACCESOS.md).

## Arquitectura

```mermaid
flowchart LR
    U["Navegador"] -->|HTTPS| CFF["CloudFront frontend<br/>d1frc9zkkqqmbj"]
    U -->|HTTPS| CFA["CloudFront API<br/>d1s2thm3f8g40t"]
    U -->|HTTPS| CFS["CloudFront spark<br/>d1k8f7ya4l8w58"]
    ST["Stripe"] -->|"webhook /api/stripe/webhook"| CFA

    CFF -->|"HTTP :80"| ALB["ALB urbanblade-staging"]
    CFA -->|"HTTP :8080"| ALB
    CFS -->|"HTTP :8501"| ALB

    subgraph ECS["ECS Fargate (cluster urbanblade-staging, FARGATE_SPOT)"]
        FE["uba-stg-frontend<br/>Nuxt :3000"]
        BA["uba-stg-barber<br/>Caddy + PHP-FPM :80"]
        SP["uba-stg-spark<br/>Streamlit :8501"]
    end

    ALB --> FE
    ALB --> BA
    ALB --> SP

    BA --> ATL[("MongoDB Atlas<br/>barber_db · urbanblade_analytics")]
    SP --> ATL
    BA -->|"rol de tarea"| S3P[("S3 uploads<br/>lectura pública")]
    BA -->|"rol de tarea<br/>URLs firmadas 15 min"| S3R[("S3 receipts<br/>privado")]
    SM["Secrets Manager"] -.->|"inyectados al arrancar"| ECS
    ECR["ECR"] -.->|"imágenes :latest"| ECS
```

Los usuarios de Atlas siguen el mínimo privilegio descrito en
[`ADR-001-ARQUITECTURA-DE-DATOS.md`](ADR-001-ARQUITECTURA-DE-DATOS.md).

## Recursos

| Recurso | Nombre / valor |
|---|---|
| Repos ECR | `urbanblade/barber`, `urbanblade/frontend-urban`, `urbanblade/spark` |
| Cluster ECS | `urbanblade-staging` (estrategia por defecto FARGATE_SPOT) |
| Servicios | `uba-stg-barber`, `uba-stg-frontend`, `uba-stg-spark` |
| Task definitions | `urbanblade-staging-barber` (512 CPU / 1 GB), `-frontend` (512 / 1 GB), `-spark` (1024 / 3 GB) |
| Balanceador | ALB `urbanblade-staging`; listeners 80 → frontend, 8080 → barber, 8501 → spark |
| Health checks | frontend `/api/health`, barber `/up`, spark `/_stcore/health` |
| Red | VPC por defecto `vpc-052236dd391444209`, subredes d y f; SG del ALB `sg-0a537dcb25fcf51b2` (:80), `sg-0372b1aa878f1657f` (:8080), `sg-01da9703671ffd6ad` (:8501), todos solo desde CloudFront; SG de tareas (solo desde el ALB) `sg-0d01fa3c3044c3832` |
| CloudFront | 3 distribuciones (PriceClass_100, sin caché, origen HTTP): frontend `E1LFM8ET3R2TF3`, API `E3VPZXBKIE3G5T`, spark `E1WK7UCZ9FYH79` |
| S3 | `urbanblade-staging-uploads-209479293733` (imágenes públicas), `urbanblade-staging-receipts-209479293733` (privado) |
| Roles IAM | `ecsTaskExecutionRole` (ejecución + lectura de secretos), `urbanblade-staging-task-role` (acceso a los dos buckets) |
| Logs | CloudWatch `/ecs/urbanblade-staging/{barber,frontend-urban,spark}` |

### Secretos (Secrets Manager, `urbanblade/staging/...`)

- `barber/`: `APP_KEY`, `MONGODB_URI`, `ANALYTICS_MONGODB_URI`, `STRIPE_KEY`,
  `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
  `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`.
- `spark/`: `MONGO_PASSWORD`, `ANALYTICS_MONGO_PASSWORD`.

Cambiar un secreto no afecta a las tareas en marcha: hay que forzar un nuevo despliegue.

### Variables relevantes de barber (no secretas)

`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` = URL de CloudFront de la API (si
empieza con `https://` la app fuerza el esquema https: el ALB reescribe
`X-Forwarded-Proto`), `FRONTEND_URL`, `SPARK_URL`, `CORS_ALLOWED_ORIGINS` = URL de
CloudFront del frontend, `SESSION_DRIVER=file`, `CACHE_STORE=file`,
`QUEUE_CONNECTION=sync`, `UPLOADS_BUCKET`, `RECEIPTS_BUCKET`, `AWS_DEFAULT_REGION`.

## Integraciones externas

- **Stripe:** el destino del webhook debe crearse en la **misma cuenta de Stripe** que
  emite las claves `STRIPE_KEY`/`STRIPE_SECRET` (los eventos solo se entregan a
  destinos de la cuenta que procesó el pago). URL: `<CloudFront API>/api/stripe/webhook`
  (no lleva `/api/v1`). Eventos de tipo instantáneo; el `whsec_` del destino va en
  `STRIPE_WEBHOOK_SECRET`. Verificado el 2026-09-18 con un pago real de prueba.
- **Google login:** agregar `<CloudFront API>/api/v1/auth/google/callback` como URI de
  redirección autorizada en Google Cloud Console.

## Runbook

Los comandos usan `aws` con el usuario IAM `staging-deploy`.

**Apagar (ahorra el cómputo; el ALB sigue cobrando):**

```bash
for s in barber frontend spark; do aws ecs update-service --cluster urbanblade-staging --service uba-stg-$s --desired-count 0 --region us-east-1; done
```

**Encender:** el mismo comando con `--desired-count 1`.

**Desplegar una imagen nueva** (ejemplo `barber`; `frontend-urban` y `spark` igual con su repo):

```bash
docker build -f .docker/staging/Dockerfile -t 209479293733.dkr.ecr.us-east-1.amazonaws.com/urbanblade/barber:latest .
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin 209479293733.dkr.ecr.us-east-1.amazonaws.com
docker push 209479293733.dkr.ecr.us-east-1.amazonaws.com/urbanblade/barber:latest
aws ecs update-service --cluster urbanblade-staging --service uba-stg-barber --force-new-deployment --region us-east-1
```

Antes de subir, comprueba la fecha de creación de la imagen (`docker inspect --format='{{.Created}}'`):
un `docker build` fallido en silencio ya llevó a subir una imagen vieja. Los `push` a ECR
fallan a veces por timeout; reintentar.

**Cambiar variables o secretos:** registrar una task definition nueva
(`aws ecs register-task-definition`) y actualizar el servicio con ella; para secretos
basta `--force-new-deployment`.

**Ver logs:** CloudWatch, grupos `/ecs/urbanblade-staging/*`.

## Costo aproximado

Con los tres servicios encendidos 24/7: ~55–65 USD/mes (Fargate Spot + ALB). Apagados,
queda el ALB (~16–18 USD/mes) más centavos de ECR, S3 y Secrets Manager.

## Limitaciones conocidas

- `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=file`, `CACHE_STORE=file`: no hay Redis ni
  worker ni scheduler. Recordatorios y tareas programadas **no corren** en staging, y
  la sesión/caché se pierden al reiniciar la tarea.
- El ALB solo acepta tráfico de CloudFront (lista de prefijos `pl-3b927c52`), con un
  grupo de seguridad por puerto (`uba-stg-alb-cf-80`, `-8080`, `-8501`) porque la lista
  cuenta como ~55 reglas y el límite es 60 por grupo. Las tareas aceptan tráfico de
  `uba-stg-alb-cf-80`. El acceso directo `http://<ALB>:puerto` ya no responde.
- Fargate Spot puede interrumpir tareas; el servicio las repone solo.
- Sin dominio propio ni certificado ACM; al comprarlo hay que cambiar `APP_URL`,
  `FRONTEND_URL`, CORS, el callback de Google y el destino del webhook de Stripe.
- `staging-deploy` no puede crear roles IAM ni revocar reglas de grupos de seguridad:
  esos pasos se hacen desde la consola.
- Los grupos de seguridad antiguos `sg-02338d6cb80cf5866` y `sg-0dbd32344bd423d23` (con
  reglas abiertas a `0.0.0.0/0`) ya no están asociados al ALB y pueden eliminarse; el
  segundo sigue referenciado por reglas del grupo de las tareas, que hay que quitar
  primero desde la consola.
