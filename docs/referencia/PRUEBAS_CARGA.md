# Pruebas de rendimiento y carga

Historia **HT-20 Pruebas de rendimiento y carga** (Jira SCRUM-193), Sprint 5. Ejecución del 23 de noviembre de 2026. La herramienta es [`scripts/carga/carga.mjs`](../../scripts/carga/carga.mjs) (Node 20+, sin dependencias, carga de tasa abierta) y los resultados en bruto están en [`docs/referencia/carga/`](carga/).

## T055 · Escenarios y umbrales

| Escenario | Dónde | Qué hace | Umbral |
| --- | --- | --- | --- |
| `catalogo` | Staging | `barbershop`, `services`, `barbers` y `products`, rampa de 2 a 15 req/s en 3 minutos | p95 < 1 s y menos de 1 % de errores |
| `disponibilidad` | Staging | Horarios de un barbero a 0.4 req/s durante 60 s (el límite es 30 por minuto por IP) | p95 < 1 s |
| `rafaga-limite` | Staging | 35 consultas de horarios seguidas | 429 a partir de la 31 |
| `login-limite` | Staging | 7 intentos de login con un correo inventado, sin credenciales reales | 429 a partir del 6 |
| `demo-sesion`, `demo-panel`, `demo-reserva-concurrente` | Demostración local | Flujo con sesión: horarios, mis citas, panel y 20 reservas simultáneas del mismo horario | Una sola cita creada |

Los escenarios con sesión solo corren contra la demostración local aislada (`scripts/demo/levantar-demo.ps1`, `127.0.0.1:8001`, cuentas `demo-*@urbanblade.test`): contra staging no se usan credenciales.

La herramienta tiene un **freno automático**: si en las últimas 40 respuestas más del 5 % fallan o el p95 pasa de 3 s, deja de enviar peticiones. Se añadió después del incidente descrito abajo.

## T056 · Resultados en staging

| Escenario | Peticiones | Resultado | p50 | p95 | p99 |
| --- | --- | --- | --- | --- | --- |
| `disponibilidad` | 24 | 24 respuestas 200 | 222 ms | 2,629 ms | 4,897 ms |
| `catalogo` | 1,890 | 537 respuestas 200 y 1,353 sin respuesta en 15 s | 634 ms | 8,349 ms | 14,878 ms |
| `rafaga-limite` | 35 | 35 respuestas 200, ningún 429 | 154 ms | 277 ms | |
| `login-limite` | 7 | 7 respuestas 422, ningún 429 | | | |

### Capacidad del catálogo

Métricas del balanceador por minuto durante la rampa:

| Ritmo aproximado | Respuesta media |
| --- | --- |
| 1.4 req/s | 0.22 s |
| 7 req/s | 1.94 s |
| 10.5 req/s | 11 s |
| 12.7 req/s | 56 s; después, 0 hosts sanos |

**Staging sostiene unas 5 a 6 req/s de catálogo.** A partir de unas 7 req/s se degrada y hacia las 10 req/s colapsa.

### Incidente

La rampa a 15 req/s **dejó la API de staging sin servicio unos 4 minutos** (de 05:01 a 05:05 UTC del 23 de noviembre). La comprobación de salud `/up` comparte los mismos procesos de PHP, falló 5 veces seguidas y ECS reemplazó la tarea, que se recuperó sola. A partir de entonces la herramienta tiene el freno automático, y contra staging no se pasa de 5 req/s sin acordarlo antes.

### Causa de la saturación

La tarea es pequeña (0.5 vCPU, 1 GB, 16 procesos de PHP-FPM) y la base es Atlas M0, un plan con tope de operaciones por segundo. Los registros de peticiones lentas mostraron que casi todo el tiempo de cada petición era MongoDB, y que la mayor parte no eran las consultas del catálogo:

| Origen de las operaciones lentas | Cantidad | Peso |
| --- | --- | --- |
| Colección `cache`: contadores de límites y de abuso | ~1,930 | 60 % |
| Laravel Pulse guardando métricas en Atlas (`pulse_entries` y transacciones abortadas) | ~1,030 | 32 % |
| Consultas reales del catálogo | ~150 | 5 % |

### Límites por IP rotos

`rafaga-limite` y `login-limite` no recibieron ningún 429, y el contador restante retrocedía entre peticiones seguidas. Con la imagen de staging en local se comprobó que Caddy (`php_fastcgi`) sustituía el `X-Forwarded-For` por la IP del balanceador: Laravel veía a todos los visitantes como uno de los dos nodos del ALB, y los límites eran compartidos por todo el sitio. Se corrige en el PR `fix/ip-real-detras-de-caddy` (barber#109). Tras desplegarlo hay que repetir estos dos escenarios.

## T057 · Optimización

1. **Pulse en SQLite local.** El diseño documentado (`config/database.php`, `.env.example`) era `PULSE_DB_CONNECTION=sqlite`, pero staging no definía la variable y Pulse escribía en Atlas. Ahora `sqlite` es el valor por defecto en `config/pulse.php`, así que staging lo toma sin cambiar la definición de la tarea.
2. **Caché del catálogo** ([`CatalogCache`](../../app/Support/CatalogCache.php)): servicios, productos sin filtros y la parte pública de barberos, 60 s en el almacén por defecto. Se borra al guardar o eliminar un servicio, producto, barbero o reseña, o al cambiar el nombre o la foto de un usuario. Los datos propios del cliente (`citas_conmigo`, `es_favorito`) se calculan aparte y nunca se guardan en ella.

**Medición** en la demostración local, con los contadores de límites en MongoDB como en staging: 120 peticiones de catálogo seguidas y los contadores de operaciones del servidor de MongoDB antes y después.

| Versión | Operaciones de MongoDB por petición | Inserciones de Pulse |
| --- | --- | --- |
| Antes (`main`, Pulse en MongoDB) | 10.1 | 270 |
| Después (caché y Pulse en SQLite) | 5.6 | 0 |

**45 % menos operaciones por petición.** La latencia en serie local no cambia (173 ms frente a 186 ms): el MongoDB local es rápido y lo que pesa ahí es el servidor de desarrollo de PHP. El beneficio está en el tope de operaciones de Atlas M0, que era el cuello de botella en staging.

### Pendientes y recomendaciones

- **Medir en staging después de desplegar**, a 4 req/s como máximo y con el freno activo, y repetir `rafaga-limite` y `login-limite`.
- Las operaciones que quedan (unas 5.6 por petición) son los contadores de límites en MongoDB. Con barber#109 el guardián de abuso vuelve a contar por IP real y suma unas 3 operaciones más. Si hace falta más capacidad: llevar los contadores gruesos (catálogo y abuso) a un almacén local por tarea y dejar en MongoDB solo los sensibles, como login. Es un intercambio de seguridad que debe decidir el equipo.
- Separar la comprobación de salud del grupo de procesos que atiende la API, o subir su tolerancia, para que una saturación pasajera no haga que ECS reemplace la tarea.
- Los escenarios con sesión de la demostración (`demo-sesion`, `demo-panel`, `demo-reserva-concurrente`) están escritos pero todavía no se han ejecutado.
