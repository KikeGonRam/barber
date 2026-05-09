# 🧪 GUÍA COMPLETA DE TESTING - BARBERPRO ELITE

## 📋 ESTRUCTURA DE TESTING

### Frontend (E2E + UI)
```
cypress/
├── e2e/
│   ├── auth.cy.js           # Tests de autenticación
│   ├── reservas.cy.js       # Tests de reservas
│   ├── dashboard.cy.js      # Tests del dashboard
│   └── social.cy.js         # Tests de features sociales
├── support/
│   ├── commands.js          # Custom commands
│   └── e2e.js               # Setup global
└── cypress.config.js        # Configuración
```

### Backend (Unit + Feature)
```
tests/
├── Unit/
│   ├── BarberosTest.php     # Unit tests para Barberos
│   ├── ClientesTest.php     # Unit tests para Clientes
│   └── ReservasTest.php     # Unit tests para Reservas
├── Feature/
│   ├── AuthTest.php         # Tests de autenticación
│   ├── ReservasTest.php     # Tests de reservas
│   └── DashboardTest.php    # Tests del dashboard
└── TestCase.php             # Base test case
```

---

## 🚀 INSTALACIÓN RÁPIDA

### Frontend
```bash
npm install -D cypress @testing-library/dom
npx cypress open
```

### Backend (En Docker)
```bash
docker-compose exec app composer require --dev phpunit/phpunit:^11
```

---

## 📝 EJEMPLOS DE TESTS

### Frontend - Cypress (E2E)

#### Test de Autenticación
```javascript
// cypress/e2e/auth.cy.js
describe('Authentication Tests', () => {
  beforeEach(() => {
    cy.visit('http://localhost:8000')
  })

  it('debe permitir login con credenciales válidas', () => {
    cy.get('input[type="email"]').type('admin@barberia.local')
    cy.get('input[type="password"]').type('password')
    cy.get('button[type="submit"]').click()
    
    cy.url().should('include', '/dashboard')
    cy.get('h1').should('contain', 'Dashboard')
  })

  it('debe rechazar credenciales inválidas', () => {
    cy.get('input[type="email"]').type('invalid@email.com')
    cy.get('input[type="password"]').type('wrongpassword')
    cy.get('button[type="submit"]').click()
    
    cy.get('.alert-error').should('be.visible')
  })

  it('debe permitir logout', () => {
    cy.login('admin@barberia.local', 'password')
    cy.get('[data-testid="user-menu"]').click()
    cy.get('[data-testid="logout-btn"]').click()
    
    cy.url().should('eq', 'http://localhost:8000/')
  })
})
```

#### Test de Reservas
```javascript
// cypress/e2e/reservas.cy.js
describe('Reservas Tests', () => {
  beforeEach(() => {
    cy.login('cliente@example.com', 'password')
    cy.visit('http://localhost:8000/reservas')
  })

  it('debe mostrar calendario de disponibilidad', () => {
    cy.get('[data-testid="calendar"]').should('be.visible')
    cy.get('.calendar-day').should('have.length.greaterThan', 0)
  })

  it('debe permitir crear reserva', () => {
    cy.get('[data-testid="barbero-select"]').select('Juan Pérez')
    cy.get('[data-testid="servicio-select"]').select('Corte Classic')
    cy.get('[data-testid="fecha-input"]').type('2026-05-15')
    cy.get('[data-testid="hora-input"]').select('10:00')
    
    cy.get('button[type="submit"]').click()
    
    cy.get('.success-message').should('contain', 'Reserva creada exitosamente')
    cy.url().should('include', '/mis-reservas')
  })

  it('debe validar campos requeridos', () => {
    cy.get('button[type="submit"]').click()
    
    cy.get('.error-message').should('be.visible')
  })
})
```

### Backend - PHPUnit

#### Test Unitario
```php
// tests/Unit/ReservasTest.php
<?php

namespace Tests\Unit;

use App\Models\Reserva;
use App\Models\Barbero;
use App\Models\Cliente;
use Carbon\Carbon;
use Tests\TestCase;

class ReservasTest extends TestCase
{
    public function test_crear_reserva_con_datos_validos()
    {
        $barbero = Barbero::factory()->create();
        $cliente = Cliente::factory()->create();
        
        $reserva = Reserva::create([
            'barbero_id' => $barbero->id,
            'cliente_id' => $cliente->id,
            'fecha' => Carbon::tomorrow()->setHour(10),
            'duracion' => 30,
            'servicio' => 'Corte Classic'
        ]);
        
        $this->assertInstanceOf(Reserva::class, $reserva);
        $this->assertEquals($barbero->id, $reserva->barbero_id);
        $this->assertEquals(30, $reserva->duracion);
    }

    public function test_reserva_requiere_barbero()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Reserva::create([
            'cliente_id' => 1,
            'fecha' => Carbon::tomorrow(),
            'duracion' => 30
        ]);
    }

    public function test_calcular_precio_correctamente()
    {
        $barbero = Barbero::factory()->create();
        $cliente = Cliente::factory()->create();
        
        $reserva = Reserva::factory()->create([
            'barbero_id' => $barbero->id,
            'cliente_id' => $cliente->id,
            'servicio' => 'Corte Premium'
        ]);
        
        $this->assertEquals(150, $reserva->calcularPrecio());
    }
}
```

