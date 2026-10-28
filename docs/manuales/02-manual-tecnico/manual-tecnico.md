# Manual Técnico

Backend, API, datos, servicios, seguridad, pruebas y contrato de UrbanBlade.

| Dato | Valor |
| --- | --- |
| Documento | Manual Técnico de UrbanBlade (API Laravel y clientes web y Android) |
| Versión | 2.0 · 28 de octubre de 2026 |
| Audiencia | Desarrolladores y revisores técnicos |
| Fuente | Repositorios `barber` (API), `frontend-urban` (web) y `UrbanBladeMobile` (Android), revisados en esa fecha |
| Clasificación | Uso interno. Sin credenciales; los nombres de variables aparecen sin valores |

## 1. Alcance y panorama

UrbanBlade son tres productos que comparten una sola API: un **backend Laravel** (`barber`), un **frontend Nuxt** (`frontend-urban`) y una **aplicación Android** (`UrbanBladeMobile`). Un cuarto proyecto, **Spark**, es un análisis académico de datos con una integración de lectura separada.

La regla que ordena el sistema: **solo la API habla con la base de datos operativa**. La web y Android consumen HTTP con un token Bearer y no tienen credenciales de MongoDB.

```text
  Navegador / PWA (Nuxt)  ──┐
                            ├──HTTPS + Bearer──►  API Laravel  ──►  MongoDB operativo
  App Android (Kotlin)    ──┘                       │   │  └──►  MongoDB analítico (lectura)
                                                    │   ├──────►  Redis (caché y colas, según entorno)
  Spark (PySpark)  ──lectura──► MongoDB              │   ├──────►  Discos de archivos / S3
                                                    │   └──────►  Stripe · Google · correo · push · Twilio · Ollama
```

## 2. Stack tecnológico

| Capa | Tecnología | Versión declarada |
| --- | --- | --- |
| Lenguaje | PHP | `^8.3` en Composer; las imágenes usan PHP 8.4 |
| Framework | Laravel | `^13.0` |
| Base de datos | MongoDB con `mongodb/laravel-mongodb` | `^5.0` |
| Autenticación | Tokens Bearer propios (`MobileApiToken`); Socialite para Google | Socialite `^5.31` |
| Roles y permisos | `spatie/laravel-permission` | `^7.2` |
| Auditoría | `spatie/laravel-activitylog` | `^5.0` |
| Pagos | `stripe/stripe-php` | `^20.3` |
| PDF y Excel | `barryvdh/laravel-dompdf`, `maatwebsite/excel` | `^3.1`, `^3.1` |
| Imágenes | `intervention/image` | `^3.0` |
| OCR de comprobantes | `thiagoalessio/tesseract_ocr` | `^2.13` |
| Notificaciones push | `minishlink/web-push` (web) y FCM (Android) | `^11.0` |
| Archivos | `league/flysystem-aws-s3-v3` | `^3.0` |
| Observabilidad | `laravel/pulse`, `sentry/sentry-laravel` | `^1.8`, `^4.25` |
| Frontend | Nuxt 4, Vue 3, TypeScript, Tailwind CSS 4 | Nuxt `^4.6.2`, Vue `^3.5` |
| Android | Kotlin, Jetpack Compose, MVVM, Retrofit | Kotlin 2.1 |

> **Nota** Las versiones salen de `composer.json` y `package.json`. Consúltalos antes de fijar una versión en otro documento: las notas antiguas hablaban de Laravel 12 y Blade, y eso ya no es cierto.

## 3. Estructura del repositorio `barber`

