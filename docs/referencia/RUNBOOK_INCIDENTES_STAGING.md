# Runbook de incidentes y reversión de staging

Qué hacer cuando suena una alarma, cuándo revertir y cómo hacerlo, con el pipeline actual (despliegue con aprobación, pruebas de humo y reversión automática). Los pasos del flujo manual anterior siguen en [DESPLIEGUE_AWS_STAGING.md](DESPLIEGUE_AWS_STAGING.md) y quedan como último recurso. Los controles del pipeline están en [CI_CD.md](CI_CD.md); los respaldos de datos, en [CONTINUIDAD_OPERATIVA.md](CONTINUIDAD_OPERATIVA.md).

Los comandos usan el usuario IAM `staging-deploy`, la región `us-east-1`, el clúster `urbanblade-staging` y los servicios `uba-stg-barber` y `uba-stg-frontend`. Están escritos para PowerShell 7. En Git Bash, anteponer `MSYS_NO_PATHCONV=1` a los comandos con rutas que empiezan por `/` (grupos de logs).

## 1. Primer vistazo (2 minutos)

```powershell
aws ecs describe-services --cluster urbanblade-staging --services uba-stg-barber uba-stg-frontend --query "services[].[serviceName,runningCount,desiredCount,deployments[0].rolloutState]" --output table
```

- `runningCount` menor que `desiredCount`: el servicio no logra arrancar tareas. Ir al apartado 3.
- Todo igual y sin error, pero el sitio falla: mirar la salud de los target groups y los logs (apartado 4).
- Comprobación externa rápida: `https://urbanblade.com.mx/api/health` (frontend) y `https://api.urbanblade.com.mx/up` (API).
- Panel `urbanblade-staging` en CloudWatch: hosts saludables, errores 5xx, latencia, CPU y memoria.

## 2. Qué significa cada alarma

Las alarmas llegan por correo desde el tema `urbanblade-staging-alarmas` (definidas en urbanbladeTerraform, `alarmas.tf`).

| Alarma | Qué indica | Primer paso |
| --- | --- | --- |
| `<servicio>-sin-hosts-saludables` | Ningún host sano durante 3 minutos: el servicio está caído. | Apartados 1 y 3. Si coincide con un despliegue reciente, revertir (apartado 5). |
| `<servicio>-errores-5xx` | 10 o más 5xx de la aplicación en 5 minutos. | Logs del servicio (apartado 4); si empezó con un despliegue, revertir. |
| `alb-errores-5xx` | 5 o más 502, 503 o 504 del balanceador: no llegó a ninguna tarea. | Revisar tareas y salud del target group. |
| `<servicio>-latencia-p95` | p95 mayor que 3 s durante 15 minutos. | Mirar CPU, memoria y logs; descartar la base de datos (Atlas). |
| `<servicio>-memoria-alta` | Memoria mayor que 85 % durante 15 minutos. | Posible fuga: reiniciar el servicio (apartado 6) y vigilar. |

Cuando el problema se resuelve llega un segundo correo de «OK». Una alarma que se dispara justo durante un despliegue y se normaliza sola en minutos es esperable; no revertir por eso.

## 3. El servicio no arranca tareas

```powershell
aws ecs describe-services --cluster urbanblade-staging --services uba-stg-barber --query "services[0].events[:6].[createdAt,message]" --output text
```

Causas habituales: secreto o variable faltante (la tarea muere al arrancar), imagen que no existe en ECR, o falta de capacidad. Si empezó con un despliegue, revertir (apartado 5) y diagnosticar después con calma.

## 4. Logs y salud

```powershell
aws logs tail /ecs/urbanblade-staging/barber --since 30m
aws logs tail /ecs/urbanblade-staging/frontend-urban --since 30m
```

Salud de los hosts de un target group (los ARN salen de `aws elbv2 describe-target-groups`):

```powershell
aws elbv2 describe-target-health --target-group-arn <arn-del-target-group> --query "TargetHealthDescriptions[].[Target.Id,TargetHealth.State,TargetHealth.Reason]" --output table
```

