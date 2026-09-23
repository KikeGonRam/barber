---
name: urbanblade-historias-sprint
description: Forma de trabajo de Urban Blade desde el 23-sep-2026 — todo trabajo sale de una historia de usuario (HU/T) del Product Backlog o de una historia técnica (HT/TT) asignada, dentro de su sprint. Usar al iniciar cualquier sesión de trabajo en este repositorio, antes de proponer o escribir código, al elegir qué hacer, al preparar un commit o al reportar avance.
---

# Trabajo guiado por historias (Urban Blade)

Desde el 23-sep-2026 el equipo trabaja **solo** con lo que se encargó en el
cuatrimestre: historias de usuario (HU-xx, tareas Txxx) e historias técnicas (HT-xx,
tareas TTxx). Nada de funciones nuevas, rediseños ni refactors "de paso" si no están
ligados a una tarea.

Esta skill existe con el mismo nombre en los cuatro repositorios (`barber`,
`frontend-urban`, `UrbanBladeMobile`, `spark`). El núcleo es idéntico; cambia solo la
sección **"Tareas de este repositorio"**. Si cambias el núcleo, cámbialo en los cuatro.

## Fuente de verdad

Carpeta `C:\Users\luis1\Documents\UrbanBlade\DOCUMENTACION\` (no es repositorio Git):

| Archivo | Para qué |
|---|---|
| `Product_Backlog_Urban_Blade_UBv3_Pendiente.xlsx` | **Tablero de trabajo**: 17 HU, 42 tareas, 74 pts pendientes. Hoja «Tablero de Tareas» = estado real (col. N). |
| `Historias_Tecnicas_Urban_Blade.xlsx` | HT-01 a HT-14, TT01 a TT37 (55 pts), ligadas al checklist y casos de prueba. |
| `Product_Backlog_Urban_Blade_UBv2.xlsx` | Registro completo con evidencia (incluye lo ya completado). |
| `UrbanBlade_Checklist_250_v2.xlsx`, `Casos_de_Prueba_Urban_Blade_v2.xlsx` | Pruebas que alimentan las HT. |

Si la tabla de esta skill contradice los Excel, **ganan los Excel** (esta skill es una
foto al 23-sep-2026). Para editar los Excel usar la skill `urbanblade-product-backlog`
(en `barber`).

## Calendario

| Sprint | Fechas | Notas |
|---|---|---|
| 1 | 14–25 sep | |
| 2 | 28 sep–9 oct | Terraform empieza el **5-oct** |
| 3 | 12–23 oct | |
| 4 | 26 oct–6 nov | CI/CD y Continuous Delivery desde el **26-oct** (≥ 25-oct) |
| 5 | 9–20 nov | |
| 6 | 23 nov–4 dic | Cierre, pruebas finales y documentación |

Puntos 1, 3 o 5; 1 punto = 8 horas. Estados: Pendiente, En progreso, Completado.

## Equipo

| Integrante | Rol |
|---|---|
| Alan Ruiz Vilchis | Product Owner / Backend |
| Elías García Nolasco | Scrum Master / Frontend |
| Miguel Ángel Mena Garduño | QA Tester / Frontend |
| Luis Enrique González Ramírez | Full Stack / Mobile |
| María Isabel Cruz Flores | Backend / Administradora de BD |

## Flujo obligatorio por tarea

1. **Identificar la tarea.** Pregunta o deduce quién es el usuario y busca su tarea en
   la tabla de abajo o en el Excel. Si lo pedido no corresponde a ninguna HU/HT, dilo y
   propón a qué tarea ligarlo o que el PO (Alan) la agregue; no lo hagas "por fuera".
2. **Revisar dependencias.** Si la tarea depende de otra que no está Completada
   (columna "Depende de"), no la adelantes en `main`; avisa a quién se espera.
3. **Leer los criterios de aceptación** de la HU/HT (hoja «Product Backlog» o
   «Historias Técnicas»). La tarea está hecha cuando se cumplen, no cuando compila.
4. **Revisar lo existente** en el código antes de escribir: muchas cosas ya existen a
   medias. No reinventar ni duplicar.
5. **Implementar lo mínimo** que cumple el criterio, con sus pruebas.
6. **Validar** con los comandos de este repositorio (sección de abajo). Sin validación
   no hay commit.
7. **Entregar al usuario**: resumen, archivos, resultado de pruebas y los comandos
   completos para PowerShell (`cd` al repo, `git add` con rutas, `git commit`,
   `git push origin main`). El mensaje de commit va en español, con el ID de la tarea:
   `feat(pagos): T144 mensajes claros al cancelar el pago con tarjeta`.
   **La IA no ejecuta `git commit` ni `git push`**: solo el usuario, y solo en `main`.
8. **Marcar el estado con evidencia**: una tarea pasa a *Completado* solo con evidencia
   verificable (commit en `main` + prueba que pasa, o registro de QA). Código subido sin
   probar = *En progreso*. "NO INVENTES AVANCE".

## Reglas de alcance

- Tecnología real: Laravel 13 + MongoDB (`laravel-mongodb`), token Bearer propio
  (`mobile_api_tokens`, no JWT ni Sanctum), Nuxt 4, Android nativo Kotlin + Jetpack
  Compose. Nunca Next.js, Expo, Mongoose ni JWT.
- `mobil` (Expo) está descontinuado; no es evidencia ni se toca.
- La API `/api/v1` es contrato para web y Android: cambios aditivos, con prueba.
- Terraform: aún no existe en ningún repositorio; su ubicación la define **T112**
  (Alan). No crear archivos `.tf` antes de que T112 esté en `main`.
- Secretos: nunca en código, `.tf`, docs ni en la app Android (solo claves públicas
  como `pk_test_...`). `docs/ACCESOS.md` de `barber` es la única fuente de credenciales.
- Diseños (Stitch, Figma) se revisan contra la HU que implementan antes de codificar.

## Tareas de este repositorio (barber)

| Sprint | ID | Historia | Tarea | Pts | Responsable | Estado 23-sep | Depende de |
|---|---|---|---|---|---|---|---|
| 2 | T143 | HU-16 Recordatorio de citas | Registrar los tokens de dispositivo Android y enviar los recordatorios push desde el backend | 3 | Alan | Pendiente | — |
| 2 | TT01 | HT-01 Expiración de tokens y cierre por inactividad | Definir la vigencia del token y asignar expires_at al emitirlo (login, registro, Google y refresh) | 3 | Alan | Pendiente | — |
| 2 | TT02 | HT-01 Expiración de tokens y cierre por inactividad | Agregar pruebas PHPUnit de token vencido (401) y renovación | 1 | Miguel | Pendiente | TT01 |
| 2 | TT04 | HT-02 Recuperación de contraseña segura | Responder con mensaje genérico en forgot-password sin importar si el correo existe | 1 | Alan | Pendiente | — |
| 2 | TT05 | HT-02 Recuperación de contraseña segura | Configurar la expiración del token de recuperación a 15 minutos | 1 | María | Pendiente | — |
| 2 | TT06 | HT-02 Recuperación de contraseña segura | Agregar pruebas PHPUnit de recuperación (correo inexistente y token vencido) | 1 | Miguel | Pendiente | TT04, TT05 |
| 2 | TT35 | HT-14 Documentación de gestión y normatividad | Elaborar la matriz RACI de pruebas y el plan de comunicación | 1 | Elías | Pendiente | — |
| 3 | TT07 | HT-03 Endurecimiento de login y registro | Aplicar una política de complejidad de contraseñas (Password::defaults) en registro y cambio de contraseña | 1 | María | Pendiente | — |
| 3 | TT08 | HT-03 Endurecimiento de login y registro | Registrar los intentos de inicio de sesión en la bitácora (activitylog) con IP y resultado | 1 | María | Pendiente | — |
| 3 | TT09 | HT-03 Endurecimiento de login y registro | Ejecutar las pruebas de caja negra de autenticación (CN-004, CN-014, CN-025) | 1 | Miguel | Pendiente | — |
| 3 | TT10 | HT-04 Cabeceras de seguridad HTTP | Agregar Content-Security-Policy y HSTS en Caddy/nginx (y CloudFront) — en `barber`: `.docker/caddy/Caddyfile` y `.docker/nginx/default.conf` | 1 | Luis | Pendiente | — |
| 3 | TT11 | HT-04 Cabeceras de seguridad HTTP | Ocultar las cabeceras Server y X-Powered-By | 1 | Luis | Pendiente | — |
| 3 | TT12 | HT-04 Cabeceras de seguridad HTTP | Agregar una prueba automatizada de cabeceras de seguridad | 1 | Miguel | Pendiente | TT10, TT11 |
| 3 | TT36 | HT-14 Documentación de gestión y normatividad | Elaborar el análisis de riesgos (ISO 31000) y la matriz de controles ISO/IEC 27001 | 3 | Alan | Pendiente | — |
| 4 | T130 | HU-39 Construcción automática de las aplicaciones | Automatizar la construcción de la imagen Docker de barber en el pipeline | 1 | María | Pendiente | — |
| 4 | TT13 | HT-05 CORS, HTTPS y errores en staging y producción | Fijar CORS_ALLOWED_ORIGINS y APP_DEBUG=false en las variables de staging y producción | 1 | Alan | Pendiente | — |
| 4 | TT16 | HT-06 Pruebas de autorización y entradas maliciosas | Automatizar pruebas IDOR de citas, perfiles y barberos (PHPUnit) | 3 | Miguel | Pendiente | — |
| 4 | TT17 | HT-06 Pruebas de autorización y entradas maliciosas | Automatizar pruebas de inyección NoSQL, XSS y límites de campos | 3 | María | Pendiente | — |
| 4 | TT18 | HT-06 Pruebas de autorización y entradas maliciosas | Corregir los hallazgos de autorización y validación | 1 | Alan | Pendiente | TT16, TT17 |
| 4 | TT19 | HT-07 SAST con reglas OWASP en el pipeline | Agregar Semgrep con reglas OWASP Top 10 a los pipelines de barber y frontend-urban — pipeline de `barber` (el de `frontend-urban` va en su repo) | 3 | Elías | Pendiente | — |
| 4 | TT20 | HT-07 SAST con reglas OWASP en el pipeline | Configurar el bloqueo por severidad y documentar supresiones de falsos positivos | 1 | Elías | Pendiente | TT19 |
| 4 | TT24 | HT-09 Cobertura mínima de pruebas en el pipeline | Medir la cobertura actual y agregar pruebas en autenticación y citas hasta el umbral | 3 | Miguel | Pendiente | — |
| 4 | TT25 | HT-09 Cobertura mínima de pruebas en el pipeline | Configurar el umbral de cobertura en el pipeline de barber | 1 | María | Pendiente | TT24 |
| 5 | T134 | HU-40 Despliegue continuo (Continuous Delivery) | Configurar las credenciales de AWS y los secretos del pipeline de despliegue | 1 | María | Pendiente | — |
| 5 | T135 | HU-40 Despliegue continuo (Continuous Delivery) | Automatizar el despliegue a staging después de un build exitoso en main — imágenes de `barber` y `frontend-urban` | 3 | Luis | Pendiente | T133 |
| 5 | TT21 | HT-08 Dependencias, imágenes y licencias (SCA) | Configurar Trivy para bloquear CVE HIGH/CRITICAL y escanear las imágenes Docker | 1 | María | Pendiente | — |
| 5 | TT22 | HT-08 Dependencias, imágenes y licencias (SCA) | Activar Dependabot y documentar el proceso de actualización de dependencias — Dependabot en los cuatro repos | 1 | Luis | Pendiente | — |
| 5 | TT23 | HT-08 Dependencias, imágenes y licencias (SCA) | Revisar licencias de dependencias y registrar el resultado | 1 | Elías | Pendiente | — |
| 5 | TT26 | HT-10 DAST con OWASP ZAP sobre staging | Integrar OWASP ZAP baseline al pipeline contra staging | 3 | Luis | Pendiente | T135 |
| 5 | TT29 | HT-11 Pruebas de rendimiento | Crear escenarios de carga con k6 (login, disponibilidad y reserva) | 3 | María | Pendiente | — |
| 5 | TT30 | HT-11 Pruebas de rendimiento | Ejecutar las pruebas de carga en staging y documentar resultados | 1 | Miguel | Pendiente | TT29 |
| 5 | TT33 | HT-13 Ejecución del checklist y reporte consolidado | Ejecutar los puntos "Por ejecutar" del checklist y registrar resultados | 3 | Miguel | Pendiente | — |
| 6 | T106 | HU-31 Preparar sistema para producción | Configurar variables y parámetros del entorno | 1 | Alan | En progreso | — |
| 6 | T107 | HU-31 Preparar sistema para producción | Preparar backend, base de datos y aplicaciones para producción (hoy solo existe staging en AWS) | 3 | Alan | En progreso | — |
| 6 | T136 | HU-40 Despliegue continuo (Continuous Delivery) | Ejecutar la validación posterior al despliegue (pruebas de humo) | 1 | Miguel | Pendiente | T135 |
| 6 | TT27 | HT-10 DAST con OWASP ZAP sobre staging | Ejecutar el escaneo activo de endpoints críticos y registrar el reporte | 1 | Miguel | Pendiente | TT26 |
| 6 | TT28 | HT-10 DAST con OWASP ZAP sobre staging | Corregir los hallazgos altos del escaneo | 1 | Alan | Pendiente | TT27 |

### Terraform (EP-17, HU-33 a HU-37 y HU-41) — ubicación por definir en T112

Hoy el staging de AWS está creado a mano (`docs/DESPLIEGUE_AWS_STAGING.md`). Estas
tareas no tienen repositorio todavía: **T112 (Alan) decide dónde vive Terraform**.
Hasta entonces no se crean archivos `.tf` en ningún lado.

| Sprint | ID | Historia | Tarea | Pts | Responsable | Estado 23-sep | Depende de |
|---|---|---|---|---|---|---|---|
| 2 | T112 | HU-33 Configuración inicial de Terraform | Crear la estructura del proyecto Terraform (directorios, módulos y archivos .tf base) | 1 | Alan | Pendiente | — |
| 2 | T113 | HU-33 Configuración inicial de Terraform | Configurar los proveedores (AWS y MongoDB Atlas) y fijar sus versiones | 1 | María | Pendiente | — |
| 2 | T114 | HU-33 Configuración inicial de Terraform | Definir variables (variables.tf), outputs (outputs.tf) y el archivo de ejemplo terraform.tfvars | 1 | Luis | Pendiente | T112, T113 |
| 2 | T115 | HU-34 Gestión del estado de Terraform | Configurar el estado remoto de Terraform en S3 con bloqueo para evitar ejecuciones simultáneas | 1 | María | Pendiente | T112 |
| 2 | T116 | HU-34 Gestión del estado de Terraform | Definir la convención de ambientes (workspaces) y documentar el proceso de manejo del estado | 1 | Elías | Pendiente | T112 |
| 3 | T117 | HU-35 Provisionamiento de recursos | Codificar la red, los grupos de seguridad y el balanceador (ALB) del staging en AWS | 3 | Alan | Pendiente | T112–T116 |
| 3 | T118 | HU-35 Provisionamiento de recursos | Codificar el clúster de MongoDB Atlas, sus usuarios y el acceso de red con el proveedor mongodbatlas | 3 | María | Pendiente | T112–T116 |
| 3 | T119 | HU-35 Provisionamiento de recursos | Codificar ECR, ECS Fargate (servicios de barber y frontend-urban) y los secretos en Secrets Manager | 3 | Luis | Pendiente | T112–T116 |
| 3 | T120 | HU-35 Provisionamiento de recursos | Codificar CloudFront y los buckets S3 de la plataforma web y de archivos | 1 | Elías | Pendiente | T112–T116 |
| 3 | T121 | HU-36 Gestión y validación de la infraestructura | Ejecutar terraform init, terraform fmt y terraform validate, y corregir los hallazgos | 1 | Miguel | Pendiente | T117–T120 |
| 3 | T122 | HU-36 Gestión y validación de la infraestructura | Importar los recursos existentes (terraform import), revisar terraform plan y aplicar en staging | 3 | Alan | Pendiente | T121 |
| 3 | T123 | HU-36 Gestión y validación de la infraestructura | Validar los recursos creados y documentar la infraestructura (diagrama, variables y outputs) | 1 | Elías | Pendiente | T122 |
| 4 | T124 | HU-37 Integración de la infraestructura con backend y base de datos | Desplegar barber y frontend-urban sobre la infraestructura gestionada por Terraform | 3 | Luis | Pendiente | T122 |
| 4 | T125 | HU-37 Integración de la infraestructura con backend y base de datos | Conectar el backend con MongoDB Atlas en el ambiente gestionado y validar la integridad de los datos | 1 | María | Pendiente | T122 |
| 4 | T137 | HU-41 Integración de Terraform con CI/CD | Ejecutar terraform fmt y terraform validate automáticamente en el pipeline | 1 | María | Pendiente | T112 |
| 4 | T138 | HU-41 Integración de Terraform con CI/CD | Ejecutar terraform plan en el pipeline y publicar el resultado para revisión | 3 | Alan | Pendiente | T112 |
| 5 | T139 | HU-41 Integración de Terraform con CI/CD | Configurar controles de aprobación antes de ejecutar terraform apply | 1 | Elías | Pendiente | T138 |
| 6 | T140 | HU-41 Integración de Terraform con CI/CD | Documentar el pipeline de CI/CD y su integración con Terraform | 1 | Elías | Pendiente | — |

### Pruebas de QA que tocan este repositorio (Miguel y María)

| Sprint | ID | Historia | Tarea | Pts | Responsable | Estado 23-sep | Depende de |
|---|---|---|---|---|---|---|---|
| 1 | T094 | HU-27 Comunicación frontend-backend | Sincronizar y validar la información compartida entre la app móvil, la plataforma web y la base de datos | 3 | María | En progreso | — |
| 1 | T102 | HU-29 Pruebas funcionales | Registrar los resultados y los errores encontrados | 1 | Miguel | Pendiente | — |
| 1 | T103 | HU-30 Pruebas de integración | Preparar escenarios de integración | 1 | Miguel | En progreso | — |
| 4 | T104 | HU-30 Pruebas de integración | Probar la integración entre frontend, backend y MongoDB contra la API real (sin simulación) | 3 | Miguel | Pendiente | T103 |
| 5 | T095 | HU-27 Comunicación frontend-backend | Realizar pruebas de integración entre la app Android, el backend y la plataforma web | 1 | Miguel | Pendiente | T094 |
| 5 | T105 | HU-30 Pruebas de integración | Registrar los resultados y verificar las correcciones aplicadas | 1 | Miguel | Pendiente | T104 |
| 6 | T108 | HU-31 Preparar sistema para producción | Realizar pruebas finales del sistema | 1 | Miguel | Pendiente | T107 |
| 6 | T111 | HU-32 Documentación del proyecto | Revisar y organizar la documentación final | 1 | Miguel | Pendiente | — |

## Validación antes de entregar el commit

```powershell
.\test.ps1                                           # nunca php artisan test directo
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan clear-result-cache --configuration=phpstan.neon.dist
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
```
Después del push, revisar que el CI de `main` quede en verde (Backend, Frontend,
Security, Smoke). Antes de tocar pruebas, seeders o `docker compose down`, leer
`urbanblade-guardrails`.
