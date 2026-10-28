# Mantenimiento

Plan y procedimientos de mantenimiento preventivo, correctivo y evolutivo de UrbanBlade.

| Dato | Valor |
| --- | --- |
| Documento | Manual de Mantenimiento de UrbanBlade |
| Versión | 2.0 · 28 de octubre de 2026 |
| Audiencia | Equipo de desarrollo y quien opera el sistema |
| Fuente | Flujo de trabajo del repositorio, CI/CD, `docs/referencia/PRUEBAS.md`, `docs/referencia/CONTINUIDAD_OPERATIVA.md` y los hallazgos documentados en `docs/referencia/DECISIONES.md` |
| Clasificación | Uso interno |

## 1. Objetivo y tipos de mantenimiento

Mantener UrbanBlade **correcto, seguro y recuperable** con el menor riesgo para los datos. Todo cambio, sin importar su tamaño, sigue el mismo flujo: rama, pruebas, PR con checks verdes y publicación como decisión aparte.

| Tipo | Cuándo | Ejemplos |
| --- | --- | --- |
| Preventivo | Antes de que algo falle. | Actualizar dependencias, revisar auditorías, ensayar una restauración, rotar credenciales. |
| Correctivo | Cuando algo falla. | Corregir un error, un despliegue fallido o una alerta de seguridad. |
| Evolutivo | Cuando se agrega valor. | Nuevas funciones, reportes, pantallas. |
| Adaptativo | Cuando cambia el entorno. | Nueva versión de Laravel o Nuxt, cambios de un proveedor, nuevas políticas de la plataforma. |

## 2. Flujo estándar de cambio

1. **Revisar el estado:** `git status --short --branch` y los cambios ajenos que ya existan.
2. **Rama nueva** desde `origin/main`, con un nombre que describa el cambio.
3. **Validar el alcance** con los comandos del capítulo 4.
4. **Staging selectivo** de los archivos del cambio y revisión de `git diff --cached`.
5. **Commit en español** con título `tipo(alcance): descripción` (por ejemplo `fix(scheduler): …`).
6. **PR a `main`** con la plantilla del repositorio: qué cambia, alcance, validación y revisión.
7. **Checks verdes** en el SHA actual. Se conserva la rama de trabajo.
8. **Fusionar** y, solo si el propietario lo ordena, **publicar** (manual de Instalación y Despliegue).

> **Importante** Subir, fusionar y desplegar son pasos distintos. Un CI verde no despliega, y un cambio solo de documentación no se publica. Ten en cuenta que en el frontend un archivo `.md` puede disparar una vista previa de Vercel.

## 3. Calendario de mantenimiento preventivo

| Frecuencia | Tarea | Responsable |
| --- | --- | --- |
| Cada cambio | Pruebas del alcance, `pint`, verificación de documentos y revisión del diff. | Quien cambia |
| Cada PR | Esperar los checks del CI: Pint, Larastan, PHPUnit con cobertura, auditorías, Gitleaks, Trivy y smoke HTTP. | Quien cambia |
| Semanal | Revisar logs y errores (Pulse, Sentry opcional, CloudWatch); confirmar `running` = `desired`. | Administrador de sistemas |
| Semanal | Revisar avisos de Dependabot y de la auditoría de dependencias. | Desarrollo |
| Mensual | Revisar costos en Cost Explorer. | Administrador de sistemas |
| Mensual | Revisar el vencimiento de excepciones de la auditoría de npm (`audit:gate`). | Desarrollo |
| Trimestral | Ensayar una restauración de respaldo en un destino aislado. | Administrador de sistemas |
| Trimestral | Revisar permisos de IAM y de Atlas, y la lista de acceso de red. | Administrador de sistemas |
| Anual | Renovar el dominio (septiembre) y comprobar el certificado de ACM. | Administrador de sistemas |
| Ante un incidente | Rotar las credenciales afectadas (`docs/referencia/PLAN_ROTACION_CREDENCIALES.md`). | Propietario |

## 4. Comandos de verificación

### 4.1 Backend (`barber`)

```powershell
.\test.ps1 --compact              # suite completa (única forma segura en local)
.\test.ps1 --testsuite=UAT        # un tipo: UT, SIT, UAT u OAT
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G --configuration=phpstan.neon.dist
python scripts/check_docs.py --all
git diff --check
```

