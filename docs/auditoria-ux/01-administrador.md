# Auditoría UI/UX · 01 Administrador

Auditoría de interfaz y experiencia del rol **administrador**, hecha el 29 de octubre de 2026 sobre la versión desplegada en staging (frontend `0f17e84`), recorrida en un entorno de demostración aislado, en escritorio a 1440 × 900 px y con el tema *Sastrería Nocturna*. Es la primera de cinco: administrador, recepcionista, ingeniero, barbero y cliente.

**Alcance:** panel, Citas, Clientes, Pagos, Inventario, Reportes, Analítica y Configuración. No se auditó Usuarios, Servicios, Campañas, Sorteos ni Logs (quedan para la segunda pasada), ni los temas claro u otros.

**Qué NO se toca aquí:** la identidad (negro y oro, mascotas, nombres). Este informe evalúa los **componentes**: si funcionan, si son coherentes y si la plataforma se siente premium.

**Cómo se evaluó:** inspección heurística (Nielsen y principios de diseño de producto tipo Stripe, Linear y Vercel), revisión visual de cada pantalla, mediciones en el navegador (tamaños de texto, contraste, objetivos táctiles, encabezados) y lectura del kit de componentes (`app/components/ui`).

---

## 1. Veredicto

La base es **buena y reconocible**: identidad fuerte, barra lateral ordenada, tarjetas de indicadores cuidadas (con cuenta animada que respeta *reducir movimiento*), chips de estado claros y un contraste correcto del texto secundario (6.6 : 1). La pantalla de **Reportes** ya se parece a lo que buscamos.

Lo que impide que se sienta una plataforma premium no es el estilo, sino la **disciplina**: textos demasiado pequeños, números con formatos distintos, tablas con demasiado ruido, estados de carga que parecen roto, y una pantalla de análisis (Analítica) que no tiene gráficas. Casi todo se arregla con un **sistema de reglas** (escala tipográfica, formato numérico, patrón de tabla, patrón de carga) más que con rediseñar pantallas.

### Puntuación (1 = débil, 5 = referente)

| Dimensión | Nota | Por qué |
| --- | --- | --- |
| Identidad y atmósfera | 4.5 | Coherente, reconocible, con carácter. |
| Jerarquía tipográfica | 2.5 | 32 % de los textos mide menos de 12 px; sin `h1`. |
| Consistencia de componentes | 3 | Hay kit, pero cada módulo arma su tabla y su filtro. |
| Densidad y legibilidad | 2.5 | Filas de Citas con hasta seis botones; etiquetas de 9–10 px. |
| Datos y gráficas | 3 | Buenas piezas (dona, barras, línea), mal contadas en datos escasos. |
| Estados (carga, vacío, error) | 2.5 | Tarjetas vacías varios segundos; vacíos sin guía. |
| Accesibilidad | 2.5 | Tamaños, encabezados, tablas y fechas nativas sin estilo. |
| Percepción premium global | 3 | Bonita de lejos; de cerca, los detalles la delatan. |

---

## 2. Qué ya funciona (conservar)

- **Reportes** como patrón de referencia: ocho indicadores en cuadrícula, selector segmentado de periodo (*Hoy · Esta semana · Este mes…*) y dona con leyenda numérica.
- **Barra lateral** con secciones plegables (*Operación, Gestión, Análisis, Sistema*) y un único botón primario dorado.
- **Búsqueda global** (`Ctrl K`) y notificaciones con contador.
- **StatCard** con delta, minigráfica y conteo animado; respeta `prefers-reduced-motion`.
- **Chips de estado** (Pendiente, Confirmado) con color y texto, no solo color.
- Texto secundario con contraste suficiente sobre las tarjetas (6.6 : 1).
- Las acciones de riesgo ya piden confirmación.

---

## 3. Hallazgos

Prioridad: **Alta** = daña la percepción o la tarea; **Media** = resta pulido; **Baja** = detalle. Esfuerzo: **S** (horas), **M** (1–2 días), **L** (más).

### 3.1 Transversales (afectan a todo el rol)

