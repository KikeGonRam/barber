# Auditoría UI/UX

Evaluación profesional de la interfaz de UrbanBlade, rol por rol, hecha el 29 de octubre de 2026 sobre staging (frontend `0f17e84`) con un entorno de demostración aislado. Mide **componentes y experiencia**; no toca la identidad (negro y oro, mascotas), que es una fortaleza.

| # | Rol | Informe | Nota global* |
| --- | --- | --- | --- |
| 01 | Administrador | [01-administrador.md](01-administrador.md) | 3.0 |
| 02 | Recepcionista | [02-recepcionista.md](02-recepcionista.md) | 3.0 |
| 03 | Ingeniero | [03-ingeniero.md](03-ingeniero.md) | 3.5 (potencial 5) |
| 04 | Barbero | [04-barbero.md](04-barbero.md) | 3.5 |
| 05 | Cliente | [05-cliente.md](05-cliente.md) | 3.5 |

\* Percepción premium global, de 1 a 5, según cada informe.

## Lectura de conjunto

**Fortalezas que se conservan:** identidad coherente, carcasas distintas por tipo de rol (corporativa para datos, amable para barbero y cliente), mascotas con función (vacíos, 404), kit de componentes (`StatCard`, `ChartCard`, `Modal`, `Badge`…) y buen contraste del texto secundario.

**Lo que impide sentirse premium** no es el estilo, sino la **disciplina del sistema**. Los mismos siete problemas aparecen en los cinco roles:

| # | Regla que falta | Se ve en | Esfuerzo |
| --- | --- | --- | --- |
| 1 | **Escala tipográfica con mínimo 12 px** y un `h1` por pantalla (hoy 32 % del texto mide menos de 12 px). | Todos | M |
| 2 | **Formateadores únicos** de moneda, número y fecha (hoy `$2690.00`, `$5400`, `$10,800`; `2026-11-02` y `28 oct 2026`). | Admin, recepción, ingeniero | S |
| 3 | **Un componente de tabla** con barra de herramientas, orden y acciones en menú `⋯`. | Admin, recepción, ingeniero | L |
| 4 | **Patrón de carga** con esqueletos (las gráficas tardan segundos y parecen rotas). | Todos | M |
| 5 | **Patrón de estado vacío** con mascota, frase y una acción (hoy cada pantalla lo resuelve distinto). | Recepción, barbero, cliente | M |
| 6 | **Guía de gráficas** (leyenda visual, un color de énfasis, datos escasos, ejes con unidades). | Admin, ingeniero | M |
| 7 | **Selector de fechas propio** (hoy `dd/mm/aaaa` nativo). | Admin, recepción, ingeniero | M |

## Las cinco apuestas de producto

Son lo que cambia la percepción de "bonita" a "plataforma":

1. **Centro de Análisis del administrador** con gráficas reales (hoy son seis contadores) → [01 §4](01-administrador.md).
2. **Centro de observabilidad del ingeniero** con series de tiempo (latencia, errores, cola, tareas, despliegues) → [03 §4](03-ingeniero.md). Requiere registrar métricas: decisión de arquitectura previa.
3. **Consola de mostrador de recepción** con *Llegó* y *Cobrar* en la propia tarjeta → [02 §4](02-recepcionista.md).
4. **"Mi día" del barbero** como línea de tiempo con la cita en curso → [04 §4](04-barbero.md).
5. **Reserva como momento de marca** (dos columnas, fotos, resumen fijo) y **imágenes por defecto** de servicios y productos → [05 §4](05-cliente.md).

## Plan sugerido

| Fase | Contenido | Duración estimada | Efecto |
| --- | --- | --- | --- |
| 1 · Pulido | Reglas 1 y 2, y los hallazgos *S* de cada informe (botón principal del cliente, `Eliminar` a menú, rótulos, capitalización). | 1–2 días | Se nota en todas las pantallas sin tocar el diseño. |
| 2 · Sistema | Reglas 3 a 7 como componentes compartidos; imágenes por defecto. | 1–2 semanas | Coherencia entre los cinco roles. |
| 3 · Producto | Las cinco apuestas, empezando por la reserva del cliente (conversión) y el Centro de Análisis. | 3–5 semanas | La parte "premium" visible para negocio y dirección. |

## Límites de esta auditoría

- Una sola pasada, con datos de demostración (algunas pantallas se ven más vacías que en producción).
- Tema *Sastrería Nocturna* y escritorio de 1440 px, más la revisión móvil previa; faltan los temas alternativos, 1920 px y la revisión con teclado y lector de pantalla.
- Pantallas sin recorrer: Usuarios, Servicios, Campañas, Sorteos, Membresía, Paquetes, Gift Cards, Referidos, Recibos y Perfil.
- Las notas son juicios de diseño, no medidas objetivas; las mediciones (tamaños de texto, contraste, encabezados) están en los informes.
