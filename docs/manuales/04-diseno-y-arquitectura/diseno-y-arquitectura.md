# Diseño y Arquitectura

Arquitectura lógica y física, modelo de datos, flujos, seguridad e interfaz de UrbanBlade.

| Dato | Valor |
| --- | --- |
| Documento | Diseño y Arquitectura de UrbanBlade |
| Versión | 2.0 · 28 de octubre de 2026 |
| Audiencia | Arquitectos, desarrolladores y revisores |
| Fuente | Código de los repositorios y lectura de solo lectura del entorno de pruebas en AWS el 28 de octubre de 2026 |
| Clasificación | Uso interno. Sin identificadores de cuenta, credenciales ni datos personales |

## 1. Principios de diseño

| Principio | Qué significa en UrbanBlade |
| --- | --- |
| API como única puerta a los datos | Web y Android consumen HTTP; solo Laravel accede a MongoDB operativo. |
| El servidor es la fuente de verdad | Precios, descuentos, puntos, saldos y stock se calculan y vuelven a leer en el servidor. |
| Monolito modular | Un solo despliegue con dominios separados en servicios, sin la complejidad de microservicios. |
| Separar cargas, no entidades | Datos operativos, derivados analíticos, telemetría y caché tienen almacenes distintos. |
| Mínimo privilegio | Cada consumidor usa credenciales con el alcance justo. |
| Cambios aditivos en el contrato | La API es un contrato real para web y Android. |
| Operación reproducible | Docker para desarrollo y pruebas; imágenes propias para staging. |

## 2. Vista de contexto

```text
                    ┌────────────────────────────────────────────┐
   Cliente ─┐       │                  UrbanBlade                │
   Barbero ─┤       │                                            │
 Recepción ─┼──►  Web (Nuxt) ─┐                                  │
Administrador┤                ├─► API Laravel ─► MongoDB         │
  Ingeniero ─┘  Android ──────┘        │                         │
                                       ├─► Stripe                │
                                       ├─► Google (acceso)       │
                                       ├─► Correo · Push · SMS   │
                                       └─► Asistente (Ollama)    │
                    └────────────────────────────────────────────┘
        Spark (análisis académico) ── lee datos ── escribe derivados aparte
```

## 3. Arquitectura lógica

### 3.1 Capas del backend

| Capa | Responsabilidad | Ubicación |
| --- | --- | --- |
| Rutas y middleware | Autenticación, mantenimiento, límites, filtros de entrada. | `routes/api.php`, `app/Http/Middleware` |
| Controladores y requests | Orquestación HTTP, validación y alcance del usuario. | `app/Http/Controllers/Api`, `app/Http/Requests` |
| Servicios | Reglas y operaciones de negocio. | `app/Services` |
| Modelos | Persistencia, relaciones y conversiones. | `app/Models` |
| Resources | Forma pública de las respuestas. | `app/Http/Resources` |
| Jobs y notificaciones | Trabajo diferido y entrega multicanal. | `app/Jobs`, `app/Notifications` |

### 3.2 Capas del frontend

| Capa | Responsabilidad |
| --- | --- |
| `pages` y `layouts` | Rutas públicas y paneles por rol; estructura y navegación compartidas. |
| `components` | Formularios, tableros, gráficas y el kit de interfaz reutilizable. |
| `composables` | Sesión, llamadas a la API, carrito, tema, consentimiento y notificaciones. |
| `middleware` | Redirecciones de navegación según sesión y rol (no sustituyen la autorización del servidor). |
| `server` (Nitro) | Renderizado en servidor, salud, mapa del sitio y filtros HTTP. No es un proxy de la API de negocio. |
| `public` | Manifiesto, service worker y página sin conexión. |

El estado compartido se resuelve con composables y las primitivas de Nuxt, no con un almacén global adicional.

### 3.3 Aplicación Android

Arquitectura **MVVM** con Jetpack Compose para la interfaz, ViewModels para el estado y Retrofit para consumir la API, con navegación por rol. Usa la misma cuenta y los mismos endpoints que la web.

## 4. Arquitectura de datos

### 4.1 Almacenes

| Almacén | Contenido | Observación |
| --- | --- | --- |
| MongoDB operativo | Usuarios, citas, pagos, inventario, pedidos, lealtad, social. | Replica set obligatorio para las transacciones de pagos e inventario. |
| MongoDB analítico | Derivados de analítica que Laravel consume. | Conexión explícita separada. |
| MongoDB de desarrollo y de pruebas | `urbanblade_dev` y `barber_db_test`. | Locales y aislados de los datos compartidos. |
| SQLite de Pulse | Telemetría de Laravel Pulse. | No es fuente de verdad. |
| Redis | Caché, sesiones y colas según el entorno. | No existe en staging. |
| Archivos | Imágenes públicas y comprobantes. | S3 en staging; los comprobantes usan URL temporal. |