| ID | Prioridad | Hallazgo | Evidencia | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- | --- |
| A-01 | Alta | **Texto demasiado pequeño.** El 32 % de los textos del panel (63 de 196) mide menos de 12 px; hay tamaños de 8, 9, 10 y 11 px, sobre todo en etiquetas en mayúsculas. | Medición en el panel; las etiquetas de las acciones rápidas, las columnas y los pies de tarjeta. | Fijar una escala con **mínimo 12 px** (etiquetas 12, cuerpo 14, subtítulos 16, título de página 24–30). Las mayúsculas con espaciado solo en etiquetas cortas. | M |
| A-02 | Alta | **Estados de carga que parecen rotos.** Las gráficas del panel tardan 5 s o más en pintarse y mientras tanto la tarjeta está vacía, sin esqueleto. | Panel: *Citas completadas del mes*, *Ingresos y citas por barbero* y *Base de clientes* aparecen en blanco. | Esqueleto con la altura final de cada tarjeta y entrada con *fade*. Lo mismo para tablas (filas fantasma) y KPIs. | M |
| A-03 | Alta | **Formatos numéricos distintos.** Pagos muestra `$2690.00` y `$0.00`; Inventario `$5400`; Reportes `$10,800` y `$1,010`. | Pagos, Inventario, Reportes. | Un solo formateador `es-MX` (miles con coma, moneda con o sin decimales según contexto), con `tabular-nums` para que las columnas alineen. | S |
| A-04 | Media | **Sin `h1`.** El panel tiene cero `h1`; "PANEL ADMINISTRATIVO" es solo estilo. | Medición del DOM. | Un `h1` por pantalla (puede mantener el estilo actual). Mejora la lectura con lector de pantalla y la navegación por encabezados. | S |
| A-05 | Media | **Fechas en tres formatos.** Citas usa `2026-11-02`; otros módulos `28 oct 2026`; Pagos `28 OCT 2026, 01:53 P.M.`. | Citas, Clientes, Pagos. | Un formateador: fecha corta `lun 2 nov`, con año solo si no es el actual; hora `13:53`; relativo (*hoy, ayer*) cuando ayude. | S |
| A-06 | Media | **Cada módulo arma su propia tabla.** Altura de fila, alineación, acciones y filtros cambian de una pantalla a otra. | Citas, Clientes, Pagos, Inventario. | Un componente `DataTable` con encabezado fijo, orden por columna, selector de densidad, barra de herramientas estándar (búsqueda · filtros · exportar a la derecha) y paginación visible. | L |
| A-07 | Media | **Controles nativos sin estilo.** Los campos de fecha muestran `dd/mm/aaaa` con el aspecto del navegador. | Pagos. | Selector de rango de fechas propio, con atajos (*Hoy, 7 días, Este mes*). | M |

### 3.2 Panel (dashboard)

| ID | Prioridad | Hallazgo | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- |
| A-10 | Media | **Indicadores con contexto pobre.** "Citas de hoy 1 · ▲650 % vs mes pasado" compara una cifra pequeña con una base mínima; "Ingresos de hoy $0" convive con "$2,690 esta semana" sin jerarquía. | Mostrar la variación solo con una base mínima; añadir tooltip con la base; resaltar la cifra principal y bajar el texto secundario. | S |
| A-11 | Media | **La gráfica de ingresos semanales no maneja datos escasos.** Siete de ocho semanas en cero producen una barra gigante; la explicación del color ("verde subió, rojo bajó, dorado la actual") está en el subtítulo, no en una leyenda. | Estado de "pocos datos"; leyenda visual; escala que no deje una barra de $2,690 junto a un eje de $3,000 sin referencia. | M |
| A-12 | Media | **Línea suavizada para datos discretos.** *Citas completadas del mes* usa curva con tensión: dibuja valles y picos entre fechas que sugieren valores intermedios. | Barras o línea escalonada para conteos; curva solo para magnitudes continuas. | S |
| A-13 | Baja | **Color categórico en un ranking de una sola dimensión.** *Servicios más pedidos* pinta cada barra de otro color (dorado, azul, verde, morado), lo que debilita la regla "dorado = acento". | Una sola tinta con gradación, y color solo cuando el color significa algo. | S |
| A-14 | Media | **Acciones rápidas poco jerarquizadas.** *Nueva cita, Nuevo cliente, Cobrar, Reportes* usan etiquetas de unos 10 px y compiten con el botón dorado *Nueva cita* de la barra lateral. | Una sola vía principal para crear; las acciones rápidas con tamaño legible o como atajos en la búsqueda. | S |
| A-15 | Baja | El selector de **Tema** ocupa un lugar protagonista en la barra superior. | Moverlo al menú del perfil. | S |

### 3.3 Citas

| ID | Prioridad | Hallazgo | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- |
| A-20 | Alta | **Demasiado ruido por fila.** Una cita confirmada puede mostrar *Iniciar, Completar, No asistió, Cobrar, Editar y Cancelar* más una línea de ayuda en ámbar; las filas quedan de alturas distintas. | Una **acción primaria según el estado** y el resto en un menú `⋯`; las ayudas pasan a tooltip o a la columna de estado. Altura de fila constante. | M |
| A-21 | Media | Faltan **búsqueda, rango de fechas, orden y paginación** a la vista; solo hay dos filtros que parecen botones y el aviso "mostrando hasta 50". | Barra de herramientas estándar (ver A-06) y filtro activo con indicador. | M |
| A-22 | Baja | La vista de lista no deja ver **carga por barbero ni huecos**; hay un calendario aparte. | Ofrecer una vista de día por barbero con arrastrar y soltar en una segunda fase. | L |

### 3.4 Clientes

| ID | Prioridad | Hallazgo | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- |
| A-30 | Alta | **El botón rojo *Eliminar* está en cada fila**, al mismo nivel que *Ver ficha*. Aunque pide confirmación, el riesgo y el ruido son altos. | Pasarlo al menú `⋯` y a la ficha; el borrado debe ser lógico y con motivo. | S |
| A-31 | Media | **Barra de herramientas dispersa:** el buscador es pequeño y queda aislado; *Exportar CSV* ocupa una fila entera a la derecha. | Una sola barra: búsqueda ancha · filtro por segmento · exportar. | S |
| A-32 | Baja | Las **tarjetas de segmento** (VIP, Nuevo, Activo, Inactivo) parecen filtros pero no lo son. | Que filtren la tabla y muestren el estado activo. | S |

