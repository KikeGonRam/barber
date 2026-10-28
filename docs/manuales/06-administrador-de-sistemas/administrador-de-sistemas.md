# Administrador de Sistemas

Guía de operación diaria del entorno de pruebas de UrbanBlade en AWS, MongoDB Atlas y los servicios asociados.

| Dato | Valor |
| --- | --- |
| Documento | Guía del Administrador de Sistemas de UrbanBlade |
| Versión | 2.0 · 28 de octubre de 2026 |
| Audiencia | Quien opera la infraestructura (AWS, Atlas, DNS, secretos) |
| Fuente | `docs/referencia/DESPLIEGUE_AWS_STAGING.md`, `docs/referencia/MONGODB_ATLAS.md`, `docs/referencia/CONTINUIDAD_OPERATIVA.md`, `docs/referencia/PLAN_ROTACION_CREDENCIALES.md` y el estado de AWS leído en solo lectura el 28 de octubre de 2026 |
| Clasificación | Uso interno. Sin credenciales; los identificadores de cuenta no se reproducen |

## 1. Responsabilidades y alcance

El administrador de sistemas mantiene **disponible, seguro y recuperable** el entorno de pruebas. Opera cuatro cosas:

| Área | Qué incluye |
| --- | --- |
| Cómputo | Servicios en ECS Fargate, balanceador, CloudFront e imágenes en ECR. |
| Datos | MongoDB Atlas, buckets S3, respaldos y restauración. |
| Identidad y secretos | Usuarios IAM, Secrets Manager, rotación de credenciales. |
| Red y dominio | Route 53, certificado en ACM, CloudFront y grupos de seguridad. |

> **Aviso** El entorno es de **pruebas, no producción**. Un nombre histórico con "staging" no certifica qué datos atiende un recurso: antes de operar, identifica cuenta, región, clúster, servicio, imagen y base de datos.

## 2. Mapa del entorno

| Servicio | Estrategia | Tareas | Función |
| --- | --- | --- | --- |
| `uba-stg-barber` | FARGATE | 2 | API Laravel (Caddy + PHP-FPM) en `api.urbanblade.com.mx`. |
| `uba-stg-frontend` | FARGATE | 2 | Web Nuxt en `urbanblade.com.mx` y `www`. |
| `uba-stg-spark` | FARGATE_SPOT | 1 | Panel Streamlit en `spark.urbanblade.com.mx`. |
| `uba-stg-ollama` | FARGATE_SPOT | 1 | Asistente virtual local (modelo `qwen2.5:0.5b`). |

Estado leído el 28 de octubre de 2026. Si la consola de AWS muestra otra cosa, **confía en la consola**.

Recursos de apoyo: clúster `urbanblade-staging`, un ALB con listeners 80, 8080 y 8501, tres distribuciones de CloudFront, repositorios ECR (`barber`, `frontend-urban`, `spark`, `ollama`), dos buckets S3 (imágenes públicas y comprobantes privados), Secrets Manager con el prefijo `urbanblade/staging/` y logs en CloudWatch (`/ecs/urbanblade-staging/…`).

## 3. Acceso y cuentas

- **Un usuario IAM por persona**, nunca compartir las claves de otro. Cada quien tiene su historial en CloudTrail y se puede revocar sin afectar a los demás.
- El usuario de despliegue (`staging-deploy`) tiene ECR, ECS, ELB, EC2 (solo grupos de seguridad), Secrets Manager, CloudWatch Logs, CloudFront y S3. **No puede** crear roles IAM, registrar definiciones de tarea ni pedir certificados en ACM; esos pasos los hace un administrador desde la consola.
- Verifica tu identidad antes de operar:

```powershell
aws sts get-caller-identity
```

- Las claves se configuran con `aws configure` y se comparten por un canal privado; nunca por chat de grupo ni en un commit.
- Aparte de AWS, cada repositorio de GitHub y MongoDB Atlas tienen sus propios accesos.

## 4. Operación diaria

### 4.1 Ver qué está encendido

```powershell
aws ecs describe-services --cluster urbanblade-staging `
  --services uba-stg-barber uba-stg-frontend uba-stg-spark uba-stg-ollama --region us-east-1 `
  --query "services[].{name:serviceName,desired:desiredCount,running:runningCount}" --output table
```

`desired` es lo que pediste y `running` lo que hay. Tarda 1–2 minutos en alcanzarse; repite hasta que coincidan.

### 4.2 Encender y apagar (ahorrar costo)

```powershell
aws ecs update-service --cluster urbanblade-staging --service uba-stg-spark --desired-count 0 --region us-east-1   # apagar
aws ecs update-service --cluster urbanblade-staging --service uba-stg-spark --desired-count 1 --region us-east-1   # encender
```

