# UrbanBlade — Documentación Técnica

Sistema de gestión integral para barbería: reservas, cobros, inventario, tienda de
productos, fidelización, muro social y analítica de datos.

---

## 1. Arquitectura general

UrbanBlade está compuesto por **dos proyectos independientes**:

| Proyecto | Repositorio | Función |
|---|---|---|
| **`barber/`** | `KikeGonRam/barber` (rama `main`) | Aplicación web Laravel — la app en producción |
| **`spark/`** | `KikeGonRam/spark` (rama `urbanblade-analytics`) | Módulo de analítica Big Data (PySpark) — proyecto académico independiente que lee la misma base de datos MongoDB en modo solo-lectura |

Ambos proyectos comparten la misma base de datos MongoDB Atlas, pero corren en
entornos separados: `barber/` en Docker, `spark/` en WSL Ubuntu.

---

## 2. Aplicación web (`barber/`)

### 2.1 Stack

- **Backend**: PHP 8.2+, Laravel 12
- **Base de datos**: MongoDB (paquete `mongodb/laravel-mongodb`) — sin MySQL
- **Frontend**: TailwindCSS 3/4 + Alpine.js 3 + Vite (sin framework SPA; server-rendered con Blade)
- **Paquetes clave**:
  - `spatie/laravel-permission` — roles y permisos (adaptado a MongoDB, ver §2.3)
  - `barryvdh/laravel-dompdf` — generación de PDF (facturas, recibos, tarjeta de socio)
  - `endroid/qr-code` — códigos QR de la tarjeta de membresía
  - `stripe/stripe-php` — pagos con tarjeta (webhook dedicado)
  - Twilio (integrado a mano vía `Http::withBasicAuth()`, sin SDK) — SMS/WhatsApp
  - `spatie/laravel-activitylog` — bitácora de acciones

### 2.2 Infraestructura Docker

`docker-compose.yml` define 6 servicios sobre una imagen compartida (`barber-app`):

- **`app`** — PHP-FPM (procesa las peticiones)
- **`web`** — Nginx (puerto 8000, expuesto al host)
- **`worker`** — `queue:work` (colas: correos, notificaciones, PDFs)
- **`scheduler`** — `schedule:work` (tareas programadas: no-show automático, campañas, resúmenes diarios)
- **`redis`** — caché y backend de colas
- **`mailpit`** — capturador de correo para desarrollo (puertos 8025 UI / 1025 SMTP)

Todos comparten un volumen `vendor_data` para no reinstalir dependencias PHP por contenedor.

### 2.3 Roles y permisos

Cuatro roles: **administrador, recepcionista, barbero, cliente**.

MongoDB no soporta las tablas pivote (`MorphToMany`) que usa Spatie Permission por
defecto, así que además del paquete se mantiene un campo `role_id` embebido
directamente en el documento de cada usuario. La protección de rutas usa un
middleware propio `role.custom:<roles>` (no el `role:` estándar de Spatie).

Permisos granulares (ademsás del rol): `citas.gestionar`, `pagos.gestionar`,
`inventario.ver/gestionar`, `clientes.gestionar`, `reportes.ver`,
`servicios.gestionar`, `usuarios.gestionar`, `barberos.gestionar`,
`configuracion.gestionar`, `logs.ver`. El administrador tiene todos; la
recepcionista tiene el subconjunto operativo (citas/pagos/clientes/inventario).

### 2.4 Módulos de dominio

**Máquina de estados de citas** (`AppointmentStatusService`):

```
pendiente   → confirmada | cancelada
confirmada  → en_proceso | completada | no_asistio | cancelada
en_proceso  → completada | cancelada
(terminales: completada, cancelada, no_asistio)
```

El cobro solo se habilita desde `confirmada`, `en_proceso` o `completada` — nunca
desde una cita `pendiente` sin aprobar. El barbero aprueba/rechaza sus propias
citas; el cliente solo puede cancelar. Un job programado marca automáticamente
como `no_asistio` las citas confirmadas cuyo horario ya pasó.

