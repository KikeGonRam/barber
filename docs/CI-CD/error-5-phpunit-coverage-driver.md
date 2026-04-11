# Error 5 - PHPUnit: No code coverage driver available (exit code 1)

## Flujos de trabajo afectados

- **Archivo:** `.github/workflows/laravel.yml`
- **Job:** `laravel-tests`
- **Paso fallido:** `Execute all tests (Unit, Feature, API parity)`

- **Archivo:** `.github/workflows/php.yml`
- **Job:** `tests`
- **Paso fallido:** `Run test suite`

## Descripcion del error

Ambos workflows fallaban con exit code 1 aunque todos los tests pasaban correctamente (171 passed, 702 assertions). Al inicio de la ejecucion de `php artisan test` aparecia la siguiente advertencia:

```
WARN  No code coverage driver available
```

A continuacion todos los tests se ejecutaban y pasaban:

```
Tests:    171 passed (702 assertions)
Duration: 13.01s

##[error]Process completed with exit code 1.
```

En el workflow `php.yml`, el mensaje complementario de Composer era:

```
Script @php artisan test handling the test event returned with error code 1
```

## Causa raiz

En PR #3 se agrego una seccion `<coverage>` al archivo `phpunit.xml` con el objetivo de generar el reporte de cobertura para SonarCloud:

```xml
<coverage>
    <report>
        <clover outputFile="coverage.xml"/>
    </report>
</coverage>
```

Esta configuracion le indica a PHPUnit 11 que debe recopilar y exportar cobertura de codigo en cada ejecucion. Cuando PHPUnit intenta hacerlo pero **no encuentra un driver de cobertura** (xdebug o pcov), en PHPUnit 11 esto provoca un **exit code 1**, aunque todos los tests hayan pasado.

Los workflows `laravel.yml` y `php.yml` instalan PHP con `coverage: none`:

```yaml
uses: shivammathur/setup-php@v2
with:
  coverage: none   # Sin driver de cobertura
```

Por eso PHPUnit emite el warning y sale con codigo 1.

El workflow `sonarcloud.yml` usa `coverage: pcov`, por lo que no se ve afectado.

### Por que exit code 1 y no solo un warning

En PHPUnit 10, la ausencia de driver con `<coverage>` configurado generaba solo un aviso en pantalla pero el exit code seguia siendo 0. En **PHPUnit 11**, este comportamiento cambio: si hay un `<report>` configurado dentro de `<coverage>` y no hay driver disponible, PHPUnit trata la situacion como un error y devuelve **exit code 1**.

## Solucion aplicada

Se elimino la seccion `<coverage>` de `phpunit.xml`. El workflow `sonarcloud.yml` ya pasaba los flags de cobertura directamente como argumentos de linea de comandos a `./vendor/bin/phpunit`, por lo que no necesita la configuracion en `phpunit.xml`:

```yaml
run: |
  php artisan config:clear
  ./vendor/bin/phpunit \
    --coverage-clover=coverage.xml \
    --log-junit=junit.xml
```

De esta forma:

- Los workflows `laravel.yml` y `php.yml` ejecutan `php artisan test` **sin intentar generar cobertura**, obteniendo exit code 0 cuando todos los tests pasan.
- El workflow `sonarcloud.yml` genera la cobertura con pcov mediante los flags CLI, sin depender de `phpunit.xml`.

La seccion `<source>` permanece en `phpunit.xml` para indicar a PHPUnit que archivos deben considerarse al medir cobertura cuando el flag `--coverage-clover` se pasa por CLI:

```xml
<source>
    <include>
        <directory>app</directory>
    </include>
</source>
```

### Verificacion

Tras aplicar el cambio, los workflows `laravel.yml` y `php.yml` ejecutan los tests sin el warning y finalizan con exit code 0:

```
Tests:    171 passed (702 assertions)
Duration: 13.xx s
```

El workflow `sonarcloud.yml` sigue generando `coverage.xml` correctamente para el analisis de SonarCloud.
