# Auditoría UI/UX · 02 Recepcionista

Segundo informe de cinco. Mismo método y entorno que el [informe del administrador](01-administrador.md): staging `0f17e84`, demostración aislada, escritorio a 1440 × 900 px, tema *Sastrería Nocturna*. No evalúa la identidad, solo los componentes y la experiencia.

**Alcance:** Panel, Citas, Calendario, Pagos, Corte de caja, Pedidos y Lista de espera. Los problemas que ya están en el informe del administrador (texto pequeño, formatos, tabla de Citas, esqueletos de carga) aplican igual aquí y **no se repiten**; se citan por su ID (A-xx).

## 1. Veredicto

La recepcionista trabaja **de pie, con el cliente enfrente y con prisa**. Su pantalla debería parecerse a una **consola de mostrador**: quién llega, a quién cobro, qué se me atrasa. Hoy el panel es un resumen informativo con seis contadores y tarjetas casi vacías; funciona, pero **informa en lugar de ayudar a actuar**.

Lo mejor del rol es **Corte de caja**: estructura clara, miga de pan, estado vacío con la mascota y un flujo lineal. Es el modelo de cómo deberían verse los vacíos y los formularios del resto.

### Puntuación (1 = débil, 5 = referente)

| Dimensión | Nota | Por qué |
| --- | --- | --- |
| Orientación a la tarea (mostrador) | 2.5 | El panel muestra cifras, no acciones; falta el *check-in* y el cobro directo. |
| Jerarquía y legibilidad | 2.5 | Seis tarjetas diminutas llenas de ceros; gráfica con 13 etiquetas rotadas. |
| Consistencia | 3 | Estados vacíos distintos en cada pantalla (ver R-04). |
| Calendario | 3 | Buena leyenda y mes legible; sin vista de día con horas ni indicador de "ahora". |
| Estados vacíos | 3 | Corte de caja es excelente; Pedidos y Lista de espera, no. |
| Navegación | 3 | Dos ítems del menú aparecen activos a la vez en Calendario. |
| Percepción premium | 3 | Limpio y ordenado, pero poco "vivo" para una herramienta de uso continuo. |

## 2. Qué ya funciona (conservar)

- **Corte de caja:** miga de pan "Volver a pagos", título, subtítulo que explica la regla ("Cobros de citas y ventas de tienda realmente recibidos en el día"), tarjetas por paso y mascota en el vacío.
- **Menú reducido a lo operativo:** solo *Principal* y *Operación*; sin ruido de administración.
- **Calendario mensual** con leyenda de colores por estado, selector de barbero y vistas *Mes · Semana · Día · Lista*; el día de hoy está resaltado.
- Aviso de cobro ya diferenciado por método en Pagos.
- La **página 404** con Nava es un buen ejemplo de marca útil.

## 3. Hallazgos