**Pagos y fidelización**: cada cita completada genera un `Payment` (monto,
propina, método) y una `LoyaltyTransaction` (puntos ganados). El nivel del
cliente (`nuevo → regular → vip → leyenda`) se recalcula por umbrales de citas
completadas. Existe integración con Stripe para pago con tarjeta, con webhook
dedicado excluido de CSRF.

**Tienda de productos**: catálogo (`Product`, con `tipo` = `uso_interno` o
`venta`), carrito (`CartService`) y pedidos (`Order`, `OrderService`). Un pedido
puede ser de dos tipos: `cita` (add-on comprado dentro de la reserva) o `tienda`
(compra suelta). La entrega se gestiona desde una bandeja de pedidos para
recepción/administración, que notifica al cliente al marcar como entregado.

**Muro social**: los barberos publican trabajos (`Work` + `WorkImage`,
portafolio), que los clientes pueden comentar (`Comment`) y reaccionar
(`Reaction`). Sirve como escaparate del trabajo de cada barbero.

**Notificaciones**: multicanal — correo (con plantilla de marca compartida),
`database` (centro de notificaciones in-app) y un canal Twilio propio (SMS/
WhatsApp; se simula/registra en log si no hay credenciales configuradas).
Eventos cubiertos: confirmación/cancelación de cita (con adjunto `.ics` y enlace
a Google Calendar), recibo de pago con factura PDF adjunta, solicitud de reseña
tras completar el servicio, alerta de stock bajo al administrador, resumen
diario del negocio, y entrega de pedido.

**Campañas de marketing**: el módulo `Campaign` permite enviar promociones ahora
o programarlas, con tracking de apertura (`/t/o/{campaign}/{user}`) y clic
(`/t/c/{campaign}/{user}`) vía píxel/redirección, y métricas agregadas en la UI
de campañas. Los clientes pueden optar por no recibir promociones
(`PromotionNotification` respeta preferencias por usuario).

**Tarjeta de socio**: tarjeta de membresía dinámica (nivel de lealtad), con
volteo para mostrar un QR de identificación, animación de confeti al subir de
nivel, y descarga en PDF.

### 2.5 Base de datos (MongoDB)

Colecciones principales, cada una poblada por un seeder dedicado en
`database/seeders/` (17 seeders, orquestados por `DatabaseSeeder.php` en orden
estricto de dependencias):

`roles`/`permissions` → `barbershop_settings` → `services` → `products` →
usuarios (`administrador`, `recepcionista`, `barbers`, `clients`) →
`barber_schedules` → `appointments` → `payments` → `loyalty_transactions` →
`orders` → `works` → `work_images` → `comments` → `reactions`.

> ⚠️ **`barber_db` se limpió por completo el 2026-09-04** (tenía ~214,623
> citas y ~323,095 transacciones de lealtad sintéticas acumuladas de siembras
> masivas repetidas, además de ~4,767 usuarios de sobra). El estado real
> actual son solo las 4 cuentas de equipo documentadas en
> [ACCESOS.md](ACCESOS.md) (1 admin, 1 recepcionista, 1 barbero, 1 cliente) y
> ningún dato operativo (citas, pagos, productos, etc.) — se va cargando con
> información real conforme el negocio la genera.
>
> Si se corre el `DatabaseSeeder` completo (`migrate --seed`, **no
> recomendado**, ver [README.md](../README.md)), sí generaría un dataset
> sintético de referencia grande: 1 administrador + 1 recepcionista + 50
> barberos + 1500 clientes, ~112,000 citas históricas (2024 → semana actual,
> con la regla de que ninguna cita futura puede estar en un estado distinto
> de `pendiente`), ~19,000 pedidos de tienda, ~200 publicaciones sociales con
> comentarios y reacciones — es exactamente ese volumen el que se limpió.

### 2.6 Rutas