| Carpeta | Contenido |
| --- | --- |
| `routes/api.php` | Rutas de la API (`/api/v1`). Hay 263 rutas registradas, 208 de ellas bajo `api/`. |
| `app/Http/Controllers/Api` | Controladores por dominio: Appointment, Payment, Admin, Inventory, Order, Social, Barber, Chatbot, etc. |
| `app/Http/Middleware` | Autenticación, mantenimiento, límites y filtros (ver capítulo 6). |
| `app/Http/Requests` y `Resources` | Validación de entrada y forma pública de las respuestas. |
| `app/Services` | Reglas de negocio por dominio (ver capítulo 5). |
| `app/Models` | 40 modelos MongoDB. |
| `app/Jobs`, `app/Notifications` | Trabajo diferido y notificaciones multicanal. |
| `app/Console/Commands` | Comandos programados y de mantenimiento. |
| `app/Support` | Guardas de entorno, almacenamiento de comprobantes e imágenes. |
| `config/` | Configuración (abuse, auth, cache, database, filesystems, observability, scribe, etc.). |
| `tests/` | Pruebas por tipo: `UT`, `SIT`, `UAT` y `OAT`. |
| `docs/` | Documentación (este manual, referencia técnica y operación). |
| `.docker/` | Imágenes: desarrollo y staging (PHP-FPM + Caddy). |

## 4. La API

### 4.1 Convenciones

- **Base:** `/api/v1`. Todas las respuestas son JSON (`ForceJsonResponse`).
- **Autenticación:** cabecera `Authorization: Bearer <token>`. El login devuelve el token y la plataforma (`web` o móvil).
- **Validación:** los errores de entrada devuelven `422` con los mensajes por campo, que el frontend muestra junto a cada control.
- **Límites:** las rutas sensibles usan `throttle` por IP o por usuario. Los contadores se guardan en la caché configurada (`CACHE_LIMITER_STORE`).
- **Mantenimiento:** el middleware `CheckMaintenanceMode` responde `503` y los clientes muestran una pantalla de mantenimiento.
- **Webhook de Stripe:** `POST /api/stripe/webhook` (sin `/v1`), autorizado por la **firma de Stripe**, no por token de usuario.

### 4.2 Familias de rutas

| Familia | Rutas aprox. | Qué cubre |
| --- | --- | --- |
| `admin/*` | 49 | Administración: usuarios, roles, servicios, planes, moderación, sistema. |
| `appointments/*` | 16 | Disponibilidad, reserva, estados, enlaces de gestión. |
| `auth/*` | 11 | Registro, login, Google, refresco, cierre de sesión, recuperación. |
| `barber/*` y `barbers/*` | 15 | Agenda, horario, portafolio y desempeño del barbero; catálogo público. |
| `payments/*`, `deposits/*`, `no-show-fees/*`, `cash-closes/*` | 19 | Cobros, comprobantes, depósitos, cargos por inasistencia y cierre de caja. |
| `inventory/*`, `orders/*` | 14 | Productos, movimientos, pedidos y entrega. |
| `profile/*`, `notifications/*`, `push/*` | 17 | Perfil, preferencias, centro de avisos y suscripciones push. |
| `social/*` | 8 | Muro, comentarios, reacciones, guardados, reportes. |
| `chatbot/*` | 7 | Asistente virtual. |
| `memberships/*`, `packages/*`, `gift-cards/*`, `referrals/*` | 16 | Membresías, paquetes, regalos y referidos. |
| `reports/*`, `dashboard/*`, `analytics`, `campaigns/*` | 8+ | Reportes, paneles, analítica y campañas. |

### 4.3 Contrato OpenAPI

El contrato vive en `public/docs/openapi.yaml`. `scripts/verificar_contrato_api.mjs` lo compara con `route:list --json` en cada CI, y el frontend mantiene una copia en `contract/openapi.yaml` de la que genera sus tipos. **No se regenera Scribe contra un entorno operativo**: la generación puede hacer llamadas reales. Procedimiento en `docs/referencia/PLAN_CONTRATO_API.md`.

## 5. Dominios y servicios

El backend es un **monolito modular por dominios**. Los controladores orquestan; las reglas viven en `app/Services`.