### 3.5 Pagos, Inventario, Configuración y Analítica

| ID | Prioridad | Hallazgo | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- |
| A-40 | Media | **Folio ilegible.** `#0b7f60` es un fragmento de identificador. | Consecutivo humano (`P-000123`) con el identificador solo en el detalle. | M |
| A-41 | Baja | La tarjeta **Por método** es texto plano ("EFECTIVO: 12 · TARJETA: 2"). | Mini barras apiladas o dona pequeña. | S |
| A-42 | Media | **Dos "valores de inventario" distintos** sin aclarar: Inventario `$5,400` y Reportes `$10,800`. | Rotular **"a precio de compra"** y **"a precio de venta"**, o unificar el cálculo. | S |
| A-43 | Baja | Productos sin imagen real (icono genérico) y tabla sin orden. | Miniatura con *fallback* de marca; orden por columna (ver A-06). | S |
| A-44 | Media | **Configuración**: el formulario ocupa ~60 % del ancho y deja una columna vacía; no hay índice de secciones ni barra de guardado fija; el borde de los campos es de bajo contraste. | Dos columnas (índice a la izquierda, formulario a la derecha), barra de guardado fija con estado "cambios sin guardar", borde de campo a 3 : 1 como mínimo. | M |
| A-45 | **Alta (estratégica)** | **Analítica no tiene gráficas.** El *Centro de Análisis* del administrador son seis contadores, un enlace y el aviso "aún no hay análisis". Es la pantalla que debería ser el centro de inteligencia del negocio. | Convertirla en un tablero analítico real (ver sección 4). | L |

---

## 4. Recomendación estratégica: Centro de Análisis del administrador

Hoy lo más parecido a un tablero analítico vive repartido entre el panel y Reportes. La propuesta es que **Analítica** sea el lugar de las preguntas de negocio, con gráficas como las de las plataformas de referencia:

| Bloque | Pregunta que responde | Gráfica sugerida |
| --- | --- | --- |
| Ingresos | ¿Cómo vamos contra el periodo anterior? | Línea con periodo anterior superpuesto y meta mensual. |
| Mezcla de servicios | ¿Qué nos deja más dinero, no solo qué se pide más? | Barras horizontales por ingreso y por margen. |
| Ocupación | ¿Cuándo estamos llenos o vacíos? | Mapa de calor día × hora (ya existe `PeakHoursHeatmap`). |
| Retención | ¿Los clientes regresan? | Cohortes por mes de alta (matriz) y curva de retención. |
| Cancelaciones e inasistencias | ¿Dónde perdemos citas? | Barras apiladas por día de la semana y por barbero. |
| Equipo | ¿Cómo rinde cada barbero? | Dispersión ocupación × ingreso, con tabla. |
| Embudo de reserva | ¿Dónde se caen los clientes al reservar? | Embudo (visitas → servicio → horario → confirmada). |

Reglas para todas las gráficas: leyenda visual (no en el subtítulo), un solo color de énfasis más grises, ejes con unidades, estado de "pocos datos", exportar imagen o CSV, y tooltip con la cifra exacta.

---

## 5. Sistema de diseño: las siete reglas que más rinden

Si solo se hiciera esto, la percepción cambiaría en todos los roles a la vez:

1. **Escala tipográfica** con mínimo 12 px y un `h1` por pantalla (A-01, A-04).
2. **Formateadores únicos** de moneda, número y fecha, con `tabular-nums` (A-03, A-05).
3. **Componente `DataTable`** con barra de herramientas, orden, densidad y acciones en menú (A-06, A-20, A-30, A-31).
4. **Patrón de carga**: esqueleto en tarjetas, gráficas y tablas (A-02).
5. **Patrón de vacío**: ilustración de la mascota, una frase y una acción ("Aún no hay citas hoy · Crear cita").
6. **Guía de gráficas** (sección 4) aplicada a todas las del sistema.
7. **Selector de fechas y rangos** propio (A-07).

## 6. Orden de trabajo sugerido

| Fase | Contenido | Efecto |
| --- | --- | --- |
| 1 · Rápida (1–2 días) | A-03, A-04, A-05, A-10, A-12, A-13, A-14, A-31, A-30, A-42 | Pulido inmediato, riesgo bajo. |
| 2 · Sistema (1 semana) | A-01, A-02, `DataTable` (A-06, A-20, A-21), patrón de vacío | Cambia la sensación de toda la plataforma. |
| 3 · Producto (2 semanas) | A-45 (Centro de Análisis), A-07, A-44, A-40 | La parte "premium" visible para la dirección. |

## 7. Pendiente de esta auditoría

- Segunda pasada sobre Usuarios, Servicios, Campañas, Sorteos y Logs.
- Prueba con los otros temas (claro y variantes) y con 1920 px.
- Revisión de teclado y lector de pantalla pantalla por pantalla.

Siguiente informe: **02 Recepcionista**.