- **`routes/web.php`**: rutas públicas (landing, equipo, servicios, tracking de
  campañas, chatbot) + grupos protegidos por `auth`, `role.custom:administrador`,
  `role.custom:administrador,recepcionista`, `role.custom:cliente` (prefijo
  `/cliente`) y `role.custom:barbero` (prefijo `/barbero`).
- **`routes/api.php`**: webhook de Stripe (sin CSRF), y todo bajo `prefix('v1')`
  — endpoints públicos (auth, catálogo, chatbot), luego un grupo protegido por
  autenticación móvil (`mobile.auth`), con un subgrupo `admin` +
  `role.custom:administrador` para endpoints administrativos (dashboard,
  barberos, clientes, inventario, reportes).

### 2.7 Estado de pruebas

El proyecto no cuenta actualmente con un directorio `tests/` ni suite de pruebas
automatizadas activa. Cualquier verificación de cambios se hace manualmente
(navegador + revisión de logs de Docker).

### 2.8 Antes de desplegar a producción

El `.env` local trae `APP_ENV=local` y `APP_DEBUG=true` a propósito — así
Laravel muestra la traza completa del error (archivo, línea, versión de
PHP/Laravel) cuando algo falla, útil en desarrollo. **En producción esto es
una fuga de información**: cualquier visitante que provoque un error (incluso
sin haber iniciado sesión) puede ver rutas del servidor y detalles internos.

Antes de publicar el sistema, en el `.env` de producción:

```
APP_ENV=production
APP_DEBUG=false
```

y confirmar que aparece una página de error genérica (no la traza de Laravel)
al forzar un error, por ejemplo visitando una ruta que no existe.

---

## 3. Módulo de analítica (`spark/`)

### 3.1 Propósito y contexto académico

`spark/` es un proyecto independiente de la materia *Extracción del
conocimiento en bases de datos* (UTVT IDGS-93). No es parte de la app en
producción — es un módulo de analítica que **lee** la misma base MongoDB Atlas
en modo solo-lectura y aplica las técnicas de las 5 unidades del programa:
introducción, preparación de datos (ETL/MapReduce), aprendizaje supervisado,
aprendizaje no supervisado y visualización.

### 3.2 Stack y entorno

- **PySpark** 3.5.1, **PyMongo** 4.7.2, **Python** 3.11 (vía Miniconda, entorno
  `spark_env`)
- **Streamlit** + **Plotly** para los dashboards interactivos
- **PyTorch** + **scikit-learn** para el módulo de red neuronal
- Corre en **WSL Ubuntu** (no en Docker); el proyecto vive en
  `C:\Users\...\UrbanBlade\spark` y se monta automáticamente en `/mnt/c/...`

### 3.3 Capa de datos: `config/mongo_spark_conexion_sinnulos.py`

Es el módulo central del que dependen todos los scripts. En vez de usar el
conector nativo Spark-MongoDB, hace un **JOIN en memoria vía PyMongo** entre
`appointments → services → barbers → users → clients → users`, arma un
DataFrame de Spark con las columnas ya resueltas (`servicio`, `barbero`,
`cliente`, `nivel`, `precio`, `ingreso`, `estado`, fecha desagregada en
`anio/mes/dia/dia_semana/hora`, etc.) y expone `get_spark_session()` →
`(spark, df, df_vector)`.

Además de la sesión base, expone helpers especializados que cada script importa
según necesite:

| Helper | Qué devuelve |
|---|---|
| `get_clientes_df` | Perfil RFM (recencia/frecuencia/monto) por cliente |
| `get_pagos_df` | Pagos reconciliados contra citas + quién cobró |
| `get_loyalty_df` | Puntos de lealtad por cliente/mes/nivel |
| `get_horarios_df` / `get_utilizacion_barberos_df` | Horas disponibles vs. ocupadas por barbero |
| `get_productos_df` | Inventario con márgenes y alertas de reorden |
| `get_pedidos_df` | Un pedido por fila (`orders`: tipo, estado, total) |
| `get_top_productos_df` | Ranking de productos vendidos (explode de `items[]`) |
| `get_publicaciones_df` | Engagement del muro social por barbero |