### 4.2 Modelo de datos (entidades principales)

```text
User ──1:1── Client   ──1:N── Appointment ──N:1── Barber ──1:N── BarberSchedule
 │             │                  │  │  └─N:1── Service
 │             ├─1:N── Order      │  ├──1:N── Payment
 │             │                  │  └──1:N── NoShowFee
 │             ├─1:N── ClientMembership ──N:1── MembershipPlan
 │             ├─1:N── ClientPackage ──N:1── ServicePackage
 │             └─1:N── LoyaltyTransaction
 └─1:N── MobileApiToken            Barber ──1:N── Work ──1:N── WorkImage
Product ──1:N── InventoryMovement   Work ──1:N── Comment / Reaction / SavedWork
```

Al ser MongoDB, las relaciones son referencias por identificador resueltas en la aplicación. Los borrados lógicos no liberan necesariamente los índices únicos (por ejemplo el del correo de un usuario).

### 4.3 Aislamiento de entornos

`DataEnvironmentGuard` valida al arrancar los contextos `shared`, `development` y `testing` y rechaza combinaciones de host, entorno y nombre de base que mezclarían datos. `Tests\TestCase` repite la comprobación en cada prueba. Es una defensa adicional: no reemplaza los permisos mínimos de la base de datos.

## 5. Arquitectura física (entorno de pruebas en AWS)

Estado leído en solo lectura el 28 de octubre de 2026.

```text
  Navegador ──HTTPS──► CloudFront ──HTTP──► ALB ──► ECS Fargate (cluster urbanblade-staging)
                         │  urbanblade.com.mx          :80   ──► frontend (Nuxt)        2 tareas
                         │  api.urbanblade.com.mx      :8080 ──► barber (Caddy+PHP-FPM) 2 tareas
                         └  spark.urbanblade.com.mx    :8501 ──► spark (Streamlit)      1 tarea
                                                       interno ─► ollama (asistente)     1 tarea
  barber ──► MongoDB Atlas   ·   S3 (imágenes y comprobantes)   ·   Secrets Manager (claves)
  Imágenes: ECR (barber, frontend-urban, spark, ollama)   ·   Logs: CloudWatch
  DNS: Route 53   ·   Certificado HTTPS: ACM (en CloudFront)
```

| Elemento | Detalle |
| --- | --- |
| Servicios | `uba-stg-barber`, `uba-stg-frontend`, `uba-stg-spark`, `uba-stg-ollama` en el clúster `urbanblade-staging`. |
| Capacidad | `barber` y `frontend` en FARGATE; `spark` y `ollama` en FARGATE_SPOT. |
| Entrada | Tres distribuciones de CloudFront (frontend, API y Spark) hacia un ALB con tres listeners (80, 8080, 8501). |
| Salud | Frontend `/api/health`, backend `/up`, Spark `/_stcore/health`. |
| Datos | MongoDB Atlas externo; el backend no usa Redis en staging (caché en archivo, colas síncronas). |
| Secretos | Secrets Manager; nunca en la imagen ni en el repositorio. |

> **Aviso** Staging **no es producción**. Un nombre histórico con la palabra staging no certifica qué datos atiende un recurso; antes de operar hay que identificar cuenta, región, clúster, servicio e imagen.

## 6. Flujos clave

### 6.1 Reserva de una cita

```text
Cliente ─► Web: elige servicio, barbero y día
Web ─► API GET /availability/slots ─► servicio calcula huecos libres (horario + citas)
Cliente ─► Web: elige hora
Web ─► API POST /appointments ─► valida disponibilidad y política ─► crea cita "pendiente"
API ─► notifica al barbero y a recepción (correo / push)
Barbero o recepción ─► confirma ─► estado "confirmada" ─► recordatorios 24 h y 2 h
```

### 6.2 Pago con tarjeta

```text
Cliente ─► Web ─► API crea el pago y un PaymentIntent en Stripe (el servidor fija el importe)
Web ─► Stripe.js confirma con la clave publicable (el secreto nunca sale del backend)
Stripe ─► POST /api/stripe/webhook (firmado) ─► API concilia y marca el pago
API ─► recibo PDF y aviso al cliente
```

### 6.3 Atención del servicio

```text
confirmada ─► Iniciar (ServiceStartGuard: fecha, margen, pago resuelto) ─► en proceso
en proceso ─► aviso a 5 min del final: terminar ya / agregar tiempo ─► Completar
completada ─► puntos y nivel (LoyaltyService) · comisión del barbero · solicitud de reseña
```

