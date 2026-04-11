# Error 2 - Desajuste de version PHP entre workflow y composer.lock

## Flujo de trabajo afectado

- **Archivo:** `.github/workflows/laravel.yml`
- **Job:** `laravel-tests`
- **Paso fallido:** `Install Dependencies`

## Descripcion del error

El job `laravel-tests` del workflow **Laravel** fallaba en el paso de instalacion de dependencias con el siguiente mensaje:

```
Your lock file does not contain a compatible set of packages. Please run composer update.

  Problem 1
    - spatie/laravel-activitylog is locked to version 5.0.0 and an update of this package was not requested.
    - spatie/laravel-activitylog 5.0.0 requires php ^8.4 -> your php version (8.2) does not satisfy that requirement.
  Problem 2
    - spatie/laravel-permission is locked to version 7.2.4 and an update of this package was not requested.
    - spatie/laravel-permission 7.2.4 requires php ^8.4 -> your php version (8.2) does not satisfy that requirement.
  Problem 3
    - symfony/clock v8.0.8 requires php >=8.4 -> your php version (8.2) does not satisfy that requirement.
  Problem 4
    - symfony/css-selector v8.0.8 requires php >=8.4 -> your php version (8.2) does not satisfy that requirement.
  ...

Process completed with exit code 2.
```

## Causa raiz

El archivo `composer.lock` fue generado con **PHP 8.4** e incluye versiones de paquetes que requieren PHP >= 8.4:

| Paquete | Version bloqueada | PHP requerido |
|---|---|---|
| `spatie/laravel-activitylog` | 5.0.0 | ^8.4 |
| `spatie/laravel-permission` | 7.2.4 | ^8.4 |
| `symfony/clock` | v8.0.8 | >=8.4 |
| `symfony/css-selector` | v8.0.8 | >=8.4 |
| `symfony/event-dispatcher` | v8.0.8 | >=8.4 |
| `symfony/string` | v8.0.8 | >=8.4 |
| `symfony/translation` | v8.0.8 | >=8.4 |
| `symfony/var-exporter` | v8.0.8 | >=8.4 |

Sin embargo, el workflow `laravel.yml` estaba configurado con **PHP 8.2**, creando una incompatibilidad:

```yaml
# Configuracion anterior (incorrecta)
- uses: shivammathur/setup-php@15c43e89cdef867065b0213be354c2841860869e
  with:
    php-version: '8.2'   # No coincide con las dependencias bloqueadas
```

Ademas, el workflow usaba una referencia fija a un commit antiguo de la accion `setup-php` en lugar del tag de version correcto (`@v2`).

## Por que el otro workflow (PHP) si funciona

El workflow `php.yml` usa PHP 8.4 correctamente, por lo que instala las dependencias sin problemas:

```yaml
# php.yml (correcto)
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.4'
```

## Solucion aplicada

Se actualizo el workflow `laravel.yml` para usar **PHP 8.4**, que coincide con la version usada para generar el `composer.lock`, y se reemplazo la referencia al commit de la accion por el tag `@v2`:

```yaml
# Configuracion corregida en laravel.yml
- uses: actions/checkout@v4
- uses: shivammathur/setup-php@v2
  with:
    php-version: '8.4'
    extensions: mbstring, dom, fileinfo, curl, sqlite3, pdo_sqlite
    tools: composer:v2
    coverage: none
```

## Prevencion futura

- El archivo `composer.lock` debe estar siempre sincronizado con la version de PHP usada en todos los workflows de CI.
- Cuando se actualiza la version de PHP del proyecto, se debe actualizar el `composer.lock` ejecutando `composer update` con la nueva version y tambien actualizar todos los workflows que instalan dependencias.
- Usar siempre tags de version en las acciones (como `@v2`) en lugar de referencias a commits especificos, para beneficiarse de las actualizaciones de compatibilidad.

## Referencia al CI

- Workflow: [Laravel](https://github.com/KikeGonRam/barber/actions/workflows/laravel.yml)
- Run de ejemplo con el error: [Run #24276032888](https://github.com/KikeGonRam/barber/actions/runs/24276032888/job/70890028833)
