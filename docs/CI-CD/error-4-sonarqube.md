# Error 4 - SonarCloud: Workflow inexistente y configuracion incorrecta

## Flujo de trabajo afectado

- **Archivo creado:** `.github/workflows/sonarcloud.yml`
- **Archivo creado:** `sonar-project.properties`
- **Archivo modificado:** `phpunit.xml`

## Descripcion del problema

El proyecto no contaba con un pipeline de analisis de calidad de codigo. Al intentar agregar SonarCloud como parte del CI/CD, el workflow fallaba por cuatro causas independientes:

| # | Causa | Impacto |
|---|---|---|
| 4.1 | Ausencia de `sonar-project.properties` | SonarCloud no sabia que analizar ni a que organizacion reportar |
| 4.2 | Version vulnerable de la accion | `sonarqube-scan-action` < 6.0.0 tiene vulnerabilidades de inyeccion de argumentos y comandos (CVE) |
| 4.3 | Falta de cobertura de codigo | phpunit no generaba `coverage.xml`, dejando a SonarCloud sin datos de cobertura |
| 4.4 | Fallo si `SONAR_TOKEN` no esta configurado | En forks o entornos sin el secreto el workflow terminaba con error, bloqueando el PR |

---

## Causa 4.1 — Archivo `sonar-project.properties` ausente

SonarCloud requiere este archivo en la raiz del proyecto para identificar:
- El **project key** y **organization** del proyecto en SonarCloud.
- Los **directorios de codigo fuente** y de **tests**.
- Las **rutas de los reportes** de cobertura y tests.
- Las **exclusiones** (vendor, node_modules, archivos generados).

Sin este archivo, el scanner no puede ni iniciar el analisis y devuelve:

```
ERROR: You must define the following mandatory properties for 'Unknown':
  sonar.projectKey, sonar.organization
```

### Solucion

Se creo el archivo `sonar-project.properties` en la raiz del proyecto:

```properties
sonar.projectKey=KikeGonRam_barber
sonar.organization=kikegonram

sonar.projectName=Barber
sonar.projectVersion=1.0

sonar.sources=app,routes,config,database
sonar.tests=tests

sonar.exclusions=vendor/**,node_modules/**,public/**,storage/**,bootstrap/cache/**,*.min.js
sonar.coverage.exclusions=database/**,config/**,resources/**,bootstrap/**,routes/**

sonar.php.coverage.reportPaths=coverage.xml
sonar.php.tests.reportPath=junit.xml
sonar.sourceEncoding=UTF-8
```