> **Aviso** No ejecutes `php artisan test` directamente: una configuración cacheada con valores reales ya borró datos una vez. `test.ps1` fuerza `.env.testing` y la base `barber_db_test`.

### 4.2 Frontend (`frontend-urban`)

```powershell
npx --no-install eslint . --max-warnings=0
npm run test:ut                   # Vitest
npm run test:sit ; npm run test:uat ; npm run test:oat   # Playwright por tipo
npm run contract:check            # tipos contra OpenAPI
npm run build
npm run audit:gate
python scripts/check_docs.py --all
```

## 5. Dependencias y actualizaciones

- **Composer y npm:** las auditorías corren en el CI. Un aviso `high` o `critical` bloquea; las excepciones del frontend tienen fecha de vencimiento y se renuevan o se resuelven.
- **Laravel y Nuxt:** una actualización mayor es mantenimiento adaptativo: rama aparte, suite completa y revisión de `phpstan-baseline.neon` (los textos de los mensajes cambian entre versiones).
- **Imágenes:** el Dockerfile del frontend usa Node 22 y `npm install`, mientras que el CI usa Node 24 y `npm ci --legacy-peer-deps`. La diferencia está documentada y no se ha unificado.
- **Dependabot** abre PR de dependencias: revisa el cambio y deja pasar el CI antes de fusionar.

## 6. Mantenimiento de datos

| Tarea | Detalle |
| --- | --- |
| Caducidades automáticas | Tokens vencidos, pedidos sin recoger (3 días), lista de espera y paquetes vencidos, puntos de lealtad (365 días) se limpian con las tareas programadas. |
| Integridad | `php artisan data:verify-integrity` revisa la consistencia de los datos y falla si supera `--max-orphans`; ejecútalo en un entorno aislado. |
| Registros huérfanos | `php artisan data:cancel-orphans` muestra lo que haría; solo con `--apply` cancela de verdad. |
| Cálculos derivados | `clients:sync-loyalty-counters` y `payments:sync-totals` admiten `--dry-run` para ver el cambio antes de escribir. |
| Roles | `validate:user-roles` comprueba la coherencia de los roles de los usuarios. |
| Índices | Antes de cambiar un índice: confirmar origen, destino, respaldo y autorización. |
| Respaldos | Ver `docs/referencia/CONTINUIDAD_OPERATIVA.md`. Un respaldo cuenta cuando se ensayó su restauración. |

Usa siempre primero la variante de simulación (`--dry-run`, o `data:cancel-orphans` sin `--apply`). No ejecutes `migrate:fresh`, no borres volúmenes ni limpies colecciones operativas para preparar una prueba.

## 7. Mantenimiento de la infraestructura

- **Escalado de la API:** al usar más de una réplica de `barber`, define `CACHE_LIMITER_STORE=mongodb` y `SCHEDULE_CACHE_STORE=mongodb`; sin ellos el planificador se ejecuta por duplicado y los límites se multiplican.
- **Imágenes antiguas en ECR:** conserva las etiquetas de respaldo recientes y evalúa una política de ciclo de vida antes de borrar nada.
- **Terraform:** el repositorio de infraestructura como código describe CloudFront, S3, Atlas, red y ECR. Su workflow de aplicación está **pausado** a petición del propietario; antes de reactivarlo debe reflejar el estado actual (frontend en FARGATE con el servicio recreado). Cualquier `apply` requiere aprobación.
- **Spot:** `spark` y `ollama` usan capacidad Spot y pueden interrumpirse; el servicio las repone solo.

## 8. Mantenimiento correctivo

### 8.1 Procedimiento ante un error

1. **Reproducir** de forma acotada, sin datos reales ni credenciales.
2. **Escribir primero una prueba** que falle y exprese el comportamiento correcto.
3. **Corregir** con el cambio mínimo y comprobar que la prueba pasa.
4. **Correr la suite del alcance** y el CI.
5. **Documentar** la causa en el PR (qué falló y por qué), no solo el arreglo.

No cambies fixtures ni expectativas solo para ocultar un fallo, y no amplíes el baseline de PHPStan sin revisar el motivo.

### 8.2 Fallos conocidos y su solución