| Dominio | Servicios principales | Invariantes que protege |
| --- | --- | --- |
| Citas | `AppointmentService`, `AppointmentStatusService`, `ServiceStartGuard`, `ServiceTimeService`, `WaitlistService`, `AppointmentManageLinkService` | Disponibilidad, transiciones de estado, condiciones para iniciar. |
| Pagos | `PaymentService`, `DepositService`, `NoShowFeeService`, `StripePaymentService`, `ServiceTicketService`, `CashCloseService` | Importes calculados en servidor; pago y cita tienen estados distintos. |
| Inventario y pedidos | `InventoryService`, `OrderService`, `CartService` | Precio y stock se vuelven a leer en el servidor; operaciones consistentes. |
| Lealtad | `LoyaltyService`, `RaffleService`, `ReferralService`, `MembershipService`, `MemberCardService` | Niveles, descuentos, puntos y su caducidad. |
| Paquetes y regalos | `PackageService`, `GiftCardService` | Compra, saldo, vigencia y conciliación. |
| Barberos | `BarberPerformanceService`, `BarberReviewService`, `BarberCommissionService` | Reseñas, comisiones y desempeño. |
| Reportes y analítica | `ReportService`, `ReportNarrative`, `AnalyticsInsightService`, `OperationalAnalyticsService`, `PredictionService`, `DashboardService`, `BusinessInsightService` | Resultados por rol y periodo; derivados separados de la operación. |
| Comunicación | `MessagingService`, `FcmPushService`, `WebPushService`, `CampaignDispatcher` | Entrega según canales, preferencias y workers. |
| Asistente | `ChatbotIntelligenceService`, `ChatbotContextService`, `OllamaService`, `GeminiService`, `ChatbotLearningService` | Proveedor intercambiable (Ollama local o Gemini). |
| Sistema | `DatabaseBackupService`, `QueueFailureMonitor`, `ScheduledTaskMonitor` | Respaldo ZIP, trabajos fallidos y tareas programadas. |

### 5.1 Máquina de estados de una cita

```text
pendiente  -> confirmada | cancelada
confirmada -> en_proceso | completada | no_asistio | cancelada
en_proceso -> completada | cancelada
completada, cancelada y no_asistio son estados terminales
```

- **Cobrar** requiere `confirmada`, `en_proceso` o `completada`.
- **Iniciar** exige además fecha y margen válidos y el pago resuelto (`ServiceStartGuard`).
- Cobrar **no** equivale a completar el servicio.

### 5.2 Lealtad

Los niveles se calculan por **citas completadas**: `nuevo` (0), `regular` (5), `vip` (10) y `leyenda` (20), con descuentos de 0, 5, 10 y 15 %. Una cita completada da 10 puntos y una reseña 5. El canje se limita al 50 % del total ya con descuento. A los 180 días sin citas completadas se baja un nivel y a los 365 caducan los puntos.

### 5.3 Pagos con Stripe

Se usan Payment Intents, Customers y tarjetas guardadas, reembolsos, productos y precios, y suscripciones de membresía. El webhook procesa eventos de pago, suscripción e *invoice*. Las claves secretas viven solo en el backend; el frontend usa la clave publicable.

## 6. Autenticación, autorización y seguridad

| Mecanismo | Detalle |
| --- | --- |
| Tokens | `MobileApiToken` guarda el **hash SHA-256**, nunca el token plano. Vigencia deslizante: 30 días web y 180 móvil (`config/auth.php`). |
| Google | Web con Socialite; Android envía un ID token que el servidor verifica. |
| Roles | Administrador, recepcionista, barbero, cliente e ingeniero. `role.custom` y `permission.custom` restringen rutas. |
| Pertenencia | Los controladores validan que la cita, el pago o el perfil pertenezcan al usuario; el rol por sí solo no basta. |
| Entradas | `RejectMongoOperators` bloquea operadores Mongo (`$ne`, `$gt`, …) que lleguen desde el cliente. |
| Proxies | `TrustProxyChain` confía solo en `X-Forwarded-For`, con `TRUSTED_PROXY_HOPS` proxies propios. |
| Abuso | `BlockAbusiveIps` bloquea IP abusivas; `config/abuse.php` define los umbrales. |
| Comprobantes | `ReceiptStorage` sirve comprobantes con URL temporal de 15 minutos cuando se usa S3. |
| Imágenes | `UploadedImage` valida y optimiza cada archivo subido. |
| Registro | El registro público y Google no permiten elegir un rol administrativo. |

