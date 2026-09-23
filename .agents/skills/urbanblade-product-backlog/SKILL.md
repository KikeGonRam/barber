---
name: urbanblade-product-backlog
description: Mantiene el Product Backlog académico de Urban Blade (Excel en la carpeta DOCUMENTACION) — épicas, historias, tareas, estados basados en evidencia del código, avance previo + 6 sprints, Terraform y CI/CD. Usar antes de leer, actualizar, reorganizar o reportar el avance del backlog, o al preparar una evaluación/entrega de sprint.
---

# Product Backlog de Urban Blade

## Dónde vive

- Carpeta canónica: `C:\Users\luis1\Documents\UrbanBlade\DOCUMENTACION\` (junto a
  `barber/`, `frontend-urban/` y `spark/`; no es un repositorio Git).
- Archivo vigente: `Product_Backlog_Urban_Blade_UBv2.xlsx` (revisión 3, 23-sep-2026:
  70 % de avance verificado, 74 pts pendientes). `UBv1` es el original del equipo; no
  se edita, se conserva como respaldo.
- Esta skill existe en tres copias idénticas: `DOCUMENTACION/.claude/skills/`,
  `barber/.claude/skills/` y `barber/.agents/skills/`. Si cambias una, cambia las tres.

## Tecnología real (usar siempre estos términos)

| Capa | Correcto | Nunca escribir |
|---|---|---|
| Backend/API | `barber`: Laravel 13 (PHP 8.3), API REST `/api/v1` | Node.js, Express |
| Base de datos | MongoDB Atlas con `mongodb/laravel-mongodb` (Eloquent) | Mongoose |
| Autenticación | Token Bearer propio (`mobile_api_tokens`) + login con Google | JWT, Sanctum |
| Web | `frontend-urban`: Nuxt 4 (Vue 3), E2E con Playwright | Next.js |
| Móvil | `UrbanBladeMobile`: Android nativo, Kotlin + Jetpack Compose, MVVM, Retrofit | Expo, React Native |
| Pagos | Efectivo, transferencia y tarjeta (Stripe en modo prueba) | "pago simulado", QR |
| Infraestructura | Staging en AWS creado a mano (ECR, ECS Fargate, ALB, CloudFront, S3, Secrets Manager), ver `barber/docs/DESPLIEGUE_AWS_STAGING.md`; Terraform aún no existe | — |
| CI | `.github/workflows/ci.yml` en barber y frontend-urban; Android sin CI | — |

Repositorios: `KikeGonRam/barber`, `KikeGonRam/frontend_Urbanblade`,
`al140605/UrbanBladeMobile`. El proyecto Expo `mobil` está descontinuado: no es evidencia.

## Estructura del libro (no romperla)

| Hoja | Qué es | ¿Se edita a mano? |
|---|---|---|
| Portada | Proyecto, tecnología, equipo, nota de revisión | Solo textos |
| Resumen por Épica | Historias, tareas, puntos y % de avance por épica | No (fórmulas) |
| Avance del Proyecto | Indicadores, plan de sprints, áreas, evidencia por épica, mapa HU×entrega | Solo textos |
| Product Backlog | Épica → historia → tarea, criterios, área, responsable, **evidencia** | Sí: textos, puntos, responsable, área, evidencia |
| Tablero Kanban | Tarjetas por estado; filtro de entrega en `C3` (Todas, 0–6) | No |
| Tablero por Semanas | Tarjetas pendientes por semana (1–12) | No |
| Tablero de Tareas | **Fuente de verdad** de Entrega (A: 0 = avance previo, 1–6 = sprints), Estado (N) y Semana (O: 0–12) | Sí: A, N, O |
| Acrónimos, Historias Combinadas | Catálogos | Al agregar historias |
| Distribución por Miembro | Tareas, estado y carga por entrega de cada integrante | Al reasignar (IDs en col. A) |
| Validación de Equipo / Validación Final | Controles con fórmulas | No |

- El Product Backlog obtiene Estado, Sprint y fechas del Tablero de Tareas por
  `INDEX/MATCH` sobre el ID de tarea; el Tablero de Tareas obtiene textos, puntos y
  responsable del Product Backlog. Una tarea nueva requiere fila en **ambas** hojas, su
  ID en "Distribución por Miembro" y, si es historia nueva, fila en "Acrónimos" y en el
  mapa de "Avance del Proyecto".
- Columnas ocultas: Product Backlog `V` (épica) y `W` (historia); Tablero de Tareas
  `Q`–`U` (claves de Kanban y semanas). No borrarlas.
- No usar `FILTER`, `XLOOKUP`, `UNIQUE`, `SORT`: el libro se valida con LibreOffice.

## Reglas del backlog

1. **Estados basados en evidencia del código, no en suposiciones.** Antes de cambiar un
   estado, revisa los tres repositorios. `Completada` exige evidencia concreta
   (archivo, controlador, pantalla o prueba) en la columna **Evidencia**; `En progreso`
   exige avance parcial verificable; sin evidencia queda `Pendiente`. El estado de la
   historia se deriva de sus tareas.
2. **Entrega 0 = avance previo.** Lo que ya existía al 23-sep-2026 está en la entrega 0
   (semana 0). Los sprints 1–6 contienen **solo trabajo pendiente** del cuatrimestre.
   Al terminar una tarea de un sprint, se marca `Completada` sin moverla de sprint.
3. Nomenclatura: `EP-NN`, `HU-NN`, `TNNN`. Siguientes libres: `EP-19`, `HU-42`, `T146`.
   No renumerar.
4. Puntos solo 1, 3 o 5 por tarea; 1 punto = 8 horas. Carga ≤ 45 pts por sprint y
   ≤ 15 pts por integrante y sprint.
5. Sprints (2 semanas; semanas 1–12), uno por evaluación:
   S1 14–25 sep · S2 28 sep–9 oct · S3 12–23 oct · S4 26 oct–6 nov · S5 9–20 nov ·
   S6 23 nov–4 dic.
6. Terraform (área `Terraform`, EP-17) nunca antes del **5-oct** (semana 4). El trabajo
   **pendiente** de CI/CD (área `CI/CD`, EP-18) nunca antes del **25-oct** (semana 7).
   T127–T129 (CI de barber y frontend-urban) ya existían y están en el avance previo.
7. Dependencias: autenticación → catálogos/horarios → disponibilidad → citas; API antes
   que su consumo; Terraform antes del despliegue continuo; pruebas conforme se libera
   cada función; producción y documentación final en S6.
8. Equipo (nombres exactos, la validación usa `COUNTIF`): Alan Ruiz Vilchis (Product
   Owner / Backend Developer), Elías García Nolasco (Scrum Master / Frontend Developer),
   Miguel Ángel Mena Garduño (QA Tester / Frontend Developer), Luis Enrique González
   Ramírez (Full Stack / Mobile Developer), María Isabel Cruz Flores (Backend Developer /
   Administradora de BD). Todos con trabajo técnico pendiente.

## Pendientes reales al 23-sep-2026 (74 pts)

- Android: pago con tarjeta (Stripe PaymentSheet, en progreso), push con FCM y su envío
  desde el backend, pruebas instrumentadas y en dispositivo.
- QA: registro de resultados, pruebas contra la API real (las E2E web usan API
  simulada), integración Android–backend–web.
- Infraestructura: todo EP-17 (codificar en Terraform el staging de AWS y MongoDB Atlas).
- CI/CD: CI de Android, imágenes Docker, build firmado, despliegue continuo a ECS,
  Terraform en el pipeline.
- Producción: ambiente de producción, pruebas finales y revisión de la documentación.

## Cómo modificar y validar

1. Trabajar sobre una copia; conservar el formato (Arial, encabezados gris `4A4A4A`,
   filas de épica en `E8DFC0`).
2. Editar con `openpyxl` sin `data_only=True` (borraría las fórmulas).
3. Recalcular con LibreOffice (requiere el paquete `libreoffice-calc`) y confirmar
   0 errores de fórmula y que todas las filas de "Validación Final" digan `✓ Cumple`.
4. Reportar: historias, tareas y puntos totales, completados, en progreso y pendientes,
   % de avance (puntos completados / totales) y la tabla de sprints.
5. Git: ninguna IA hace `commit` ni `push` en los repos de UrbanBlade salvo autorización
   explícita del dueño para ese cambio (ver `git-commit-conventions`); los mensajes de
   commit van en español.