El balanceador sigue cobrando aunque todos los servicios estén en 0. Apagar `ollama` solo detiene el asistente; el resto de la aplicación sigue.

### 4.3 Logs

```powershell
aws logs tail /ecs/urbanblade-staging/barber --region us-east-1 --since 30m --follow
```

Cambia `barber` por `frontend-urban` o `spark`. Cómo leerlos: el arranque de `barber` imprime la ejecución de migraciones, la preparación de cachés y, si está activo, el planificador (`Running [artisan …] DONE`).

### 4.4 Costos

Consulta Cost Explorer en la consola o `aws ce get-cost-and-usage`. La cifra de ~55–65 USD/mes de septiembre ya no es válida: desde entonces `barber` y `frontend` corren en FARGATE normal con dos tareas y se agregó `ollama` (2 vCPU y 4 GB).

## 5. Publicar y revertir

El procedimiento detallado está en el manual de **Instalación y Despliegue**. Resumen operativo:

1. Autorización del propietario y checks del PR en verde.
2. Etiqueta de respaldo de la imagen vigente.
3. Construir desde `git archive`, subir las etiquetas `<sha>` y `latest`, forzar el despliegue.
4. Esperar `services-stable` y verificar `/up` y el recorrido del rol afectado.
5. Para revertir: etiquetar la imagen de respaldo como `latest`, subirla y forzar el despliegue.

> **Riesgo** El arranque de la imagen de `barber` ejecuta `migrate --force`. Recrear una tarea puede modificar datos: antes de publicar confirma las migraciones pendientes y que existe un respaldo restaurable.

## 6. Secretos y variables