#### Test de Feature
```php
// tests/Feature/ReservasTest.php
<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Barbero;
use Carbon\Carbon;
use Tests\TestCase;

class ReservasTest extends TestCase
{
    public function test_cliente_puede_crear_reserva()
    {
        $cliente = Cliente::factory()->create();
        $barbero = Barbero::factory()->create();
        
        $this->actingAs($cliente)
            ->post('/api/reservas', [
                'barbero_id' => $barbero->id,
                'fecha' => Carbon::tomorrow()->setHour(10)->toIso8601String(),
                'duracion' => 30,
                'servicio' => 'Corte Classic'
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'barbero_id',
                'cliente_id',
                'fecha',
                'duracion'
            ]);
    }

    public function test_cliente_puede_ver_sus_reservas()
    {
        $cliente = Cliente::factory()->create();
        $reservas = Reserva::factory(3)->create(['cliente_id' => $cliente->id]);
        
        $this->actingAs($cliente)
            ->get('/api/mis-reservas')
            ->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_cliente_no_puede_editar_reserva_de_otro()
    {
        $cliente1 = Cliente::factory()->create();
        $cliente2 = Cliente::factory()->create();
        $reserva = Reserva::factory()->create(['cliente_id' => $cliente1->id]);
        
        $this->actingAs($cliente2)
            ->patch("/api/reservas/{$reserva->id}", [
                'fecha' => Carbon::tomorrow()->setHour(14)->toIso8601String()
            ])
            ->assertStatus(403);
    }
}
```

---

## 🏃 EJECUTAR TESTS

### Frontend (E2E)
```bash
# Interfaz gráfica
npm run test:e2e

# Headless (CI/CD)
npm run test:e2e:ci

# Específico
npx cypress run --spec "cypress/e2e/auth.cy.js"

# Con reporte
npx cypress run --reporter json --reporter-options "reportDir=cypress/results"
```

### Backend
```bash
# Todos los tests
docker-compose exec app php artisan test

# Solo unitarios
docker-compose exec app php artisan test --testsuite=Unit

# Solo features
docker-compose exec app php artisan test --testsuite=Feature

# Con coverage
docker-compose exec app php artisan test --coverage

# Test específico
docker-compose exec app php artisan test tests/Feature/ReservasTest.php
```

---

## 📊 COBERTURA DE TESTS

### Meta: 60%+ Coverage

```bash
# Generar reporte de cobertura
docker-compose exec app php artisan test --coverage

# Salida esperada:
# ┌─────────────┬──────────┬──────────┐
# │ Class       │ Methods  │ Lines    │
# ├─────────────┼──────────┼──────────┤
# │ Reserva     │ 90%      │ 85%      │
# │ Barbero     │ 80%      │ 75%      │
# │ Cliente     │ 75%      │ 70%      │
# │ Dashboard   │ 60%      │ 55%      │
# └─────────────┴──────────┴──────────┘
# TOTAL: 76% (Excelente!)
```

---

## 🔄 INTEGRACIÓN CONTINUA

### GitHub Actions (CI/CD)

Crear `.github/workflows/testing.yml`:

```yaml
name: Testing Pipeline

on: [push, pull_request]

jobs:
  frontend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '22'
      
      - run: npm ci
      - run: npm run lint
      - run: npm run test:e2e:ci
      
      - uses: actions/upload-artifact@v3
        if: always()
        with:
          name: cypress-screenshots
          path: cypress/screenshots

  backend-tests:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: barber_test
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      
      - run: composer install --no-interaction
      - run: php artisan migrate --env=testing
      - run: php artisan test --coverage
      
      - uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

---

## ✅ CHECKLIST TESTING

### Unit Tests
- [ ] Modelos principales
- [ ] Servicios
- [ ] Helpers/Utilities
- [ ] Validaciones

### Feature Tests
- [ ] Autenticación
- [ ] Autorización
- [ ] CRUD operations
- [ ] APIs

### E2E Tests
- [ ] Login/Logout
- [ ] Crear reserva
- [ ] Dashboard
- [ ] Social features

### Performance Tests
- [ ] Tiempo de carga < 3s
- [ ] API response < 200ms
- [ ] Database queries optimizadas

---

## 🚨 TROUBLESHOOTING

### Cypress no inicia
```bash
# Limpiar caché
rm -rf ~/.cypress
npm run test:e2e
```

### Fallan tests en Docker
```bash
# Esperar a que MySQL esté listo
sleep 10
docker-compose exec app php artisan migrate
docker-compose exec app php artisan test
```

### Coverage muy bajo
```bash
# Identificar archivos sin tests
php artisan test --coverage-report

# Mejorar cobertura
# 1. Agregar tests para funciones críticas
# 2. Usar factories para datos de prueba
# 3. Mockear dependencias externas
```

---

## 📈 MÉTRICAS DE ÉXITO

```
Meta Final:
✅ 60%+ Code Coverage
✅ Todos los tests pasando
✅ CI/CD automático en PRs
✅ Performance tests incluidos
✅ E2E coverage de flujos críticos

Estándares:
- Unit tests: 80%+
- Feature tests: 70%+
- E2E tests: Flujos críticos
- Total coverage: 60%+
```

---

## 🎯 PRÓXIMOS PASOS

1. **Instalación** (15 minutos)
   - Cypress en frontend
   - PHPUnit en backend

2. **Crear tests básicos** (1-2 horas)
   - Auth tests
   - Reservas tests
   - Dashboard tests

3. **Ejecutar locally** (30 minutos)
   - `npm run test:e2e`
   - `docker-compose exec app php artisan test`

4. **GitHub Actions** (1 hora)
   - Crear workflow
   - Configurar triggers
   - Validar en PRs

5. **Coverage** (30 minutos)
   - Medir cobertura actual
   - Identificar gaps
   - Mejorar coverage a 60%+

