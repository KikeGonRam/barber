---
name: despliegue-staging
description: Procedimiento para desplegar barber o frontend a staging con el pipeline de GitHub Actions: lanzar el workflow, esperar la aprobación del propietario, verificar el resultado y guardar la copia local de la imagen. Úsalo cuando el propietario ordene un despliegue.
---
<!-- Generado desde urbanblade-equipo. No editar aquí: cambia el origen y vuelve a sincronizar. -->

# Despliegue a staging

Sustituye al procedimiento manual anterior. Fuentes: `docs/referencia/CI_CD.md` y `docs/referencia/RUNBOOK_INCIDENTES_STAGING.md` del backend, y `docs/CI_CD.md` del frontend.

| Servicio | Repo de GitHub | ECR | Servicio de ECS | Se verifica |
| --- | --- | --- | --- | --- |
| barber | `KikeGonRam/barber` | `urbanblade/barber` | `uba-stg-barber` | `https://api.urbanblade.com.mx/up` y las pruebas de humo |
| frontend | `KikeGonRam/frontend_Urbanblade` | `urbanblade/frontend-urban` | `uba-stg-frontend` | `https://urbanblade.com.mx/api/health` y las pruebas de humo |

## Pasos
1. **Orden expresa del propietario** para este despliegue. Si no la hay, no sigues.
2. **CI en verde del SHA exacto:** `gh run list --repo <repo> --workflow CI --limit 3 --json status,conclusion,headSha`. Si el CI sigue corriendo, espera. Un despliegue lanzado antes falla con «El CI de <sha> no terminó en verde»: no es un daño, pero hay que relanzarlo.
3. **Lanzar:** `gh workflow run desplegar-staging.yml --repo <repo> --ref main`. Comparte el enlace de la ejecución.
4. **Pedir la aprobación:** el propietario debe pulsar «Review deployments». Tú no la das, ni con la API ni con un token.
5. **Esperar y verificar:** conclusión de la ejecución, pasos de verificación y de humo, etiquetas en ECR (`latest`, `sha-<corto>` y la `rollback-…` del despliegue), `rolloutState` de ECS en `COMPLETED` con las tareas esperadas, y las URL del sitio.
6. **Copia local, solo si salió bien:** desde `C:\Users\luis1\Documents\UrbanBlade\_deploys`, `.\guardar-imagen.ps1 -Servicio barber -Sha <sha7> -DesdeEcr` (o `-Servicio frontend-urban`). Esa carpeta no es un repositorio.

## Si no hay nada que desplegar
Un commit que solo cambia documentación, `.github/`, pruebas o archivos de agentes no entra a la imagen: el job «¿El commit cambia la imagen?» lo detiene sin pedir aprobación. Repórtalo; no fuerces un despliegue.

## Si algo falla
- **Antes de AWS** (CI no verde, credenciales vacías): reporta el paso y la causa. Nada cambió.
- **Después de AWS:** el workflow revierte solo. Confirma que ECS volvió a la versión anterior y reporta qué falló.
- **Migraciones o seeders:** el workflow se detiene; el propietario decide si lanza a mano con `confirmar_migraciones`.
- **Reversión manual:** cambia `latest` en ECR; la propones con el comando del runbook y la ejecuta solo quien tenga orden del propietario.

## Reporte final
Servicio, SHA, resultado del CI y de la ejecución, etiqueta de respaldo, estado de ECS y de las URL, copia local guardada, e incidentes. Lo que no verificaste, dilo tal cual.