## 7. Diseño de seguridad

| Frontera | Control |
| --- | --- |
| Identidad | Token Bearer propio con hash SHA-256 y vigencia deslizante; Google con verificación en el servidor. |
| Autorización | Rol y permiso en la ruta y **pertenencia del recurso** en el controlador. |
| Entradas | Validación por `FormRequest`; rechazo de operadores Mongo del cliente. |
| Red | Detrás de CloudFront y un ALB que solo acepta tráfico de CloudFront; límite por IP con `TRUSTED_PROXY_HOPS`. |
| Navegador | CSP con nonce, HSTS en producción, cookie de sesión `SameSite=Lax` y `Secure`. |
| Archivos | Validación y optimización de imágenes; comprobantes con URL temporal de 15 minutos. |
| Secretos | Gestor de secretos; plantillas `.env.example` sin valores. |
| Operación | Bitácora de actividad, Pulse y registro de peticiones lentas. |

## 8. Diseño de la interfaz

### 8.1 Identidad

- Cuatro **temas**: `noir`, `acero`, `salon` y `libreta`. Comparten navegación y permisos; solo cambian color y estilo.
- Tres **mascotas** acompañan los estados de la interfaz: **Nava** (orientación y estados vacíos), **Bladebot** (asistencia, espera y éxito) y **Bruno** (conocimiento, errores y reportes). Nunca tapan acciones ni sustituyen texto accesible.
- Paleta de los entregables del equipo: negro y oro.

### 8.2 Navegación por rol

El menú lateral se arma según el rol: Principal (Dashboard, Muro, Notificaciones), Operación, Gestión, Análisis, Sistema, Mi Espacio (barbero) y tres grupos para el cliente (Reservar, Tienda y Beneficios). En móvil se convierte en un cajón y una barra inferior con las acciones principales.

### 8.3 Criterios de interfaz

- Foco visible, controles con nombre accesible y soporte de movimiento reducido.
- Objetivo interno de 44 px para las áreas táctiles.
- Estados vacíos, de carga y de error que expliquen la acción disponible.
- Reutilizar el kit existente antes de crear otro selector, modal, insignia o tabla.
- No sustituir datos ausentes por cifras inventadas.

### 8.4 PWA

El service worker maneja notificaciones push y cachés limitadas (navegación con red primero, recursos con caché primero). Omite métodos distintos de GET, otros orígenes y `/api/`. **No admite reservas ni cobros sin conexión.**

## 9. Decisiones de arquitectura

| ID | Decisión | Estado |
| --- | --- | --- |
| D01 | Backend JSON con clientes web y Android separados. | Implementada |
| D02 | Tokens Bearer propios guardados como hash (no Sanctum). | Implementada |
| D03 | Monolito modular con servicios de dominio. | Implementada |
| D04 | Separar cargas de datos, no una base por entidad. | Implementada |
| D05 | Replica set en desarrollo y pruebas por las transacciones. | Implementada |
| D06 | Precio, descuentos, saldo y stock calculados en el servidor. | Implementada |
| D07 | Estado de la cita separado del estado del pago. | Implementada |
| D08 | Recursos públicos separados de los comprobantes. | Implementada |
| D09 | OpenAPI y tipos generados para los clientes. | Implementada (cobertura parcial) |
| D10 | Cambios por rama y PR; publicar es una decisión aparte. | Vigente |

El registro completo está en `docs/referencia/DECISIONES.md` y la decisión de datos en `docs/referencia/ADR-001-ARQUITECTURA-DE-DATOS.md`.

## 10. Calidad y escalabilidad

- **Escalar la API** a varias réplicas exige un almacén compartido para el límite de intentos (`CACHE_LIMITER_STORE`) y para el mutex del planificador (`SCHEDULE_CACHE_STORE`); de lo contrario cada réplica cuenta por su cuenta y repite las tareas.
- **Colas:** los trabajos diferidos (por ejemplo el OCR de comprobantes) necesitan un worker; en staging las colas son síncronas.
- **Capacidad:** el rendimiento y la capacidad no se han medido con carga representativa; los datos de prueba son pequeños.
- **Observabilidad:** Pulse, Sentry (opcional) y logs en CloudWatch.

## 11. Limitaciones conocidas

- No hay producción: nada de lo descrito certifica comportamiento con datos o tráfico reales.
- El filtro de abuso del frontend guarda estado en memoria; no es un limitador distribuido.
- La caché del service worker no excluye específicamente las rutas con sesión; el objetivo es no almacenar tokens ni datos privados, pero conviene revisarlo antes de asumirlo.
- El backend de staging no tiene Redis.