> **Importante** El menú del frontend no es una barrera de seguridad. La API valida rol, permiso y pertenencia en cada petición.

## 7. Persistencia de datos

| Conexión o servicio | Contenido | Nota |
| --- | --- | --- |
| `mongodb` | Datos operativos (usuarios, citas, pagos, inventario…). | Base y URI por entorno. |
| `mongodb_analytics` | Derivados de analítica que Laravel lee. | Si faltan variables propias hereda la conexión operativa. |
| `mongo-dev` (`rsdev`) | Desarrollo local, `urbanblade_dev`. | Persistente, volumen aparte. |
| `mongo-test` (`rs0`) | Pruebas, `barber_db_test`. | Local y desechable. |
| SQLite de Pulse | Telemetría de Laravel Pulse. | No es fuente de verdad del negocio. |
| Redis | Caché, sesiones y colas según el entorno. | No sustituye un respaldo. |

`DataEnvironmentGuard` distingue los contextos `shared`, `development` y `testing` y rechaza mezclas conocidas de host, entorno y nombre. `Tests\TestCase` revisa además ambas conexiones antes de cada prueba. Las transacciones de pagos e inventario exigen un **replica set**. Los borrados lógicos no liberan necesariamente los índices únicos.

### 7.1 Modelos

`Activity`, `AnalyticsInsight`, `Appointment`, `Barber`, `BarberReview`, `BarberSchedule`, `BarbershopSetting`, `Campaign`, `CashClose`, `ChatMessage`, `Client`, `ClientMembership`, `ClientPackage`, `Comment`, `CommentReport`, `DatabaseNotification`, `GiftCard`, `InventoryMovement`, `LoyaltyTransaction`, `MembershipInvoice`, `MembershipPlan`, `MobileApiToken`, `NoShowFee`, `Order`, `Payment`, `Permission`, `Product`, `PushSubscription`, `RaffleResult`, `Reaction`, `Referral`, `Role`, `SavedWork`, `Service`, `ServiceCombo`, `ServicePackage`, `User`, `UserBlock`, `Waitlist`, `Work` y `WorkImage`.

## 8. Tareas programadas y trabajos

`routes/console.php` es la lista vigente. Las 15 tareas se agrupan con `onOneServer()` para que, con varias réplicas y `SCHEDULE_CACHE_STORE` apuntando a un almacén compartido, cada ejecución ocurra una sola vez.

| Frecuencia | Comando | Función |
| --- | --- | --- |
| Cada minuto | `appointments:notify-service-ending` | Avisa al barbero que el servicio termina en 5 minutos. |
| Cada 5 min | `campaigns:dispatch-due`, `appointments:notify-service-overrun` | Envía campañas vencidas; avisa si un servicio se alarga. |
| Cada 10 min | `appointments:send-reminders` | Recordatorios de 24 h y 2 h. |
| Cada hora | `appointments:mark-no-show` | Marca como no asistidas las citas vencidas. |
| Diaria | `tokens:clean-expired`, `inventory:low-stock-alert`, `reports:daily-summary`, `loyalty:apply-inactivity`, `orders:cancel-expired`, `clients:send-birthday-greetings`, `waitlist:expire-stale`, `packages:expire-stale` | Mantenimiento, avisos y caducidades. |
| Mensual (día 1) | `loyalty:draw-raffle`, `barbers:monthly-performance` | Sorteo de lealtad y reporte de desempeño. |

Los **jobs** (por ejemplo `RunOcrOnComprobante`) necesitan un worker; las tareas necesitan el scheduler. Evita duplicar el scheduler al escalar réplicas.

## 9. Integraciones externas