> **Nota:** `sonar.projectKey` y `sonar.organization` deben coincidir exactamente con lo configurado en la interfaz de SonarCloud ([sonarcloud.io](https://sonarcloud.io)).

---

## Causa 4.2 — Version vulnerable de la accion `sonarqube-scan-action`

Las versiones `>= 4.0.0 y < 6.0.0` de `SonarSource/sonarqube-scan-action` tienen dos vulnerabilidades conocidas:

| Vulnerabilidad | Versiones afectadas | Version corregida |
|---|---|---|
| Inyeccion de argumentos | >= 4.0.0, < 6.0.0 | 6.0.0 |
| Inyeccion de comandos via la accion | >= 4.0.0, <= 5.3.0 | 5.3.1 |

Un workflow que use `@v4` o `@v5` (sin especificar patch) podria quedar expuesto a estas vulnerabilidades.

### Solucion

Se fijo la version a `v5.3.1` (version con parche para ambas vulnerabilidades en la rama v5):

```yaml
- name: SonarCloud Scan
  uses: SonarSource/sonarqube-scan-action@v5.3.1
```

> **Nota de seguridad:** Se puede usar `@v6` para la version mayor con ambas correcciones consolidadas. Se usa `@v5.3.1` por maxima compatibilidad con la configuracion actual.

---

## Causa 4.3 — PHPUnit no generaba reporte de cobertura

El archivo `phpunit.xml` no incluia configuracion de cobertura. Sin esta configuracion, la ejecucion de tests no produce el archivo `coverage.xml` que SonarCloud necesita para mostrar metricas de cobertura.

**Configuracion anterior (`phpunit.xml`):**
```xml
<source>
    <include>
        <directory>app</directory>
    </include>
</source>
<!-- Sin seccion <coverage> -->
```

### Solucion

Se agrego la seccion `<coverage>` al `phpunit.xml`:

```xml
<source>
    <include>
        <directory>app</directory>
    </include>
</source>
<coverage>
    <report>
        <clover outputFile="coverage.xml"/>
    </report>
</coverage>
```

Ademas, el workflow genera los reportes pasando flags directamente a PHPUnit:

```bash
./vendor/bin/phpunit \
  --coverage-clover=coverage.xml \
  --log-junit=junit.xml
```

El workflow usa **pcov** (mas rapido que Xdebug para cobertura pura) configurado via `setup-php`:

```yaml
- uses: shivammathur/setup-php@v2
  with:
    coverage: pcov
```

---

## Causa 4.4 — Fallo cuando `SONAR_TOKEN` no esta configurado

Si el secreto `SONAR_TOKEN` no esta configurado en el repositorio (por ejemplo en forks, en ramas de contribuidores externos, o antes de crear la cuenta en SonarCloud), la accion falla con:

```
Error: No value provided for SONAR_TOKEN
```

Esto bloquea completamente el workflow y hace que el check de CI quede en rojo.

### Solucion

Se expone el secreto como variable de entorno a nivel de job y se evalua en el `if` de cada paso:

```yaml
jobs:
  sonarcloud:
    env:
      SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}

    steps:
      - name: SonarCloud Scan
        if: ${{ env.SONAR_TOKEN != '' }}
        uses: SonarSource/sonarqube-scan-action@v5.3.1
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          SONAR_TOKEN: ${{ env.SONAR_TOKEN }}

      - name: Skip SonarCloud (no token configured)
        if: ${{ env.SONAR_TOKEN == '' }}
        run: |
          echo "::notice::SONAR_TOKEN no esta configurado. El analisis de SonarCloud fue omitido."
```

Con esto:
- Si el token **esta configurado**: se ejecuta el analisis real.
- Si el token **no esta configurado**: el paso se omite con una advertencia visible en los logs, y el workflow **pasa en verde**.

---

## Configuracion adicional: `fetch-depth: 0`

SonarCloud necesita el historial completo de git para calcular correctamente:
- El "codigo nuevo" en cada PR (segun la fecha del ultimo analisis).
- Anotaciones de blame (quien escribio cada linea).
- Metricas de antiguedad del codigo.

Sin esto, el checkout predeterminado de GitHub Actions hace un **shallow clone** (solo el ultimo commit) y SonarCloud reporta advertencias o calcula metricas incorrectas.

```yaml
- uses: actions/checkout@v4
  with:
    fetch-depth: 0
```

---

## Prevencion futura

### Configurar el secreto `SONAR_TOKEN`

1. Crear una cuenta en [sonarcloud.io](https://sonarcloud.io) usando GitHub OAuth.
2. Crear el proyecto con la clave `KikeGonRam_barber` en la organizacion `kikegonram`.
3. Generar un token en **My Account > Security > Generate Tokens**.
4. Agregar el token en GitHub: **Settings > Secrets and variables > Actions > New repository secret** con el nombre `SONAR_TOKEN`.

### Mantener la accion actualizada

Configurar Dependabot para actualizar automaticamente las acciones de GitHub:

```yaml
# .github/dependabot.yml
version: 2
updates:
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
```

---

## Resumen de archivos afectados

| Archivo | Accion | Motivo |
|---|---|---|
| `.github/workflows/sonarcloud.yml` | Creado | Nuevo workflow de analisis de calidad |
| `sonar-project.properties` | Creado | Configuracion del proyecto en SonarCloud |
| `phpunit.xml` | Modificado | Agrega generacion de `coverage.xml` |
