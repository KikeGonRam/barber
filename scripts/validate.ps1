#!/usr/bin/env powershell

# Script de Validación Completa - BarberPro Elite

Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Magenta
Write-Host "║                                                                ║" -ForegroundColor Magenta
Write-Host "║          🧪 VALIDACIÓN COMPLETA DE BARBERPRO ELITE            ║" -ForegroundColor Magenta
Write-Host "║                                                                ║" -ForegroundColor Magenta
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Magenta

# Cambiar a directorio del proyecto
Set-Location "C:\Users\luis1\Documents\UrbanBlade\barber"

# FASE 1: Verificar dependencias
Write-Host "1️⃣  FASE 1: Verificando dependencias..." -ForegroundColor Cyan

@(
    ('npm', 'npm --version'),
    ('Docker', 'docker --version'),
    ('Docker Compose', 'docker-compose --version'),
    ('K6', 'k6 --version'),
    ('Git', 'git --version')
) | ForEach-Object {
    $name, $cmd = $_
    try {
        $output = Invoke-Expression $cmd 2>&1
        Write-Host "  ✅ $name: $($output[0])" -ForegroundColor Green
    } catch {
        Write-Host "  ❌ $name: NO INSTALADO" -ForegroundColor Red
    }
}

# FASE 2: Verificar Docker
Write-Host "`n2️⃣  FASE 2: Verificando servicios Docker..." -ForegroundColor Cyan

docker-compose ps --format="table {{.Service}}\t{{.Status}}"

Write-Host "`n  Espera a que todos estén 'healthy' antes de continuar" -ForegroundColor Yellow

# FASE 3: Verificar instalaciones npm
Write-Host "`n3️⃣  FASE 3: Instalando dependencias npm..." -ForegroundColor Cyan
npm install --silent 2>&1 | Select-String -Pattern "up to date|added" | Select-Object -First 1

# FASE 4: Linting
Write-Host "`n4️⃣  FASE 4: Ejecutando linting..." -ForegroundColor Cyan
npm run lint 2>&1 | Tail -5

# FASE 5: Cypress E2E Tests
Write-Host "`n5️⃣  FASE 5: Cypress E2E Tests (si es posible)..." -ForegroundColor Cyan

Write-Host "  Comando: npm run test:e2e" -ForegroundColor Yellow
Write-Host "  Nota: Asegúrate que la app esté levantada en http://localhost:8000" -ForegroundColor Yellow

# FASE 6: Load Testing - Básico
Write-Host "`n6️⃣  FASE 6: Load Testing - Prueba Básica (10 seg)..." -ForegroundColor Cyan

Write-Host "  Ejecutando K6 load test..." -ForegroundColor Yellow
k6 run --duration 10s --vus 3 k6/load-test.js 2>&1 | Select-String -Pattern "checks|requests|data_received" | Select-Object -First 5

# FASE 7: Verificar Sentry
Write-Host "`n7️⃣  FASE 7: Verificando configuración Sentry..." -ForegroundColor Cyan

$sentryDsn = Select-String -Path ".env" -Pattern "SENTRY_LARAVEL_DSN"
if ($sentryDsn) {
    Write-Host "  ✅ Sentry configurado en .env" -ForegroundColor Green
} else {
    Write-Host "  ⚠️  Sentry NO configurado. Visita https://sentry.io y obtén tu DSN" -ForegroundColor Yellow
}

# FASE 8: Verificación de Archivos
Write-Host "`n8️⃣  FASE 8: Archivos de Configuración..." -ForegroundColor Cyan

@(
    'cypress.config.js',
    'k6/load-test.js',
    'k6/realistic-test.js',
    '.eslintrc.json',
    '.prettierrc',
    'config/sentry.php'
) | ForEach-Object {
    if (Test-Path $_) {
        Write-Host "  ✅ $_" -ForegroundColor Green
    } else {
        Write-Host "  ❌ $_ FALTA" -ForegroundColor Red
    }
}

# Resumen
Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║                     ✅ VALIDACIÓN COMPLETA                      ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Green

Write-Host "📝 PRÓXIMOS PASOS:" -ForegroundColor Magenta
Write-Host "  1. npm run test:load:quick    # Prueba rápida de carga (10s)" -ForegroundColor Cyan
Write-Host "  2. npm run test:load:realistic# Simular usuario real (5 min)" -ForegroundColor Cyan
Write-Host "  3. npm run lint               # Verificar estilo de código" -ForegroundColor Cyan
Write-Host "  4. Configurar Sentry con DSN  # Error tracking en tiempo real" -ForegroundColor Cyan

Write-Host "`n🚀 Documentación:" -ForegroundColor Yellow
Write-Host "  - TESTING_GUIDE.md            # Tests E2E y unitarios" -ForegroundColor Cyan
Write-Host "  - MONITORING_GUIDE.md         # Sentry + K6 Load Testing" -ForegroundColor Cyan
Write-Host "  - ANALISIS_BARBERPRO.md       # Análisis del proyecto" -ForegroundColor Cyan

Write-Host "`n" -ForegroundColor White
