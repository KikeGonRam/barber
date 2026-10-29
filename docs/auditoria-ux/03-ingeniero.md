# Auditoría UI/UX · 03 Ingeniero

Tercer informe de cinco. Mismo método y entorno que el [del administrador](01-administrador.md): staging `0f17e84`, demostración aislada, escritorio a 1440 × 900 px, tema *Sastrería Nocturna*. Los problemas generales (texto pequeño, formatos, esqueletos, tabla) están en ese informe y aquí solo se citan.

**Alcance:** Panel de ingeniería, Estado del servidor (mapa del sistema y tareas programadas) y Auditoría / Logs. Reportes se ve con el administrador.

## 1. Veredicto

Este es el rol donde la plataforma tiene **más potencial y más distancia entre lo que es y lo que debería ser**. La visión que se busca es un **centro de análisis y observabilidad** como los de las empresas de producto (Datadog, Grafana, Vercel Analytics, Stripe Dashboard): tendencias, comparativas, alertas y capacidad de investigar. Hoy hay buenas piezas, pero el rol recibe **fotos fijas** del sistema, no **series de tiempo**:

- El **panel** mezcla un resumen de infraestructura de una línea con tarjetas de **negocio** (citas, ingresos, campañas, sorteos, muro): para un ingeniero es información de segunda mano.
- **Estado del servidor** ofrece un mapa 3D vistoso, pero con poca densidad de datos: todas las tareas aparecen "Sin datos" y no hay ninguna gráfica de latencia, errores, cola ni disponibilidad en el tiempo.
- **Logs** es una bitácora de auditoría de negocio ("Creado · created · Sistema"), no un registro técnico con niveles, búsqueda de errores ni identificadores de petición.

La identidad visual aguanta bien este tipo de tablero (oscuro, denso, números grandes). Lo que falta es **contenido analítico**.

### Puntuación (1 = débil, 5 = referente)

| Dimensión | Nota | Por qué |
| --- | --- | --- |
| Utilidad para el rol | 2 | Instantáneas sin historia; sin errores, latencia ni tendencias. |
| Visualización de datos | 2.5 | Líneas y barras correctas; pocas, de negocio y con datos escasos. |
| Densidad de información | 3 | Buena sensación de panel; muchas tarjetas con ceros. |
| Claridad de estado | 3.5 | El semáforo (activo, lento, caído, sin uso) y el resumen "Entorno estable" son buenos. |
| Investigación (drill-down) | 1.5 | No se puede pasar de un indicador a su causa. |
| Percepción premium | 3.5 | El mapa 3D impresiona; el resto se queda corto frente a la promesa. |

## 2. Qué ya funciona (conservar)

- **Resumen de monitor** ("Entorno estable") con acceso directo a Módulos, Reportes y Logs.
- **Semáforo de cuatro estados** (activo · lento o con fallos · caído · sin uso), con color y texto.
- **Doble lectura del sistema:** mapa y lista con los mismos nodos; la lista es la versión accesible.
- **Aviso de límites del rol** ("solo lectura", "el detalle permanece fuera de este rol"): respeta el mínimo privilegio y lo explica.
- **Actualización automática** cada 30 s con hora de la última lectura.
- Las gráficas de línea y barras del panel están bien construidas.

## 3. Hallazgos