| ID | Prioridad | Hallazgo | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- |
| R-01 | **Alta** | **El panel informa, no ayuda a actuar.** Seis contadores (*Citas de hoy, Cobrado hoy, Por cobrar, Pedidos, Clientes nuevos, Stock crítico*) en tarjetas diminutas, casi todos en cero, y debajo tarjetas vacías con "No hay más llegadas por hoy". | Rediseñar como **consola de mostrador**: columna principal con *Próximas llegadas* como tarjetas accionables (**Llegó · Cobrar · Reagendar**), a la derecha *Por cobrar* y *Pedidos por entregar*; los contadores bajan a una franja delgada. | L |
| R-02 | **Alta** | **Falta el *check-in*.** No hay un gesto de "el cliente ya llegó"; la recepcionista debe entrar a Citas y buscar la fila. | Botón **Llegó** en cada cita de hoy, con estado visible ("En sala") y aviso al barbero. | M |
| R-03 | Media | **Dos ítems activos en el menú.** En Calendario aparecen resaltados **Citas** y **Calendario** a la vez, porque `/appointments` coincide por prefijo con `/appointments/calendar`. | Coincidencia exacta para rutas con hijas, o agrupar Citas y Calendario como pestañas de una misma sección. | S |
| R-04 | Media | **Cada pantalla resuelve el vacío distinto:** Corte de caja usa mascota + título + frase; Pedidos deja una tabla con encabezados y "Sin pedidos que mostrar"; Lista de espera muestra una caja casi sin altura; el panel, una línea de texto gris. | Un componente `EmptyState` único (mascota, título, frase, **una acción**), usado también cuando un filtro no devuelve resultados. | M |
| R-05 | Media | **Gráfica *Citas por hora* sin lectura útil.** Una sola barra y 13 etiquetas rotadas ilegibles; no indica la hora actual. | Franja horaria compacta (09–21) con la hora actual marcada, etiquetas cada 2 h y la carga como bloques; o sustituirla por una línea de tiempo del día. | M |
| R-06 | Media | **Calendario sin vista de día con horas.** El mes muestra eventos de 10 px y "+3 más"; falta una vista de día por barbero con huecos y la línea de "ahora". | Vista de **día por columnas de barbero** con rejilla horaria, línea de hora actual y crear cita arrastrando; conservar el mes como resumen. | L |
| R-07 | Media | **Cuadro de fecha nativo** en Corte de caja (`29/10/2026` con icono del navegador). | Selector propio (ver A-07), con atajos *Hoy* y *Ayer*. | M |
| R-08 | Media | **Botón de acción principal casi invisible cuando está deshabilitado.** *Cerrar caja del día* sin efectivo contado queda en dorado apagado, con poco contraste, y no dice por qué está inactivo. | Estado deshabilitado legible y texto de ayuda: "Captura el efectivo contado para cerrar". | S |
| R-09 | Baja | **Títulos en distintos casos:** la barra superior dice "Lista De Espera" (cada palabra en mayúscula) mientras el título de la página es "Lista de Espera". | Un solo estilo (capitalización de oración) generado desde el mismo texto. | S |
| R-10 | Baja | **Pedidos:** las tarjetas *Pendientes / Entregados / Por cobrar* tienen etiquetas de color distintas (ámbar, verde, blanco) sin una regla clara. | Color solo para estados accionables (pendiente = ámbar), neutro para totales. | S |
| R-11 | Media | **Cobro sin atajo desde la cita.** Cobrar exige salir a Pagos y buscar la cita. | Desde la tarjeta de la llegada, **Cobrar** abre el modal de cobro precargado (cliente, servicio, monto). | M |
| R-12 | Baja | **Altas de cliente rápidas.** Para atender a alguien sin cuenta hay que ir a Clientes y llenar un formulario completo. | Alta exprés (nombre y teléfono) dentro de *Nueva cita*. | M |

## 4. Propuesta: el panel de recepción como consola de mostrador

```
┌───────────────────────────────────────────────────────────────────────────┐
│ Hola, Rosa · jueves 29 de octubre        [ Nueva cita ]  [ Cobrar ]       │
├──────────────────────────────────────────────┬────────────────────────────┤
│ PRÓXIMAS LLEGADAS                            │ POR COBRAR                 │
│  10:00  Elena Flores · Corte clásico         │  Marco Díaz   $150  Cobrar │
│         Bruno Raven      [Llegó] [Reagendar] │  Sofía Ríos   $160  Cobrar │
│  10:30  Marco Díaz · Corte + barba           ├────────────────────────────┤
│         Nava Panther     [Llegó] [Reagendar] │ PEDIDOS POR ENTREGAR       │
│                                              │  #0231  2 productos [Entregar]│
├──────────────────────────────────────────────┴────────────────────────────┤
│ Hoy: 12 citas · 3 en sala · $2,450 cobrado · caja esperada $1,200          │
└───────────────────────────────────────────────────────────────────────────┘
```

Principios: **una pregunta por bloque** ("¿quién llega?", "¿a quién cobro?", "¿qué entrego?"), **una acción primaria por tarjeta**, el resumen numérico en una franja delgada y **actualización en vivo** (el estado se refresca solo, sin recargar).

## 5. Orden de trabajo sugerido

| Fase | Contenido | Efecto |
| --- | --- | --- |
| 1 · Rápida | R-03, R-08, R-09, R-10 | Pulido inmediato. |
| 2 · Sistema | R-04 (`EmptyState`), R-05, R-07 y las reglas A-01 a A-07 | Coherencia con el resto de roles. |
| 3 · Producto | R-01, R-02, R-11 (consola de mostrador), R-06, R-12 | Hace que la recepción sea más rápida de verdad. |

Siguiente informe: **03 Ingeniero**.
