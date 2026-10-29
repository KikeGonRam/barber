# Auditoría UI/UX · 04 Barbero

Cuarto informe de cinco. Mismo método y entorno que el [del administrador](01-administrador.md): staging `0f17e84`, demostración aislada, tema *Sastrería Nocturna*. Esta vez se vio el rol en escritorio (1440 × 900 px) y se tomó como referencia la revisión en móvil (375 px) de la prueba de usuario del 28 de octubre, porque **el barbero usa el teléfono entre cliente y cliente**. Los problemas generales (texto pequeño, formatos, esqueletos) están en el informe 01.

**Alcance:** Panel, Mi Agenda, Mi Horario y Mi Portafolio. No se revisó Mi Calendario, Muro Inspiración ni Notificaciones.

## 1. Veredicto

Es el rol con **mejor diseño de producto** hoy. La carcasa "amable" (islas flotantes, esquinas suaves) lo distingue bien de las pantallas de datos de administración y recepción, y el panel responde a la pregunta correcta: **"¿qué tengo que hacer ahora?"**, con la solicitud por aprobar como protagonista, botones grandes **Aprobar / Rechazar** y la agenda del día debajo. Mi Horario, con su resumen semanal visual, es de lo más cuidado de toda la aplicación.

Lo que le falta para sentirse premium es **vida y orientación a la jornada**: el panel hoy es estático, los vacíos son texto plano y la agenda no se parece a una agenda (no hay línea del día).

### Puntuación (1 = débil, 5 = referente)

| Dimensión | Nota | Por qué |
| --- | --- | --- |
| Orientación a la tarea | 4 | Aprobar y avanzar citas están a un toque; falta la vista "mi día". |
| Carcasa y atmósfera | 4 | Distinta de la de datos; amable, sin perder la marca. |
| Consistencia dentro del rol | 3.5 | Mi Horario y Portafolio son sólidos; Mi Agenda es la más débil. |
| Estados vacíos | 3 | El portafolio usa mascota; la agenda y el panel, texto gris. |
| Móvil | 3 | Mejoró en la prueba (2 × 2, botones apilados), pero la barra superior y las islas consumen alto. |
| Visualización | 3 | Mini gráficas simples y claras; sin metas ni comparativa. |
| Percepción premium | 3.5 | Cuidada, pero poco "viva". |

## 2. Qué ya funciona (conservar)

- **"Esperando tu aprobación"** como tarjeta protagonista, con color de acento y dos acciones claras.
- **Aro de avance del día** y saludo personalizado ("Buen día, Nava").
- **Mi Horario:** interruptor por día, resumen **"Tu semana"** con barras sobre eje de 6:00 a 24:00, días con atención y horas por semana; y desde la última versión, **segunda franja** para el descanso.
- **Portafolio** con mascota y una instrucción clara en el vacío.
- **Carcasa amable** distinta de la corporativa: buena decisión de producto.

## 3. Hallazgos

| ID | Prioridad | Hallazgo | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- |
| B-01 | **Alta** | **La agenda no parece una agenda.** Mi Agenda es una lista de citas con filtros y cuatro contadores; no hay línea del día con horas, huecos ni indicador de "ahora". | Vista **Hoy** como línea de tiempo vertical (de la primera a la última cita) con la cita en curso resaltada, los huecos visibles ("Libre 40 min") y botón de acción contextual (*Iniciar*, *Terminar*, *Cobrar*). | L |
| B-02 | Alta | **Panel estático y con vacío débil.** Con cero citas muestra "No tienes citas hoy. Disfruta el descanso" en texto gris dentro de una tarjeta alta. | Usar el patrón de vacío con mascota y una acción útil ("Ver mi semana", "Compartir mi enlace de reserva"). | S |
| B-03 | Media | **Los cuatro contadores no dicen nada accionable** (En periodo, Completadas, Productividad, Ingreso histórico). "Productividad 0 %" no explica su fórmula. | Una sola cifra protagonista (**ingresos del día**) con meta, y el resto en una línea secundaria; añadir explicación al pasar el cursor. | S |
| B-04 | Media | **Filtros que parecen botones.** *Día* y *Todos los estados* son selectores con aspecto de botón y sin indicador de filtro activo. | Segmentado *Día · Semana · Mes* y chips de estado con conteo. | S |
| B-05 | Media | **Móvil: la carcasa consume alto.** La barra superior, el saludo y el aro dejan poco espacio para la lista; ya se corrigieron contadores y botones, pero la cabecera sigue ocupando más de un cuarto de pantalla. | Cabecera compacta al desplazar (se colapsa) y el aro de avance integrado en la barra superior. | M |
| B-06 | Media | **"Copiar a todos" ambiguo** en Mi Horario: copia el horario de ese día a toda la semana, incluso a los días de descanso. | Rotularlo "Aplicar a todos los días" con confirmación, o "Copiar a…" con selección de días. | S |
| B-07 | Baja | **Campos de hora nativos.** Cada hora usa el control del navegador (`09:00 a. m.`), difícil de tocar con el pulgar y distinto entre navegadores. | Selector de hora propio con pasos de 15 minutos y pulsaciones grandes en móvil. | M |
| B-08 | Media | **Sin "mi enlace".** El barbero no tiene un enlace de reserva directa para compartir en redes. | Tarjeta "Comparte tu agenda" con enlace y código QR (`/reservar?barbero=…`). | M |
| B-09 | Baja | **Mi Portafolio: contadores en cero** (Trabajos, Reacciones, Comentarios, Guardados) ocupan una fila entera antes de que haya contenido. | Ocultarlos hasta la primera publicación o fundirlos con el botón de publicar. | S |
| B-10 | Baja | **Sin reconocimiento.** Ninguna pantalla celebra logros (racha de citas, reseña nueva, meta del mes). | Mensajes breves y mascota en hitos; es el rol donde una dosis de motivación más rinde. | M |

## 4. Propuesta: "Mi día" como pantalla principal

```
Buen día, Nava                                  ◐ 3 de 7 citas
─────────────────────────────────────────────────────────────
POR APROBAR (1)   Sofía Ríos · 12:00 · Barba      [Aprobar] [Rechazar]

AHORA     11:30  Sofía Ríos · Corte + barba        ● En curso 18 min
                                                   [Terminar]
PRÓXIMA   12:30  Marco Díaz · Corte clásico        en 42 min
          Libre 30 min
          13:00  Luis Soto · Barba                 [Iniciar a las 12:55]
─────────────────────────────────────────────────────────────
HOY  $450 de $800 meta · 5 de 7 completadas · propinas $60
```

Principios: la **cita en curso** es siempre lo más grande; el tiempo se muestra como **tiempo relativo** ("en 42 min"); **un botón por tarjeta**; todo cabe en una pantalla de teléfono sin desplazarse.

## 5. Orden de trabajo sugerido

| Fase | Contenido | Efecto |
| --- | --- | --- |
| 1 · Rápida | B-02, B-03, B-04, B-06, B-09 | Pulido de las pantallas existentes. |
| 2 · Móvil | B-05, B-07 | Mejor uso entre cliente y cliente. |
| 3 · Producto | B-01 ("Mi día"), B-08 (enlace y QR), B-10 | El rol deja de ver una lista y pasa a tener una herramienta de jornada. |

Siguiente informe: **05 Cliente**.
