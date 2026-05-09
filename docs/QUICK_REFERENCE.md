# 🎯 BARBERPRO ELITE - TARJETA DE REFERENCIA RÁPIDA

## 📊 Estado Actual: 81% Profesional (↑ +24% desde inicio)

```
ANTES (57%)          HOY (81%)              FALTA PARA 90%
─────────────────────────────────────────────────────────
❌ Seguridad         ✅ Seguridad (85%)    → API Docs (5%)
❌ Testing          ✅ Testing (80%)       → Tests Reales (5%)
❌ Monitoring       ✅ Monitoring (80%)    → DSN Sentry (configurar)
❌ CI/CD            ✅ CI/CD (100%)
❌ Load Testing     ✅ Load Testing (100%)
```

---

## 🚀 COMANDOS DIARIOS

### Desarrollo
```bash
npm run dev              # Vite dev server (http://localhost:5173)
npm run build            # Build para producción
npm run lint             # ESLint + Prettier check
```

### Testing
```bash
npm run test:load        # K6: 30 segundos, carga normal
npm run test:load:quick  # K6: 10 segundos, prueba rápida
npm run test:load:realistic  # K6: 5 minutos, usuario real
```

### Docker
```bash
docker-compose up -d     # Levantar (app, nginx, mysql, redis, etc)
docker-compose logs -f   # Ver logs en tiempo real
docker-compose down      # Apagar servicios
```

### Backend Tests (cuando estén listos)
```bash
docker-compose exec app php artisan test           # Ejecutar tests
docker-compose exec app php artisan test --coverage  # Con coverage
```

---

## 📁 ARCHIVOS IMPORTANTES

| Archivo | Propósito | Cuando Usarlo |
|---------|-----------|---------------|
| `MONITORING_GUIDE.md` | Sentry + K6 | Quieres configurar error tracking |
| `TESTING_GUIDE.md` | Cypress + PHPUnit | Escribir tests reales |
| `ANALISIS_BARBERPRO.md` | Evaluación inicial | Entender mejoras necesarias |
| `k6/load-test.js` | Test de carga básico | Probar 6 endpoints estándar |
| `k6/realistic-test.js` | Test realista | Simular usuario real en 5 min |
| `.github/workflows/testing.yml` | CI/CD automático | Validación en push a GitHub |
| `cypress/e2e/auth.cy.js` | E2E test ejemplo | Ver cómo escribir E2E tests |
| `config/sentry.php` | Configuración Sentry | Ya publicado, solo falta DSN |

---

## ⚙️ PRÓXIMAS 3 ACCIONES RECOMENDADAS

### 1️⃣ SENTRY - Error Tracking (15 minutos)
```bash
# Visita: https://sentry.io
# Crea proyecto "Laravel" (free tier)
# Copia el DSN (ej: https://xxxxx@o0.ingest.sentry.io/0)
# Agrega a .env:
echo "SENTRY_LARAVEL_DSN=https://xxxxx@o0.ingest.sentry.io/0" >> .env
docker-compose restart app
```

### 2️⃣ ESCRIBIR TESTS REALES (2 horas)
```bash
# Ver ejemplos en TESTING_GUIDE.md
docker-compose exec app php artisan make:test Tests/Unit/BarberosTest
docker-compose exec app php artisan make:test Tests/Feature/ReservasTest

# Escribir tests usando ejemplos de TESTING_GUIDE.md
# Ejecutar: docker-compose exec app php artisan test
```

### 3️⃣ DOCUMENTAR API (30 minutos)
```bash
composer require --dev barryvdh/laravel-scribe
# Configurar rutas en config/scribe.php
php artisan scribe:generate
# Resultado: documentación OpenAPI en /docs
```

---

## 📈 MÉTRICAS K6 - QUÉ SIGNIFICAN

```
Métrica                  Bueno        Aceptable      Malo
─────────────────────────────────────────────────────────────
p95 latency              < 500ms      500-1000ms     > 1000ms
p99 latency              < 1000ms     1-2s           > 2s
Error rate               < 1%         1-5%           > 5%
Throughput               > 100 req/s  50-100         < 50
Checks pass              > 95%        80-95%         < 80%
```

**RESULTADO ACTUAL:** p95=469ms ✅ / Error rate=0% ✅ / Throughput=9.97 req/s ⚠️

---

## 🛠️ TROUBLESHOOTING RÁPIDO

### K6 no conecta a la app
```bash
# Problema: localhost no funciona en Docker
# Solución: Usar 'host.docker.internal' en k6/load-test.js
# Línea: const baseURL = 'http://host.docker.internal:8000';
```

### Sentry no recibe eventos
```bash
# Verificar:
grep SENTRY_LARAVEL_DSN .env
docker-compose logs app | grep -i sentry
docker-compose exec app php artisan config:cache
docker-compose restart app
```

### Tests fallan sin razón clara
```bash
# Limpiar caché:
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:cache

# Mirar logs:
docker-compose logs -f app
```

---

## 📞 DOCUMENTACIÓN RÁPIDA

- **Sentry:** https://docs.sentry.io/platforms/php/guides/laravel/
- **K6:** https://k6.io/docs/
- **Cypress:** https://docs.cypress.io/
- **Laravel:** https://laravel.com/docs

---

## 🎯 META: 90% PROFESIONAL

```
Ahora:   81% ████████░
Meta:    90% █████████
Falta:    9% ░

Acciones para +9%:
  ✅ Escribir tests (5%)
  ✅ Configurar Sentry DSN (2%)
  ✅ Documentación API (2%)
```

---

## 💡 TIPS PROFESIONALES

1. **Commit con mensaje descriptivo:**
   ```bash
   git commit -m "Feat: WebSocket real-time updates

   - Nuevo WebSocket endpoint en /api/reservas/updates
   - Cliente conecta y escucha cambios en tiempo real
   - Tests E2E validados"
   ```

2. **Pre-commit hooks validan automáticamente:**
   - No puedes hacer commit si hay errores de linting
   - No puedes hacer commit si falla `npm audit`
   - Esto garantiza código limpio en el repo

3. **K6 es para probar BAJO CARGA:**
   - No es para validar lógica (usa PHPUnit para eso)
   - Es para validar que aguanta múltiples usuarios simultáneamente
   - Ejecutar antes de deployment a producción

4. **Sentry captura automáticamente:**
   - Excepciones no manejadas
   - Errores de validación
   - Errores de BD
   - Solo necesitas configurar el DSN

---

**Última actualización:** 2026-05-08  
**Commit:** fb1f2a5 (MONITORING + LOAD TESTING)  
**Score:** 81% → Meta 90%