## 5. Revertir un despliegue

**Reversión automática.** Si después de desplegar fallan la verificación de salud o las pruebas de humo, el workflow vuelve solo a la imagen anterior y el resumen de la ejecución lo dice. No hace falta hacer nada más que revisar por qué falló.

**Reversión manual.** Cada despliegue deja la imagen anterior con la etiqueta `rollback-<fecha>-pre-<sha>` en ECR. Para volver a ella (ejemplo con barber; para el frontend cambiar `urbanblade/frontend-urban` y `uba-stg-frontend`):

```powershell
aws ecr describe-images --repository-name urbanblade/barber --query "sort_by(imageDetails,&imagePushedAt)[-6:].[imageTags,imagePushedAt]" --output table
```

Elegir la etiqueta `rollback-...` más reciente que corresponda al despliegue que se quiere deshacer y ejecutar:

```powershell
$repo = "urbanblade/barber"; $etiqueta = "rollback-AAAAMMDD-pre-xxxxxxx"
$manifiesto = aws ecr batch-get-image --repository-name $repo --image-ids imageTag=$etiqueta --query "images[0].imageManifest" --output text
aws ecr put-image --repository-name $repo --image-tag latest --image-manifest $manifiesto
aws ecs update-service --cluster urbanblade-staging --service uba-stg-barber --force-new-deployment
aws ecs wait services-stable --cluster urbanblade-staging --services uba-stg-barber
```

Después comprobar el apartado 1. Si el manifiesto llega vacío (`None`), la etiqueta no existe o fue limpiada por la política de ciclo de vida de ECR (conserva las 10 más recientes `rollback-*`); en ese caso hay copias locales en el equipo del propietario (carpeta `_deploys`, ver su `README.md`) que se cargan con `docker load` y se suben con `docker push`.

Una reversión cambia `latest` pero no el repositorio de código: dejar `main` como está y abrir un PR correctivo o de reversión (`git revert`) antes del siguiente despliegue, o el mismo defecto volverá a desplegarse.

## 6. Reiniciar o escalar un servicio

```powershell
aws ecs update-service --cluster urbanblade-staging --service uba-stg-barber --force-new-deployment
aws ecs update-service --cluster urbanblade-staging --service uba-stg-barber --desired-count 1
```

El autoescalado mantiene entre 1 y 2 tareas (CPU al 60 %). Si se fuerza `desired-count 0` para apagar, el autoescalado puede volver a subirlo; para apagar de verdad hay que bajar también la capacidad mínima. Ollama tiene su propio servicio, apagado y fuera del autoescalado.

## 7. Un despliegue no arranca o se detiene

| Mensaje del workflow | Significado y acción |
| --- | --- |
| «El CI de `<sha>` no terminó en verde; no se despliega.» | Se aprobó antes de que terminara el CI, o falló. Esperar al CI y relanzar el despliegue manual. |
| «Hay migraciones o seeders…» | El despliegue se detiene a propósito. Revisarlas y lanzar a mano con `confirmar_migraciones`. |
| El job se queda en «waiting» | Falta la aprobación del propietario en el entorno `staging` («Review deployments»). Rechazarlo cancela el despliegue sin tocar AWS. |
| Las pruebas de humo fallan | La API o el sitio no devuelven lo esperado; el workflow ya revirtió. Leer el paso fallido en la ejecución. |

Despliegue manual: Actions, «Desplegar a staging», «Run workflow» sobre `main`. Siempre pide aprobación.

## 8. Límites

- Las alarmas avisan por correo al propietario; no hay guardia 24 horas.
- Los identificadores del ALB y de los target groups están escritos en `alarmas.tf`: si se recrean, actualizarlos.
- Restaurar datos de MongoDB Atlas no se cubre aquí; ver [CONTINUIDAD_OPERATIVA.md](CONTINUIDAD_OPERATIVA.md) y [MONGODB_ATLAS.md](MONGODB_ATLAS.md).