| Tipo | Dónde vive | Cómo cambiarlo |
| --- | --- | --- |
| Secretos de `barber` | Secrets Manager (`urbanblade/staging/barber/…`): `APP_KEY`, `MONGODB_URI`, `ANALYTICS_MONGODB_URI`, claves de Stripe y Google, claves VAPID, credenciales de Firebase y contraseña de correo. | `put-secret-value` y luego forzar un despliegue (la tarea en marcha no relee el secreto). |
| Secretos de `spark` | Secrets Manager (`urbanblade/staging/spark/…`). | Igual. |
| Variables no secretas | Definición de tarea (`APP_URL`, `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, `TRUSTED_PROXY_HOPS`, `MAIL_*`, `OLLAMA_*`…). | Registrar una revisión nueva y actualizar el servicio (requiere un perfil con `ecs:RegisterTaskDefinition`). |

Reglas: nunca pasar un secreto como argumento de línea de comandos que quede en el historial, no pegarlo en un informe y mantener separados test, desarrollo y staging. El procedimiento de rotación por proveedor está en `docs/referencia/PLAN_ROTACION_CREDENCIALES.md`.

### 6.1 Variables críticas de la API

| Variable | Función | Si falta o es incorrecta |
| --- | --- | --- |
| `TRUSTED_PROXY_HOPS=2` | Dos proxies propios (CloudFront y ALB). | Todos los clientes comparten una IP y los límites por IP se vuelven globales. |
| `RUN_SCHEDULER=true` | Corre el planificador dentro del contenedor. | Sin ella no hay recordatorios ni tareas programadas. |
| `CACHE_LIMITER_STORE` y `SCHEDULE_CACHE_STORE` | Almacén compartido para límites y para el mutex del planificador. | Con varias réplicas las tareas se repiten y los límites se multiplican. |
| `CORS_ALLOWED_ORIGINS` | Orígenes web permitidos. | La web no puede llamar a la API. |

## 7. Datos: MongoDB Atlas, respaldos y archivos

### 7.1 Atlas

- Operación y analítica usan **conexiones y alcances distintos**. La web y Android nunca reciben credenciales de la base.
- Cada consumidor usa una credencial de mínimo privilegio: la API operativa lee y escribe en su base, la capa analítica solo lee, Spark lee el origen y publica derivados en una base aparte.
- Verifica periódicamente la lista de acceso de red y los privilegios efectivos **sin escribir documentos de prueba** en la base operativa.
- Los pagos y el inventario usan transacciones, que necesitan un replica set.

### 7.2 Respaldos y recuperación

| Elemento | Qué permite | Qué falta comprobar |
| --- | --- | --- |
| Exportación ZIP de la aplicación (`DatabaseBackupService`) | Copia de datos desde la aplicación. | Que incluya las colecciones esperadas; no conserva todos los índices. |
| Scripts de respaldo cifrado (`scripts/`) | Preparar, cifrar, publicar y ensayar copias. | Parámetros, permisos y que estén realmente programados. |
| Backup y recuperación de Atlas | Según el nivel contratado. | Qué está habilitado hoy en el clúster. |
| Ensayo de restauración | Prueba de que un respaldo se recupera. | Fecha, integridad, tiempos y destino aislado. |

Ni los ensayos sintéticos ni un RPO/RTO propuesto demuestran que exista un respaldo externo de datos operativos. **Acuerda y ensaya** RPO, RTO, retención y alertas. Al restaurar: verifica hash y origen, restaura primero en un destino aislado, valida índices y funciones críticas (no solo conteos) y acuerda el corte y la reversión antes de tocar datos operativos. Detalle en `docs/referencia/CONTINUIDAD_OPERATIVA.md`.

### 7.3 Archivos

Las imágenes públicas y los comprobantes usan buckets distintos. Los comprobantes se sirven con URL temporal de 15 minutos. El almacenamiento local de reserva comparte `storage/app/public` y **no** ofrece el mismo aislamiento que S3 privado.

## 8. Dominio, certificado y DNS

- El dominio `urbanblade.com.mx` se compró en GoDaddy; **el DNS lo atiende Route 53** y GoDaddy solo conserva los servidores de nombres. Los registros de GoDaddy ya no tienen efecto: edita únicamente en Route 53.
- El certificado de ACM (región `us-east-1`) cubre el dominio raíz, `www`, `api` y `spark` y se renueva solo mientras existan sus registros de validación en la zona. **No los borres.**
- Para agregar un subdominio: pide un certificado nuevo con la lista completa de nombres, asócialo y su alias a la distribución de CloudFront y crea el registro en Route 53.
- Para distinguir CloudFront de GoDaddy al diagnosticar, busca la cabecera `Via: … (CloudFront)`. Una respuesta 200 por sí sola no prueba nada.
- Renueva el dominio a tiempo (septiembre de 2027) y activa la renovación automática.

## 9. Usuarios de la aplicación

Los roles son **administrador, recepcionista, barbero, cliente e ingeniero**. El registro público y Google solo crean clientes; el personal lo da de alta un administrador desde la aplicación. Reglas:

- No publicar contraseñas temporales ni ejecutar `tinker` con credenciales en el comando.
- Un usuario eliminado lógicamente puede seguir ocupando su correo en el índice único; no uses `forceDelete` como solución rutinaria.
- En producción mantén `FIRST_USER_ADMIN_ENABLED=false` y crea el administrador con el seeder.

## 10. Seguridad

| Control | Qué verificar |
| --- | --- |
| Red | El ALB solo acepta tráfico de CloudFront; grupos de seguridad por puerto. |
| HTTPS | CloudFront con ACM; HSTS en la web. |
| IAM | Mínimo privilegio, un usuario por persona, claves rotadas. |
| Secretos | Solo en Secrets Manager; escaneo de secretos en CI (Gitleaks). |
| Imágenes | Escaneo con Trivy en CI. |
| Comprobantes | Bucket privado y URL temporal. |
| Bucket de imágenes | Lectura pública de objetos: confirma que solo contiene imágenes públicas. |
| Webhook de Stripe | Verificación de firma; el destino debe estar en la misma cuenta que emite las claves. |

## 11. Respuesta a incidentes

| Situación | Pasos |
| --- | --- |
| "La app no responde" o 502/504 | 1) `describe-services`: ¿`running` = `desired`? 2) Revisa los logs al arrancar. 3) Revisa el estado de salud del grupo de destino. 4) No invalides caché: CloudFront no cachea nada aquí. |
| Servicio atascado (0 tareas, despliegues abiertos, sin eventos) | Pasó con el frontend el 24-oct-2026 por una tarea Spot interrumpida. Respalda la configuración del servicio y recréalo con autorización del propietario. |
| Recordatorios repetidos | Hay más de una réplica con planificador sin almacén compartido: aplica `SCHEDULE_CACHE_STORE` o deja una réplica. |
| Se expuso un secreto | No copiar el valor. Informar por el canal privado, verificar si es real, rotar con el procedimiento y evaluar historial y logs. Borrar el archivo no revoca la credencial. |
| Pagos que no concilian | Revisa que el webhook de Stripe apunte a `https://api.urbanblade.com.mx/api/stripe/webhook` y que el secreto de firma sea el del destino. |
| Correo que no llega | Ejecuta `php artisan urbanblade:mail-check <correo>`; revisa que el mailer no sea `log` y el límite diario de Gmail (~500). |

## 12. Lo que el administrador no hace

- No ejecuta migraciones, semillas ni limpiezas de colecciones sin confirmar la base y tener respaldo.
- No borra recursos para "recuperar salud": usa el procedimiento de reversión acordado.
- No desactiva las guardas de entorno para hacer pasar un error.
- No reescribe el historial publicado de Git ni fuerza pushes.
