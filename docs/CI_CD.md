# CI/CD del backend

## Comprobaciones existentes

| Workflow | Comprobaciones |
| --- | --- |
| [CI](../.github/workflows/ci.yml) | Pint, Larastan, PHPUnit con cobertura, auditorías Composer/npm, ESLint, Vite, Gitleaks, Trivy y smoke HTTP. |
| [Docker Image CI](../.github/workflows/docker-image.yml) | Build de imágenes de desarrollo/staging y comprobación de versión mínima de PHP. |
| [Documentación](../.github/workflows/documentation.yml) | Enlaces locales de los documentos de entrada. |

CI prepara MongoDB como replica set y Redis efímeros.
Esto es distinto del contenedor de desarrollo, que puede tener variables de
Atlas: **en local usa exclusivamente `test.ps1`**, tras verificar el montaje y
la conexión a `mongo-test` / `barber_db_test`.

## Límites del mantenimiento

Esta actualización se limita a Markdown, plantillas, validación documental y
configuración de CI. No cambia lógica de negocio, dependencias del producto,
Dockerfiles, variables de entorno, contrato de API ni infraestructura.

Los workflows versionados revisados se disparan con `push` a `main` o
`pull_request` hacia `main`. Construir una imagen en un runner no la publica.
No contienen `docker push`, actualización de ECS ni un trabajo de despliegue.
Los cambios documentales también ejecutan la CI existente: no se añaden filtros
que puedan dejar checks requeridos pendientes o saltarse controles de seguridad.

Esto no confirma la configuración de integraciones externas como Vercel,
webhooks o pipelines configurados fuera del repositorio. Antes de fusionar,
revisa esas conexiones. Durante este mantenimiento no se despliega, publica
ni ejecutan comandos de infraestructura.

## Comprobación documental

`python scripts/check_docs.py` comprueba README, contribución, seguridad,
índice documental, esta guía, la plantilla de PR y la guía de clonado.
Detecta archivos vacíos, conflictos de merge y destinos locales inexistentes
en enlaces Markdown en línea e imágenes/enlaces HTML.
Omite bloques de código, URLs externas y anclas; no comprueba sintaxis Markdown
completa, enlaces de referencia ni vigencia semántica de todas las guías.

Para revisar otros documentos explícitamente:

```powershell
python scripts/check_docs.py docs/README.md docs/CI_CD.md
```

El workflow `documentation.yml` usa Python del runner y no instala dependencias.
Se ejecuta con PR/push a `main` para no omitir un check si se configura como requerido.
Los workflows del producto mantienen sus nombres de jobs. Los límites de tiempo
evitan ejecuciones atascadas; la concurrencia cancela corridas anteriores del
mismo PR, conservando las corridas de `main`.

## Evidencia y publicación

En cada PR registra comandos y resultados del checkout probado. Distingue:
validación local, checks de GitHub, fusión y publicación.
Ninguno implica automáticamente el siguiente.

El badge del README enlaza al workflow del repositorio; no representa un
certificado del commit de una rama local.

No se modifica la licencia ni se renombran repositorios durante este mantenimiento.

## Presentación en GitHub

Descripción de About:

> API de UrbanBlade para barberías: citas, pagos, inventario y reportes con Laravel, MongoDB, Redis y Stripe.

Temas configurados: `laravel`, `php`, `mongodb`, `redis`, `rest-api`,
`barbershop`, `stripe`. Sitio del producto: https://urbanblade.com.mx/.

La revisión inicial encontró una descripción del antiguo dashboard web, sin
temas ni sitio en About. El propietario autorizó actualizar estos metadatos;
los valores anteriores ya se aplicaron y verificaron mediante la API de GitHub.

Los procedimientos operativos permanecen en
[DESPLIEGUE_AWS_STAGING.md](DESPLIEGUE_AWS_STAGING.md).
Sus fechas, nombres históricos de entorno y verificaciones no acreditan el
estado actual de producción.

## Verificación local del mantenimiento · 27 de octubre de 2026

- Base revisada: `origin/main` en `c1ebede`.
- `.\test.ps1 --compact`: 951 pruebas aprobadas, 3,679 aserciones.
  Se verificó el montaje de `barber-app` y el destino `mongo-test` / `barber_db_test`.
- Validador documental: siete documentos de entrada aprobados y 15 casos
  positivos/negativos del validador comprobados en archivos temporales.
- Enlaces locales: 23 Markdown públicos versionados revisados sin destinos faltantes.
- YAML: workflows y formularios de issues parseados sin errores.
  Se compararon los jobs anteriores: solo cambian sus límites de tiempo.
- `git diff --check`: sin errores.

Esta evidencia es local. Los workflows nuevos aún requieren ejecutarse en el PR.
No se reconstruyeron imágenes ni se ejecutaron despliegues en esta actualización.