| Servicio | Uso | Configuración |
| --- | --- | --- |
| Stripe | Cobros con tarjeta, suscripciones y reembolsos. | `STRIPE_*` (claves y secreto del webhook) |
| Google | Inicio de sesión. | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` |
| Correo | Notificaciones y comprobantes (Gmail SMTP en staging; Mailpit en local). | `MAIL_*` |
| Firebase (FCM) | Push a Android. | `FIREBASE_PROJECT_ID` y credenciales de cuenta de servicio |
| Web Push | Push en el navegador. | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` |
| Twilio | SMS y WhatsApp opcionales. | `TWILIO_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM`, `TWILIO_WHATSAPP_FROM` |
| Ollama / Gemini | Asistente virtual. | `CHATBOT_AI_PROVIDER`, `OLLAMA_*` |
| Sentry | Errores. | `SENTRY_LARAVEL_DSN` o `SENTRY_DSN`, opcional |
| S3 | Imágenes públicas y comprobantes. | `UPLOADS_BUCKET`, `RECEIPTS_BUCKET` |

## 10. Pruebas

Las pruebas viven en `tests/` clasificadas por tipo, con una suite de PHPUnit por carpeta.

| Tipo | Carpeta | Qué verifica |
| --- | --- | --- |
| UT / UIT | `tests/UT` | Unidades aisladas y unidades que colaboran con la base de pruebas. |
| SIT | `tests/SIT` | Integración de sistema: contrato, roles, seguridad transversal, Stripe, flujo de citas. |
| UAT | `tests/UAT` | Criterios de aceptación por funcionalidad y rol. |
| OAT | `tests/OAT` | Operación: entornos, colas, scheduler, mantenimiento, límites, respaldos, integridad. |

```powershell
.\test.ps1 --compact              # suite completa
.\test.ps1 --testsuite=UT         # un tipo (UT, SIT, UAT u OAT)
.\test.ps1 --filter=NombreDelTest # una prueba concreta
```

> **Aviso** En local las pruebas se ejecutan **solo con `test.ps1`**, que fuerza `.env.testing` y la base `barber_db_test`. No ejecutes `php artisan test` directamente: una configuración cacheada con valores reales ya borró datos una vez (2026-08-28).

El último resultado documentado: **953 pruebas y 3,696 aserciones** en verde. El frontend tiene Vitest (UT), Playwright (SIT, UAT, OAT) y Cypress. Detalle en `docs/referencia/PRUEBAS.md`.

## 11. Integración continua

| Workflow | Comprobaciones |
| --- | --- |
| CI | Pint, Larastan, PHPUnit con cobertura, auditorías de Composer y npm, ESLint y Vite, Gitleaks, Trivy y smoke HTTP con verificación del contrato. |
| Docker Image CI | Construcción de las imágenes de desarrollo y de staging. |
| Documentación | `scripts/check_docs.py --all` valida los enlaces locales. |

Los cambios entran a `main` por rama y PR con los checks verdes. Un PR verde no despliega: publicar es una decisión aparte (ver el manual de instalación y despliegue).

## 12. Observabilidad y errores

- **Laravel Pulse** muestra peticiones lentas, consultas, colas y excepciones (accesible solo al ingeniero).
- **LogSlowRequests** registra peticiones lentas; `config/observability.php` define los umbrales.
- **Sentry** recoge errores si el entorno lo configura.
- **Registro:** no se escriben cabeceras de autorización, URIs con credenciales ni datos de pago.

## 13. Convenciones de desarrollo

- Una rama por cambio desde `origin/main`; PR con checks verdes; las ramas de trabajo se conservan.
- Commits y PR en español con título `tipo(alcance): descripción`.
- Cambios aditivos en la API: es un contrato real para la web y Android.
- Sin secretos en el repositorio: plantillas `.env.example` y gestor de secretos del entorno.
- No ejecutar migraciones, seeders ni limpiezas de colecciones sin confirmar a qué base apunta el `.env`.
