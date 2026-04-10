# Comandos de Ejecución de Pruebas Automatizadas

Este archivo contiene los comandos recomendados para ejecutar todas las pruebas del sistema, ya sea de forma global o por secciones.

---

## Ejecución Global (todas las pruebas)

```
docker compose exec app php artisan test
```

## Ejecución Global (suite principal por bloques)

```
docker compose exec app php artisan test --testsuite=Feature
docker compose exec app php artisan test --testsuite=Unit
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
docker compose exec app php artisan test --filter=Integration
```

### 4. Pruebas de Humo
```
docker compose exec app php artisan test --filter=Smoke
```

### 5. Pruebas de Seguridad
```
docker compose exec app php artisan test --filter=Security
```

### 6. Pruebas de Rendimiento
```
docker compose exec app php artisan test --filter=Performance
```

### 7. Pruebas de Chatbot y Notificaciones
```
docker compose exec app php artisan test --filter=Chatbot
docker compose exec app php artisan test --filter=Notification
```

### 8. Pruebas de Citas/Clientes/Pagos/Inventario
```
docker compose exec app php artisan test --filter=Appointment
docker compose exec app php artisan test --filter=Client
docker compose exec app php artisan test --filter=Payment
docker compose exec app php artisan test --filter=Inventory
```

### 9. Pruebas filtradas por nombre o clase

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

### Comandos de diagnóstico rápido (si algo falla)

Ver stack detallado de una prueba:
```
docker compose exec app php artisan test tests/Feature/Api/MobileApiParityAccessTest.php --debug
```

Ver logs de la app:
```
docker compose exec app sh -lc "tail -n 200 storage/logs/laravel.log"
```