| ID | Prioridad | Hallazgo | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- |
| I-01 | **Alta** | **No hay series de tiempo técnicas.** MongoDB muestra "6 ms", Redis "—" y la cola "0": son lecturas puntuales. No existe una gráfica de latencia, errores, peticiones, cola o uso de recursos. | Construir el **Centro de observabilidad** (sección 4) con series de 1 h, 24 h y 7 días. | L |
| I-02 | **Alta** | **El panel prioriza el negocio sobre el sistema.** Ocho tarjetas de negocio (Citas, Ingresos, Servicios, Barberos, Clientes, Inventario, Pagos, Pedidos, Campañas, Sorteos, Muro, Bladebot) y solo una línea de infraestructura. | Invertir la jerarquía: arriba la **salud del sistema**; el negocio queda en una sección "Actividad del negocio" más abajo y colapsable. | M |
| I-03 | **Alta** | **"Sin datos" en todas las tareas programadas.** Última corrida y duración aparecen como "—" y el estado como "Sin datos"; el mapa pinta las tareas en gris. | Mostrar última ejecución, resultado y duración, y una **línea de tiempo** de las últimas corridas. (Depende de compartir el estado entre tareas en staging: se aplicó en la revisión 12 del servicio y falta confirmar que ya aparece.) | M |
| I-04 | Media | **Tarjetas de ceros.** Pedidos, Campañas, Sorteos, Muro y Bladebot muestran filas de "0 · 0 · 0" con etiquetas diminutas; ocupan mucho espacio para decir poco. | Agrupar en una fila de "señales de negocio" con sparkline de 7 días; mostrar el detalle solo si hay datos. | M |
| I-05 | Media | **El mapa 3D compite con la información.** Las etiquetas se pisan y el mapa ocupa el espacio de una gráfica real; la lista de al lado dice más que el mapa. | Dejar el mapa como vista opcional ("Topología") y poner primero una **tabla de servicios con estado, latencia y tendencia**. | M |
| I-06 | Media | **Logs no es un registro técnico.** Son eventos de auditoría de negocio ("Creado", descripción "created", usuario "Sistema"), todos con la misma hora; no hay nivel (info, advertencia, error), ni identificador de petición, ni ruta ni duración. | Separar **Auditoría** (qué hizo quién) de **Registros de aplicación** (niveles, filtros por error, ruta, duración, *request id*) y permitir "seguir en vivo". | L |
| I-07 | Media | **Descripciones en inglés y repetidas** ("created") en la columna Descripción. | Describir el recurso afectado ("Cita #A21 creada por Ana"). | S |
| I-08 | Media | **Sin alertas ni umbrales.** "Entorno estable" es una frase; no se ve qué umbral se vigila ni qué dispararía un cambio. | Panel de **alertas activas** y de umbrales configurables (solo lectura para ingeniero), con histórico de incidentes. | L |
| I-09 | Media | **Sin versión ni despliegue visible.** El entorno aparece como "local", Laravel 13.30.1, PHP 8.4.27, pero no la versión de la app ni cuándo se desplegó. | Tarjeta **Versión**: commit, fecha del despliegue y estado de la última revisión. | S |
| I-10 | Baja | **Instantáneas sin comparativa.** "Citas 1 hoy ▲650 %" y "Ingresos $0 hoy" no permiten comparar contra un periodo anterior de forma visual. | Superponer el periodo anterior (línea punteada) en las gráficas. | S |
| I-11 | Baja | **Campos de fecha nativos** y filtros sin estilo en Logs (ver A-07). | Selector de rango propio. | M |

## 4. Propuesta: Centro de observabilidad

El panel del ingeniero debería leerse de arriba abajo así:

| Bloque | Pregunta | Visual |
| --- | --- | --- |
| **Salud** | ¿Todo está bien ahora? | Franja de estado global + SLO del mes (disponibilidad %, presupuesto de error). |
| **Tráfico** | ¿Cuánto se usa? | Peticiones por minuto (línea) con picos y comparación con ayer. |
| **Latencia** | ¿Qué tan rápido responde? | p50 / p95 / p99 por endpoint (líneas) y ranking de rutas más lentas. |
| **Errores** | ¿Qué falla? | Tasa de errores 4xx/5xx (área apilada), lista de errores nuevos con conteo y primera vez. |
| **Base de datos** | ¿Mongo está sano? | Latencia, conexiones y operaciones lentas en el tiempo. |
| **Colas y tareas** | ¿Se ejecuta lo programado? | Profundidad de cola (línea), trabajos fallidos, y **línea de tiempo** de cada tarea programada (verde/rojo por corrida). |
| **Despliegues** | ¿Qué cambió y cuándo? | Marcadores de despliegue sobre todas las gráficas. |
| **Capacidad** | ¿Estamos cerca del límite? | CPU y memoria por tarea de ECS, con la reserva. |
| **Integraciones** | ¿Funcionan los externos? | Estado de Stripe, correo, notificaciones y el chatbot (latencia y costo). |
| **Actividad del negocio** | ¿Qué hacen los usuarios? | El contenido actual del panel, colapsado. |

Reglas de diseño para este tablero:

- **Un selector de tiempo global** (1 h · 24 h · 7 d · 30 d) que gobierna todas las gráficas.
- **Cruce de cursor sincronizado** entre gráficas, para ver el mismo instante en todas.
- **Drill-down:** clic en un pico abre la lista de peticiones o errores de ese instante.
- **Un solo color de énfasis** (dorado) y los colores de estado solo para estado (verde, ámbar, rojo).
- Estado de **datos insuficientes** explícito, no una gráfica en cero.
- Pausar y **actualizar en vivo** (cada 30 s), con indicador.

**Qué hace falta del lado técnico:** hoy la API entrega instantáneas. Para series hay que **registrar métricas** (por ejemplo con Laravel Pulse, que ya está configurado en el entorno de pruebas, o con las métricas de CloudWatch de ECS y del balanceador) y exponer un endpoint de solo lectura por rol. Esta parte es una decisión de arquitectura y conviene validarla antes de diseñar pantallas.

## 5. Orden de trabajo sugerido

| Fase | Contenido | Efecto |
| --- | --- | --- |
| 1 · Rápida | I-04, I-07, I-09, I-10 | Menos ruido y más contexto con lo que ya existe. |
| 2 · Reordenar | I-02, I-03, I-05 | El panel pasa a ser de sistema, con tabla de servicios y tareas con historial. |
| 3 · Datos | Registrar métricas y construir los bloques de la sección 4 (I-01, I-08) | El rol obtiene el centro de análisis que se busca. |
| 4 · Investigar | I-06 (registros técnicos con niveles y *request id*) | Del indicador a la causa en un par de clics. |

Siguiente informe: **04 Barbero**.
