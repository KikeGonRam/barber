# 📊 Guía de Monitoring - BarberPro Elite

## 1. Sentry - Error Tracking

### ¿Por qué Sentry?
- Captura automática de excepciones
- Stack traces legibles
- Seguimiento de usuarios afectados
- Alertas en tiempo real
- Plan gratuito: 5,000 eventos/mes

### 1.1 Configuración

**PASO 1: Crear cuenta Sentry**
```bash
# Visita https://sentry.io
# Sign up (Free plan disponible)
# Crear nuevo proyecto: Laravel
```

**PASO 2: Copiar DSN**
```bash
# En el proyecto de Sentry, obtener la URL tipo:
# https://examplePublicKey@o0.ingest.sentry.io/0

# Copiar a .env:
SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/0
SENTRY_ENVIRONMENT=production
```

**PASO 3: Verificar instalación**
```bash
# El archivo ya fue publicado en:
# config/sentry.php

# Ver contenido:
cat config/sentry.php
```

### 1.2 Probar Sentry

**Crear una ruta de prueba en `routes/web.php`:**
```php
Route::get('/test-sentry', function () {
    throw new Exception('¡Esto es una prueba de Sentry!');
});
```

**Ejecutar prueba:**
```bash
# Acceder a: http://localhost:8000/test-sentry
# La excepción debe aparecer en dashboard de Sentry en segundos

# En producción (con SENTRY_LARAVEL_DSN configurado)
```

### 1.3 Uso Automático

Sentry captura automáticamente:
- ✅ Excepciones no manejadas
- ✅ Errores de validación
- ✅ Errores de base de datos
- ✅ Errores HTTP
- ✅ Info del usuario (si lo proporcionas)

### 1.4 Capturar eventos personalizados

```php
<?php

namespace App\Services;

use Sentry\Laravel\Facade as Sentry;

class ReservasService
{
    public function crearReserva($datos)
    {
        try {
            // Tu lógica...
            
            // Capturar evento éxito
            Sentry::captureMessage(
                'Nueva reserva creada: ' . $datos['id'],
                'info'
            );
            
        } catch (\Exception $e) {
            // Capturar con contexto
            Sentry::captureException($e, [
                'extra' => [
                    'cliente_id' => $datos['cliente_id'],
                    'barbero_id' => $datos['barbero_id'],
                ]
            ]);
            throw $e;
        }
    }
}
```

### 1.5 Capturar info del usuario

```php
// En LoginController después de autenticar:
use Sentry\Laravel\Facade as Sentry;

Sentry::configureScope(function ($scope) use ($user) {
    $scope->setUser([
        'id' => $user->id,
        'email' => $user->email,
        'username' => $user->name,
    ]);
});
```

---
## 2. Logs y Dashboard

### 3.1 Verificar logs en Docker

```bash
# Ver logs de app
docker-compose logs -f app

# Ver logs de nginx
docker-compose logs -f web

# Ver logs de redis
docker-compose logs -f redis
```

### 3.2 Dashboards disponibles

- **Sentry Dashboard**: https://sentry.io (errores)
- **Adminer**: http://localhost:8080 (BD)
- **Redis Commander**: `npm install -g redis-commander && redis-commander`
- **Laravel Telescope**: http://localhost:8000/telescope (si lo instalas)

---

## 3. Checklist - Implementación Completa

### Fase 1: Monitoreo de Errores (30 min)
- [ ] Crear cuenta en Sentry
- [ ] Obtener DSN
- [ ] Guardar en .env: SENTRY_LARAVEL_DSN
- [ ] Crear ruta `/test-sentry` y probar
- [ ] Verificar excepción en dashboard Sentry

### Fase 2: Integración (30 min)
- [ ] Capturar eventos en ReservasService
- [ ] Capturar info de usuario en LoginController
- [ ] Configurar alertas en Sentry (email, Slack)
- [ ] Crear dashboard en Sentry

---

## 4. Ejemplos Prácticos

### Capturar error con contexto

```php
<?php

use Sentry\Laravel\Facade as Sentry;

try {
    $reserva = Reserva::findOrFail($id);
} catch (ModelNotFoundException $e) {
    Sentry::captureException($e, [
        'level' => 'warning',
        'extra' => [
            'reserva_id' => $id,
            'usuario_id' => auth()->id(),
            'ip' => request()->ip(),
        ],
        'tags' => [
            'tipo' => 'reserva',
            'accion' => 'lectura',
        ],
    ]);
    throw $e;
}
```

---

## 5. Troubleshooting

### Sentry no recibe eventos
```bash
# Verificar DSN en .env
echo $SENTRY_LARAVEL_DSN

# Limpiar cache Laravel
docker-compose exec app php artisan config:cache

# Reiniciar app
docker-compose restart app
```


### Latencia alta en tests
```bash
# Verificar recursos
docker stats

# Ver logs de app
docker-compose logs app | tail -50

# Reducir VUS (usuarios simultáneos)
# Aumentar duración del test para promedios reales
```

---

## 6. Siguientes Pasos

1. ✅ Sentry configurado
2. ⏳ Alertas configuradas
3. ⏳ Dashboard personal creado

**Meta:** Detectar y resolver problemas ANTES de que los clientes los vean 🎯
