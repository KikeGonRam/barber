# Documentacion de Pipelines CI/CD

Este directorio contiene la documentacion detallada de los errores encontrados en los pipelines de CI/CD del proyecto y como fueron corregidos.

## Flujos de trabajo (Workflows)

El proyecto tiene cinco workflows de GitHub Actions:

| Archivo | Nombre | Disparador | Estado |
|---|---|---|---|
| `.github/workflows/php.yml` | PHP | Push / PR a `main` | Correcto |
| `.github/workflows/laravel.yml` | Laravel | Push / PR a `main` | Correcto |
| `.github/workflows/docker-image.yml` | Docker Image CI | Push / PR a `main` | Correcto |
| `.github/workflows/npm-publish.yml` | Node.js Package | Al crear un release | No aplicable |
| `.github/workflows/sonarcloud.yml` | SonarCloud | Push / PR a `main` | Correcto (ver Error 4) |

## Resumen de errores encontrados

| # | Workflow | Job | Error | Archivo de documentacion |
|---|---|---|---|---|
| 1 | PHP | `lint` | Pint detecta 23 problemas de estilo en el codigo | [error-1-pint-style.md](./error-1-pint-style.md) |
| 2 | Laravel | `laravel-tests` | El `composer.lock` requiere PHP 8.4 pero el workflow usa PHP 8.2 | [error-2-php-version-mismatch.md](./error-2-php-version-mismatch.md) |
| 3 | Laravel | (advertencia) | La accion `setup-php` esta fijada a un commit antiguo y usa Node.js 20 obsoleto | [error-3-deprecated-action-pin.md](./error-3-deprecated-action-pin.md) |
| 4 | SonarCloud | `sonarcloud` | Workflow inexistente, accion vulnerable, sin cobertura y fallo sin token | [error-4-sonarqube.md](./error-4-sonarqube.md) |
| 5 | Laravel / PHP | `laravel-tests` / `tests` | `<coverage>` en phpunit.xml provoca exit code 1 sin driver de cobertura | [error-5-phpunit-coverage-driver.md](./error-5-phpunit-coverage-driver.md) |
