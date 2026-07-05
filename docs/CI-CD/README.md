# Documentacion de Pipelines CI/CD

Este directorio contiene la documentacion de los workflows de GitHub Actions del proyecto.

## Flujos de trabajo (Workflows)

| Archivo | Nombre | Disparador | Notas |
|---|---|---|---|
| `.github/workflows/testing.yml` | Testing & Quality Pipeline | Push / PR a `main`, `develop` | Lint (ESLint/Prettier/Pint), tests backend contra un servicio `mongo:7`, E2E con Cypress, auditoria npm |
| `.github/workflows/docker-image.yml` | Docker Image CI | Push / PR a `main` | Build de la imagen Docker |
| `.github/workflows/npm-publish.yml` | Node.js Package | Al crear un release | No aplicable a este proyecto (plantilla por defecto) |
| `.github/workflows/sonarcloud.yml` | SonarCloud | Push / PR a `main` | Analisis de calidad de codigo (ver [error-4-sonarqube.md](./error-4-sonarqube.md)) |

Los workflows `php.yml` y `laravel.yml` (que usaban servicios MySQL/SQLite, incompatibles con los modelos MongoDB de este proyecto) fueron eliminados y consolidados en `testing.yml`.

## Errores historicos documentados

| # | Workflow | Descripcion | Archivo |
|---|---|---|---|
| 4 | SonarCloud | Workflow inexistente, accion vulnerable, sin cobertura y fallo sin token | [error-4-sonarqube.md](./error-4-sonarqube.md) |
