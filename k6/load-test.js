import http from 'k6/http';
import { check, group, sleep } from 'k6';

export const options = {
  vus: 10,                    // 10 usuarios simultáneos
  duration: '30s',            // 30 segundos total
  
  stages: [
    { duration: '10s', target: 5 },   // Rampa: 0 → 5 usuarios en 10s
    { duration: '10s', target: 10 },  // Rampa: 5 → 10 usuarios en 10s
    { duration: '10s', target: 0 },   // Bajada: 10 → 0 usuarios en 10s
  ],
  
  // Umbrales de éxito
  thresholds: {
    'http_req_duration': ['p(95)<500', 'p(99)<1000'],  // 95% < 500ms, 99% < 1s
    'http_req_failed': ['rate<0.1'],                     // <10% de fallos
    'http_reqs': ['rate>100'],                           // >100 req/s
  },
};

export default function () {
  const baseURL = 'http://localhost:8000';
  const headers = {
    'Content-Type': 'application/json',
  };

  // ==================== GRUPO 1: Dashboard ====================
  group('Dashboard - Página Principal', () => {
    const res = http.get(`${baseURL}/dashboard`);
    
    check(res, {
      'dashboard status 200': (r) => r.status === 200,
      'dashboard tiempo < 500ms': (r) => r.timings.duration < 500,
      'dashboard contiene html': (r) => r.body.length > 100,
    });

    sleep(1);
  });

  // ==================== GRUPO 2: API Reservas ====================
  group('API - Obtener Reservas', () => {
    const res = http.get(`${baseURL}/api/reservas`);
    
    check(res, {
      'api status 200': (r) => r.status === 200,
      'api tiempo < 300ms': (r) => r.timings.duration < 300,
      'api devuelve JSON': (r) => r.headers['Content-Type'].includes('application/json'),
      'api contiene data': (r) => {
        try {
          return JSON.parse(r.body).data !== undefined;
        } catch (err) {
          return false;
        }
      },
    });

    sleep(0.5);
  });

  // ==================== GRUPO 3: Búsqueda ====================
  group('API - Búsqueda de Reservas', () => {
    const params = {
      params: {
        'query': 'Juan',
        'status': 'pendiente',
      },
    };
    
    const res = http.get(`${baseURL}/api/reservas/search`, params);
    
    check(res, {
      'search status 200 o 404': (r) => r.status === 200 || r.status === 404,
      'search tiempo < 400ms': (r) => r.timings.duration < 400,
    });

    sleep(0.5);
  });

  // ==================== GRUPO 4: Estadísticas ====================
  group('API - Estadísticas', () => {
    const res = http.get(`${baseURL}/api/estadisticas`);
    
    check(res, {
      'stats status 200': (r) => r.status === 200,
      'stats tiempo < 600ms': (r) => r.timings.duration < 600,
    });

    sleep(0.5);
  });

  // ==================== GRUPO 5: Login ====================
  group('API - Autenticación', () => {
    const payload = JSON.stringify({
      email: 'admin@barberpro.local',
      password: 'password',
    });

    const res = http.post(`${baseURL}/api/login`, payload, { headers });
    
    check(res, {
      'login status 200 o 401': (r) => r.status === 200 || r.status === 401,
      'login tiempo < 800ms': (r) => r.timings.duration < 800,
      'login respuesta JSON': (r) => {
        try {
          return JSON.parse(r.body) !== undefined;
        } catch (err) {
          return false;
        }
      },
    });

    sleep(1);
  });

  // ==================== GRUPO 6: Health Check ====================
  group('Health - Verificación Sistema', () => {
    const res = http.get(`${baseURL}/health`);
    
    check(res, {
      'health status 200': (r) => r.status === 200,
      'health tiempo < 100ms': (r) => r.timings.duration < 100,
    });

    sleep(0.5);
  });

  sleep(2);
}
