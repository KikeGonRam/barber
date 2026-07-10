import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Métricas personalizadas
const loginDuration = new Trend('login_duration');
const loginFailRate = new Rate('login_failures');
const createReservaDuration = new Trend('create_reserva_duration');
const getReservasDuration = new Trend('get_reservas_duration');

export const options = {
  stages: [
    { duration: '1m', target: 30 },      // Rampa: 1 minuto hasta 30 usuarios
    { duration: '3m', target: 30 },      // Mantener: 3 minutos a 30 usuarios
    { duration: '1m', target: 0 },       // Bajada: 1 minuto a 0 usuarios
  ],
  
  thresholds: {
    'http_req_duration': ['p(95)<800', 'p(99)<1500'],
    'http_req_failed': ['rate<0.05'],                    // <5% fallos
    'login_duration': ['p(95)<1000'],
    'login_failures': ['rate<0.02'],                     // <2% login failures
    'create_reserva_duration': ['p(95)<1200'],
  },
};

export default function () {
  const baseURL = 'http://localhost:8000';
  let token = '';

  // ==================== FASE 1: Login ====================
  group('01 - Autenticación Usuario', () => {
    const startTime = Date.now();
    
    const loginPayload = JSON.stringify({
      email: 'admin@barberpro.local',
      password: 'password',
    });

    const loginRes = http.post(`${baseURL}/api/login`, loginPayload, {
      headers: { 'Content-Type': 'application/json' },
      tags: { name: 'LoginAPI' },
    });

    const duration = Date.now() - startTime;
    loginDuration.add(duration);
    loginFailRate.add(loginRes.status !== 200);

    check(loginRes, {
      'login exitoso': (r) => r.status === 200,
      'respuesta tiene token': (r) => {
        try {
          const data = JSON.parse(r.body);
          return data.token !== undefined;
        } catch (err) {
          return false;
        }
      },
      'login tiempo < 1s': (r) => r.timings.duration < 1000,
    });

    // Extraer token si login fue exitoso
    if (loginRes.status === 200) {
      try {
        const data = JSON.parse(loginRes.body);
        token = data.token;
      } catch (e) {
        token = '';
      }
    }

    sleep(2);
  });

  // Si no hay token, no continuar
  if (!token) {
    return;
  }

  const authHeaders = {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`,
  };

  // ==================== FASE 2: Ver Dashboard ====================
  group('02 - Cargar Dashboard', () => {
    const dashRes = http.get(`${baseURL}/dashboard`, {
      headers: authHeaders,
      tags: { name: 'Dashboard' },
    });

    check(dashRes, {
      'dashboard carga': (r) => r.status === 200,
      'dashboard tiempo < 600ms': (r) => r.timings.duration < 600,
    });

    sleep(1);
  });

  // ==================== FASE 3: Obtener Reservas ====================
  group('03 - Cargar Reservas', () => {
    const startTime = Date.now();
    
    const resRes = http.get(`${baseURL}/api/reservas`, {
      headers: authHeaders,
      tags: { name: 'GetReservas' },
    });

    const duration = Date.now() - startTime;
    getReservasDuration.add(duration);

    check(resRes, {
      'reservas obtenidas': (r) => r.status === 200,
      'reservas tiene array': (r) => {
        try {
          const data = JSON.parse(r.body);
          return Array.isArray(data.data);
        } catch (err) {
          return false;
        }
      },
      'reservas tiempo < 500ms': (r) => r.timings.duration < 500,
    });

    sleep(2);
  });

  // ==================== FASE 4: Crear Reserva ====================
  group('04 - Crear Nueva Reserva', () => {
    const startTime = Date.now();
    
    const newReserva = JSON.stringify({
      barbero_id: 1,
      cliente_id: 1,
      fecha: '2026-05-15',
      hora: '10:00',
      servicio: 'Corte + Barba',
      notas: 'Test de carga - ' + Date.now(),
    });

    const createRes = http.post(`${baseURL}/api/reservas`, newReserva, {
      headers: authHeaders,
      tags: { name: 'CreateReserva' },
    });

    const duration = Date.now() - startTime;
    createReservaDuration.add(duration);

    check(createRes, {
      'reserva creada': (r) => r.status === 201 || r.status === 200,
      'respuesta tiene ID': (r) => {
        try {
          const data = JSON.parse(r.body);
          return data.id !== undefined || (data.data !== undefined && data.data.id !== undefined);
        } catch (err) {
          return false;
        }
      },
      'crear tiempo < 1.2s': (r) => r.timings.duration < 1200,
    });

    sleep(2);
  });

  // ==================== FASE 5: Búsqueda ====================
  group('05 - Búsqueda de Reservas', () => {
    const searchRes = http.get(`${baseURL}/api/reservas/search?query=Test`, {
      headers: authHeaders,
      tags: { name: 'SearchReservas' },
    });

    check(searchRes, {
      'búsqueda funciona': (r) => r.status === 200 || r.status === 404,
      'búsqueda tiempo < 400ms': (r) => r.timings.duration < 400,
    });

    sleep(1);
  });

  // ==================== FASE 6: Estadísticas ====================
  group('06 - Ver Estadísticas', () => {
    const statsRes = http.get(`${baseURL}/api/estadisticas`, {
      headers: authHeaders,
      tags: { name: 'Statistics' },
    });

    check(statsRes, {
      'stats disponibles': (r) => r.status === 200,
      'stats tiempo < 800ms': (r) => r.timings.duration < 800,
    });

    sleep(1);
  });

  // ==================== FASE 7: Logout ====================
  group('07 - Cerrar Sesión', () => {
    const logoutRes = http.post(`${baseURL}/api/logout`, {}, {
      headers: authHeaders,
      tags: { name: 'Logout' },
    });

    check(logoutRes, {
      'logout exitoso': (r) => r.status === 200 || r.status === 401,
      'logout tiempo < 200ms': (r) => r.timings.duration < 200,
    });

    sleep(1);
  });
}