Reglas importantes documentadas en `SKILL.md`:
- Los estados válidos de cita son **6** (`pendiente, confirmada, en_proceso,
  completada, cancelada, no_asistio`), no 4 como en versiones anteriores del
  proyecto.
- `ingreso = precio_cobrado` de la cita — nunca `cantidad × precio` (ese campo
  no existe en este esquema).
- Los campos `Decimal128` de MongoDB (precios de productos, subtotales de
  pedidos) requieren el helper `_num()` antes de operarse como `float`.

### 3.4 Organización por unidad

```
unidades/
├── unidad_1_introduccion/       Comparativa y caso de estudio (documentación)
├── unidad_2_preparacion/        01-13: tipos de datos, data warehouse, limpieza,
│                                 minería de patrones, ETL/MapReduce, calidad de
│                                 pagos, fidelización, utilización de barberos,
│                                 inventario, pedidos de tienda, engagement social
├── unidad_3_supervisado/        Regresión (6 modelos), árbol de decisión,
│                                 bosque aleatorio, red neuronal, predicción de
│                                 cancelación (churn) y de demanda
├── unidad_4_no_supervisado/     KMeans, PCA, segmentación de clientes,
│                                 motor de recomendación
└── unidad_5_visualizacion/      main_dashboard.py (dashboard ejecutivo unificado,
                                  15 pestañas) + dashboards individuales por tema
```

### 3.5 Dashboard ejecutivo

`unidades/unidad_5_visualizacion/main_dashboard.py` es el tablero principal:
carga los datos una sola vez por sesión (`@st.cache_resource`), entrena los
modelos de ML bajo demanda, y organiza el contenido en pestañas agrupadas por
unidad (selector en el sidebar). Incluye desde el resumen ejecutivo del negocio
hasta modelos predictivos y segmentación de clientes — incluyendo, desde esta
última actualización, pedidos de tienda y engagement del muro social.

### 3.6 Cómo ejecutarlo

Todo corre en WSL Ubuntu con el entorno conda `spark_env` activado:

```bash
wsl -d Ubuntu
conda activate spark_env
cd /mnt/c/Users/.../UrbanBlade/spark

# Un script individual
spark-submit --master local[*] unidades/unidad_2_preparacion/12_pedidos_tienda.py

# El dashboard ejecutivo
streamlit run unidades/unidad_5_visualizacion/main_dashboard.py
```

Requiere un archivo `.env` en la raíz de `spark/` con las 5 variables de
conexión a MongoDB Atlas (`MONGO_USER`, `MONGO_PASSWORD`, `MONGO_CLUSTER`,
`MONGO_DB`, `MONGO_COLLECTION`) — nunca una sola `MONGO_URI`.

---

## 4. Relación entre los dos proyectos

```
┌─────────────────┐        lee (solo-lectura)        ┌──────────────────┐
│   barber/        │ ───────── MongoDB Atlas ────────▶│   spark/          │
│   (Laravel,       │        (misma base de datos)     │   (PySpark,        │
│    Docker)         │                                   │    WSL Ubuntu)     │
└─────────────────┘                                   └──────────────────┘
     escribe/opera                                      solo analiza y
     el negocio real                                     visualiza
```

`spark/` nunca escribe en la base de datos de producción — es puramente
analítico. Cualquier cambio de esquema en `barber/` (nuevos campos, nuevas
colecciones, cambio de estados) debe reflejarse manualmente en
`mongo_spark_conexion_sinnulos.py` para que el análisis siga siendo correcto;
esto ya ocurrió una vez en este proyecto (la ampliación de 4 a 6 estados de
cita, y la incorporación de `orders`/`works`/`comments`/`reactions` al
análisis, antes vacías).
