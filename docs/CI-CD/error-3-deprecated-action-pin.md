# Error 3 - Accion setup-php fijada a commit obsoleto y advertencia de Node.js 20

## Flujo de trabajo afectado

- **Archivo:** `.github/workflows/laravel.yml`
- **Job:** `laravel-tests`
- **Tipo:** Advertencia (no bloquea el build por si sola, pero indica deuda tecnica)

## Descripcion del problema

Al finalizar la ejecucion del job `laravel-tests`, GitHub Actions mostraba la siguiente advertencia:

```
Node.js 20 actions are deprecated. The following actions are running on Node.js 20
and may not work as expected:
  actions/checkout@v4,
  shivammathur/setup-php@15c43e89cdef867065b0213be354c2841860869e.

Actions will be forced to run with Node.js 24 by default starting June 2nd, 2026.
Node.js 20 will be removed from the runner on September 16th, 2026.

Please check if updated versions of these actions are available that support Node.js 24.
```

## Causa raiz

El workflow `laravel.yml` referenciaba la accion `setup-php` con un **hash de commit especifico y antiguo**:

```yaml
# Configuracion anterior (problematica)
- uses: shivammathur/setup-php@15c43e89cdef867065b0213be354c2841860869e
```

El commit `15c43e89` corresponde a una version antigua de la accion que internamente usa Node.js 20. Dado que GitHub ha anunciado que Node.js 20 sera eliminado de los runners en septiembre de 2026, esta configuracion dejara de funcionar.

### Por que se uso un commit en lugar de un tag

Fijar acciones a un commit especifico es una practica de seguridad recomendada para evitar ataques de cadena de suministro (supply chain attacks). Sin embargo, el problema es que este commit en particular corresponde a una version obsoleta que no se ha mantenido actualizada.

## Diferencia entre el workflow afectado y el workflow correcto

| Workflow | Referencia a setup-php | Node.js |
|---|---|---|
| `laravel.yml` (antes) | `@15c43e89...` (commit obsoleto) | Node.js 20 (obsoleto) |
| `php.yml` | `@v2` (tag actual) | Node.js 24 (correcto) |

## Solucion aplicada

Se reemplazo la referencia al commit obsoleto por el tag `@v2`, que siempre apunta a la version estable mas reciente y compatible:

```yaml
# Configuracion corregida
- uses: shivammathur/setup-php@v2
  with:
    php-version: '8.4'
    extensions: mbstring, dom, fileinfo, curl, sqlite3, pdo_sqlite
    tools: composer:v2
    coverage: none
```

Con esta configuracion, la accion `setup-php` usara Node.js 24 y sera compatible con los runners de GitHub Actions mas alla de septiembre de 2026.

## Impacto esperado

- La advertencia de depreciacion desaparecera en futuras ejecuciones.
- El workflow seguira funcionando despues de la fecha limite de septiembre de 2026.
- Se aprovechan las mejoras de rendimiento y seguridad de la version actualizada de la accion.

## Prevencion futura

- Revisar periodicamente las advertencias de depreciacion en los logs de GitHub Actions.
- Cuando se use una referencia a un commit para mayor seguridad, asegurarse de actualizar ese commit regularmente, o usar una herramienta como Dependabot para automatizar las actualizaciones de acciones de GitHub.
- Configurar Dependabot para gestionar actualizaciones de workflows en `.github/dependabot.yml`:

```yaml
version: 2
updates:
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
```

## Referencia al CI

- Workflow: [Laravel](https://github.com/KikeGonRam/barber/actions/workflows/laravel.yml)
- Run de ejemplo con la advertencia: [Run #24276032888](https://github.com/KikeGonRam/barber/actions/runs/24276032888/job/70890028833)
- Anuncio oficial de GitHub: [Deprecation of Node.js 20 on GitHub Actions runners](https://github.blog/changelog/2025-09-19-deprecation-of-node-20-on-github-actions-runners/)
