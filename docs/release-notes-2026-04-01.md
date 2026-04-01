# Release Notes - 2026-04-01

## Resumen

Este release consolida la estabilizacion de calidad del proyecto en tres frentes:

- Cobertura de pruebas por prioridad del prompt maestro.
- Estabilizacion de la suite completa en Docker.
- Integracion de CI en GitHub Actions para pruebas y estilo.

## Hitos incluidos

### 1) Suites de pruebas agregadas

Se incorporaron suites nuevas para cubrir escenarios criticos:

- Acceptance
- Notifications
- Storage/File upload
- Functional
- Integration
- BlackBox
- WhiteBox
- Performance
- E2E
- Security
- Smoke

Referencia de documentacion tecnica:

- `docs/testing-soluciones-2026-04-01.md`

### 2) Estabilizacion de pruebas legacy

Se corrigieron fallos reales y desalineaciones de tests:

- Ajustes CSRF en entorno de testing.
- Correcciones de autorizacion y politicas.
- Actualizacion de asserts acoplados a textos/UI antigua.
- Regla de password de registro en tests auth.

Resultado final validado en Docker:

- `php artisan test` -> 155 tests passing, 572 assertions.

### 3) CI en GitHub Actions

Se mejoro el workflow principal para validar automaticamente:

- Job `tests`: instala dependencias, prepara entorno y ejecuta `composer test`.
- Job `lint`: ejecuta `./vendor/bin/pint --test`.

Archivo:

- `.github/workflows/php.yml`

### 4) Normalizacion de estilo

Se aplico formateo completo con Laravel Pint para alinear el repositorio con la nueva job `lint`.

## Commits clave del release

- `57d2fb7` - ci: run php tests in github actions
- `9735e57` - ci: add pint style check job
- `9dd0cfb` - test: stabilize full suite and fix remaining legacy failures
- `bef6c0b` - test: add acceptance, notifications and storage suites
- `a95f6b7` - style: apply pint formatting across codebase
- `260e605` - fix: resolve CI failures (composer/php workflow)

## Estado esperado post-release

- Suite local completa estable.
- Pipeline CI validando pruebas y estilo en push/PR contra `main`.
- Baseline de calidad y trazabilidad documentados.
