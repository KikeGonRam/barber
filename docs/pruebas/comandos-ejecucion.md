# Comandos de Ejecución de Pruebas Automatizadas

Este archivo contiene los comandos recomendados para ejecutar todas las pruebas del sistema, ya sea de forma global o por secciones.

---

## Ejecución Global (todas las pruebas)

```
docker compose exec app php artisan test
```

## Ejecución por Sección

### 1. Pruebas Feature (funcionales, de flujo y aceptación)
```
docker compose exec app php artisan test --testsuite=Feature
```

### 2. Pruebas Unitarias
```
docker compose exec app php artisan test --testsuite=Unit
```

### 3. Pruebas de Integración
```
docker compose exec app php artisan test --testsuite=Integration
```

### 4. Pruebas filtradas por nombre o clase

Ejemplo: solo pruebas de registro
```
docker compose exec app php artisan test --filter=Registration
```

Ejemplo: solo pruebas de inventario
```
docker compose exec app php artisan test --filter=Inventory
```

---

## Recomendaciones
- Ejecutar siempre primero en entorno local o de CI antes de producción.
- Consultar los archivos de resultados y logs en caso de fallos.
- Para mayor detalle, usar la opción `-v` (verbose):
```
docker compose exec app php artisan test -v
```
