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

## 2. K6 - Load Testing

### ¿Por qué K6?
- Escrito en Go (muy rápido)
- Fácil de usar con JavaScript
- Tests realistas
- Puede simular usuarios reales
- Métricas detalladas

### 2.1 Instalación

**En Windows (via Chocolatey o Direct):**
```powershell
# Via instalador directo:
# https://dl.k6.io/msi/k6-latest-amd64.msi

# O via WinGet:
winget install grafana.k6

# Verificar:
k6 --version
```

### 2.2 Crear test básico

**Archivo: `k6/load-test.js`**

```javascript
import http from 'k6/http';
import { check, group, sleep } from 'k6';

export const options = {
  vus: 10,                    // 10 usuarios simultáneos
  duration: '30s',            // 30 segundos
  
  stages: [
    { duration: '10s', target: 5 },   // Rampa hasta 5 usuarios
    { duration: '10s', target: 10 },  // Rampa hasta 10
    { duration: '10s', target: 0 },   // Bajada a 0
  ],
  
  thresholds: {
    'http_req_duration': ['p(95)<500'],  // 95% de requests < 500ms
    'http_req_failed': ['rate<0.1'],     // <10% de fallos
  },
};

export default function () {
  group('Dashboard', () => {
    const res = http.get('http://localhost:8000/dashboard');
    check(res, {
      'dashboard status 200': (r) => r.status === 200,
      'dashboard time < 500ms': (r) => r.timings.duration < 500,
    });
  });

  group('Reservas API', () => {
    const res = http.get('http://localhost:8000/api/reservas');
    check(res, {
      'reservas status 200': (r) => r.status === 200,
      'respuesta contiene data': (r) => r.body.includes('data'),
    });
  });

  group('Login', () => {
    const payload = JSON.stringify({
      email: 'admin@barberpro.local',
      password: 'password',
    });

    const res = http.post('http://localhost:8000/api/login', payload, {
      headers: { 'Content-Type': 'application/json' },
    });

    check(res, {
      'login status 200': (r) => r.status === 200,
      'login devuelve token': (r) => r.body.includes('token'),
    });
  });

  sleep(1);
}
```

### 2.3 Ejecutar test

```bash
# Ejecutar test básico
k6 run k6/load-test.js

# Con opciones:
k6 run --vus 20 --duration 60s k6/load-test.js

# Con salida en HTML (instalar antes: k6 install k6-html-reporter)
k6 run --out=json=results.json k6/load-test.js
```

### 2.4 Métricas esperadas

```
✅ BUENO:
- p95 latency: < 500ms
- p99 latency: < 1000ms
- Error rate: < 1%
- Throughput: > 100 req/s

⚠️ ACEPTABLE:
- p95 latency: 500-1000ms
- Error rate: 1-5%

❌ MALO:
- p95 latency: > 1000ms
- Error rate: > 5%
```

### 2.5 Test realista con sesiones

**Archivo: `k6/realistic-test.js`**

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '2m', target: 50 },   // Rampa 2 min
    { duration: '5m', target: 50 },   // Mantener
    { duration: '2m', target: 0 },    // Bajada
  ],
  thresholds: {
    'http_req_duration': ['p(95)<1000'],
    'http_req_failed': ['rate<0.05'],
  },
};

export default function () {
  // Simular usuario real
  let token = '';

  // 1. Login
  const loginRes = http.post(
    'http://localhost:8000/api/login',
    JSON.stringify({
      email: 'admin@barberpro.local',
      password: 'password',
    }),
    { headers: { 'Content-Type': 'application/json' } }
  );

  check(loginRes, {
    'login success': (r) => r.status === 200,
  });

  if (loginRes.status === 200) {
    token = JSON.parse(loginRes.body).token;
  }

  // 2. Ver dashboard
  http.get('http://localhost:8000/dashboard', {
    headers: { 'Authorization': `Bearer ${token}` },
  });

  sleep(2);

  // 3. Buscar reservas
  http.get('http://localhost:8000/api/reservas', {
    headers: { 'Authorization': `Bearer ${token}` },
  });

  sleep(1);

  // 4. Crear reserva
  http.post(
    'http://localhost:8000/api/reservas',
    JSON.stringify({
      barbero_id: 1,
      cliente_id: 1,
      fecha: '2026-05-10',
      hora: '10:00',
      servicio: 'Corte + Barba',
    }),
    {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    }
  );

  sleep(3);
}
```

---

## 3. Logs y Dashboard

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

## 4. Checklist - Implementación Completa

### Fase 1: Monitoreo de Errores (30 min)
- [ ] Crear cuenta en Sentry
- [ ] Obtener DSN
- [ ] Guardar en .env: SENTRY_LARAVEL_DSN
- [ ] Crear ruta `/test-sentry` y probar
- [ ] Verificar excepción en dashboard Sentry

### Fase 2: Load Testing (1 hora)
- [ ] Instalar K6
- [ ] Crear k6/load-test.js
- [ ] Ejecutar: `k6 run k6/load-test.js`
- [ ] Analizar métricas
- [ ] Crear k6/realistic-test.js
- [ ] Ejecutar test realista
- [ ] Documentar resultados

### Fase 3: Integración (30 min)
- [ ] Capturar eventos en ReservasService
- [ ] Capturar info de usuario en LoginController
- [ ] Configurar alertas en Sentry (email, Slack)
- [ ] Crear dashboard en Sentry

### Fase 4: CI/CD (30 min)
- [ ] Agregar K6 test al GitHub Actions
- [ ] Agregar umbral de p95 < 500ms
- [ ] Fallar workflow si no se cumplen thresholds
- [ ] Documentar en README

---

## 5. Ejemplos Prácticos

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

### Medir performance con K6

```javascript
import { Trend, Rate } from 'k6/metrics';

const loginTime = new Trend('login_duration');
const loginFailRate = new Rate('login_failures');

export default function () {
  const startTime = new Date();
  
  const res = http.post('http://localhost:8000/api/login', payload);
  
  loginTime.add(new Date() - startTime);
  loginFailRate.add(res.status !== 200);
}
```

---

## 6. Troubleshooting

### Sentry no recibe eventos
```bash
# Verificar DSN en .env
echo $SENTRY_LARAVEL_DSN

# Limpiar cache Laravel
docker-compose exec app php artisan config:cache

# Reiniciar app
docker-compose restart app
```

### K6 no conecta a localhost
```bash
# Usar IP de Docker host (no localhost)
# En Windows/Mac: host.docker.internal
# En Linux: IP de la máquina

http.get('http://host.docker.internal:8000/api/reservas')
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

## 7. Siguientes Pasos

1. ✅ Sentry configurado
2. ⏳ K6 instalado y test básico creado
3. ⏳ Tests integrados en CI/CD
4. ⏳ Alertas configuradas
5. ⏳ Dashboard personal creado

**Meta:** Detectar y resolver problemas ANTES de que los clientes los vean 🎯
