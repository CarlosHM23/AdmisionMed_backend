// Ejecutar con `k6 run tests/Load/admission-load.js` y `BASE_URL=https://test.example k6 run tests/Load/admission-load.js`.
// Genera solicitudes de autenticación con credenciales sintéticas y reporta la latencia p95.
import http from 'k6/http';
import { check } from 'k6';

http.setResponseCallback(http.expectedStatuses(401));

export const options = {
  stages: [
    { duration: '10s', target: 10 },
    { duration: '20s', target: 10 },
    { duration: '10s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],
    http_req_failed: ['rate<0.01'],
  },
};

const baseUrl = __ENV.BASE_URL || 'http://127.0.0.1:8000';

export default function () {
  const response = http.post(
    `${baseUrl}/api/login`,
    JSON.stringify({
      email: `synthetic-${__VU}-${__ITER}@example.invalid`,
      password: 'synthetic-load-test-password',
    }),
    { headers: { 'Content-Type': 'application/json' } },
  );

  check(response, {
    'synthetic credentials are rejected': (res) => res.status === 401,
  });
}