| Fallo | Causa | Solución |
| --- | --- | --- |
| El CI aborta con "Maximum execution time of 60 seconds" en una prueba ajena. | `ChatbotController` y `PredictionController` llaman a `set_time_limit(60|120)` y el límite sobrevive en el mismo proceso. | `tests/TestCase.php` reinicia el límite con `set_time_limit(0)` en cada prueba. |
| Recordatorios o avisos repetidos. | Varias réplicas con planificador sin almacén compartido. | `onOneServer()` más `SCHEDULE_CACHE_STORE`. |
| Despliegue de `barber` que no estabiliza. | Imagen construida con el Dockerfile de desarrollo. | Reconstruir con `.docker/staging/Dockerfile`. |
| Servicio de ECS sin tareas y sin eventos. | Tarea Spot interrumpida. | Respaldar la configuración del servicio y recrearlo. |
| Una prueba se ejecuta contra la base equivocada. | Configuración cacheada con valores reales. | Usar siempre `test.ps1`; las guardas de entorno abortan el proceso. |
| Análisis de SonarCloud en rojo por duplicación tras mover pruebas. | Los archivos movidos cuentan como código nuevo. | Excluir las carpetas de pruebas de la duplicación en la configuración del proyecto de SonarCloud. |

## 9. Mantenimiento evolutivo

Toda función nueva nace de una **historia de usuario** del Product Backlog o de una historia técnica. Añade siempre:

- Cambios **aditivos** en la API: es un contrato real para la web y Android. Actualiza OpenAPI y regenera los tipos del frontend (`contract:sync`).
- Pruebas del tipo que corresponda (UT, SIT, UAT u OAT) y, si cambia comportamiento, el caso afectado y un error relevante.
- Documentación: el manual correspondiente y `docs/referencia/PRUEBAS.md` si cambia la matriz de regresiones.
- Una revisión de seguridad si toca autenticación, pagos, perfiles, archivos o rutas con roles.

## 10. Mantenimiento de la documentación

- Los manuales viven en `docs/manuales/`: cada carpeta tiene su `.md` (fuente) y su `.docx` (entregable con el diseño del equipo).
- Para regenerar los Word: `cd scripts/docs`, `npm install` y `node generar-word.js`.
- Las guías de despliegue y operación **no se borran ni se recortan**: se actualizan agregando o corrigiendo. Antes de reescribir una guía operativa, compárala con `git show HEAD:<ruta>` y respáldala.
- `python scripts/check_docs.py --all` valida los enlaces locales en cada PR.
- Fecha cada revisión y distingue lo verificado en el código de lo verificado en AWS.

## 11. Lista de comprobación de una versión

- [ ] Rama desde `origin/main` y PR con la plantilla completa.
- [ ] Suite completa en verde (UT, SIT, UAT, OAT), Pint y PHPStan.
- [ ] Contrato OpenAPI y tipos del frontend sincronizados.
- [ ] Documentación actualizada y enlaces verificados.
- [ ] Sin secretos ni datos personales en el diff.
- [ ] Checks del PR verdes (o excepción documentada y aprobada).
- [ ] Imagen de respaldo etiquetada antes de publicar.
- [ ] Autorización del propietario para fusionar y para publicar.
- [ ] Verificación posterior: `services-stable`, `/up` y recorrido por rol.

## 12. Deuda técnica y pendientes conocidos

| Tema | Detalle |
| --- | --- |
| Duplicación del planificador en staging | Corregida en el código; falta aplicar la variable en AWS con un perfil que pueda registrar definiciones de tarea. |
| Terraform | Workflow pausado; pendiente reflejar el estado actual antes de reactivarlo. |
| Scribe | `auth.enabled=false`: no cambia la protección real, pero puede generar una introducción de autenticación incorrecta. |
| Sin Redis en staging | Caché en archivo y colas síncronas; escalar exige almacenes compartidos. |
| Respaldos | Falta un RPO/RTO acordado y un ensayo de restauración registrado. |
| Bucket de imágenes | Lectura pública de objetos; confirmar su contenido. |
| Dockerfile del frontend | Node 22 y `npm install` frente a Node 24 y `npm ci` en CI. |
| Pruebas Playwright omitidas | Dos pruebas se omiten en la ejecución completa (por ejemplo las de auditoría UI con variable de activación). |
| SonarCloud | Falla la duplicación en código nuevo tras reorganizar pruebas; falta excluir las carpetas de prueba en su configuración. |
