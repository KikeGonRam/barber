# Auditoría UI/UX · 05 Cliente

Quinto y último informe de roles. Mismo método y entorno que el [del administrador](01-administrador.md): staging `0f17e84`, demostración aislada, tema *Sastrería Nocturna*, escritorio a 1440 × 900 px y, como referencia, la revisión en móvil de la prueba de usuario (28 de octubre). Los problemas generales (texto pequeño, formatos, esqueletos) están en el informe 01.

**Alcance:** página de inicio pública, acceso, reserva de cita, panel del cliente, Mis Citas y Tienda. No se revisaron Membresía, Paquetes, Gift Cards, Referidos, Recibos ni Perfil.

## 1. Veredicto

El cliente es quien **decide si UrbanBlade se siente premium**, y es el rol donde ya se ve más cerca de lograrlo. La página de inicio tiene presencia de marca (titular en dos tipografías, farol animado, fotografía), el acceso con fotografía y mascota es memorable y el **panel del cliente** (próxima cita como protagonista, nivel con barra de progreso, tarjeta de membresía, tres accesos rápidos) es de lo más cuidado de toda la plataforma.

Lo que resta puntos son **contenido visual faltante** (imágenes vacías en servicios y productos), **redundancias** (la misma cita y el mismo botón aparecen varias veces) y una **jerarquía de llamadas a la acción confusa** (la acción principal de la barra lateral es "Explorar tienda", mientras que lo que un cliente quiere hacer es reservar).

### Puntuación (1 = débil, 5 = referente)

| Dimensión | Nota | Por qué |
| --- | --- | --- |
| Primera impresión (inicio y acceso) | 4.5 | Hero con carácter; acceso con fotografía y mascota. |
| Claridad de la tarea principal (reservar) | 3.5 | Pasos claros; columna estrecha y llamadas a la acción duplicadas. |
| Panel del cliente | 4 | Próxima cita, nivel y tarjeta: muy bien jerarquizado. |
| Contenido visual | 2 | Servicios y productos sin imagen real (bloques oscuros e iconos de tijeras). |
| Tienda | 3 | Cuadrícula limpia; sin fotos, sin filtros útiles ni vista rápida. |
| Mis Citas | 3.5 | Tarjetas claras con la acción correcta por estado; repite la próxima cita. |
| Percepción premium | 3.5 | Cerca; los vacíos visuales delatan la maqueta. |

## 2. Qué ya funciona (conservar)

- **Hero de inicio:** "LA EXCELENCIA *en cada detalle*" mezclando tipografía en negrita y cursiva, con el farol como remate de marca.
- **Acceso** a pantalla dividida (fotografía a la izquierda, formulario a la derecha) con la mascota y el mensaje "Donde el estilo toma vida".
- **Panel del cliente:** *Tu próxima cita* con fecha y hora grandes y estado visible; *Tu nivel* con barra de progreso y la meta ("te faltan 4 visitas para V.I.P"); **tarjeta de membresía** con aspecto de tarjeta real; estadísticas de visitas, asistencia, barbero favorito y puntos.
- **Mis Citas:** la acción principal cambia con el estado (*Reagendar / Cancelar* en pendiente; **Pagar con tarjeta** en confirmada; *Ver ticket* en completada) con texto que explica el siguiente paso.
- **Asistente de reserva** en cuatro pasos (Servicio, Barbero, Horario, Confirmar), siempre visibles.
- Página **404** con Nava ("Este corte quedó fuera del mapa").

## 3. Hallazgos

| ID | Prioridad | Hallazgo | Recomendación | Esfuerzo |
| --- | --- | --- | --- | --- |
| C-01 | **Alta** | **Imágenes ausentes que parecen un producto sin terminar.** Los servicios del inicio muestran un recuadro oscuro vacío y un panel grande con "Servicio de demostración"; la Tienda muestra un icono de tijeras en lugar de la foto del producto. | Imagen **por defecto de marca** por categoría (corte, barba, combo, cuidado) con la misma iluminación; exigir foto al publicar un producto o servicio; *skeleton* mientras carga. | M |
| C-02 | **Alta** | **Acción principal contradictoria.** Para el cliente, el botón dorado grande de la barra lateral es **"Explorar tienda"**, pero lo que más importa es **reservar**; "Reservar cita" aparece arriba a la derecha, en un acceso rápido y en el menú. | Un solo botón principal en la barra lateral: **Reservar cita**. La tienda pasa a ser un acceso secundario. | S |
| C-03 | Media | **Redundancias.** La próxima cita aparece dos veces en Mis Citas (bloque destacado y primera tarjeta); el panel repite "Reservar" tres veces (botón superior, acceso rápido y barra lateral). | Mostrar cada cosa una vez: el bloque destacado reemplaza a la tarjeta duplicada; un solo "Reservar" por pantalla. | S |
| C-04 | Media | **La reserva ocupa una columna estrecha** (~470 px) en una pantalla de 1440 px, con grandes márgenes vacíos; el resumen no se ve hasta elegir. | Diseño de dos columnas en escritorio: pasos a la izquierda y **resumen fijo** (servicio, barbero, hora, total) a la derecha, visible desde el primer paso. | M |
| C-05 | Media | **Tienda sin herramientas de compra.** Solo buscador y una categoría; no hay orden por precio, vista rápida ni indicador de disponibilidad más allá de "20 disponibles" en texto diminuto. | Orden, filtros por categoría con chips, **vista rápida** en modal, "Agregar al carrito" con confirmación en el propio botón y precio como dato principal. | M |
| C-06 | Media | **Estadísticas del panel poco expresivas.** *Visitas 7 · Asistencia 86 % · Tu barbero Nava · Puntos 60* son cuatro datos en una franja sin jerarquía; "Tus visitas" muestra una gráfica pequeña sin explicar qué mejorar. | Convertir la asistencia y los puntos en logros ("7 visitas seguidas", "te faltan 40 puntos para un corte gratis") y dar a la gráfica una lectura clara. | M |
| C-07 | Media | **Sin recordatorio visual de la cita fuera del panel.** Si el cliente navega a Tienda o Beneficios, pierde de vista su próxima cita. | Chip persistente en la barra superior ("Mañana 11:00 · Nava") que abre la cita. | S |
| C-08 | Baja | **Textos secundarios muy pequeños** en tarjetas de producto y cita ("20 disponibles", "Esperando que tu barbero apruebe la cita"). | Aplicar la escala mínima de 12 px (A-01). | S |
| C-09 | Baja | **Estados de la cita solo por color** en el chip (Pendiente ámbar, Confirmada azul). | Añadir icono para no depender del color. | S |
| C-10 | Baja | **Footer del asistente de reserva escueto**: dirección y enlaces legales sin confianza social. | Reseñas, calificación y fotos del local en la página de reserva. | M |

## 4. Propuesta: la reserva como momento de marca

La reserva es la conversión más importante del negocio. Un flujo premium tendría:

1. **Dos columnas** (pasos a la izquierda, resumen a la derecha).
2. **Fotografía del servicio y del barbero** al elegir (con la imagen por defecto de la marca si no hay).
3. **Horarios por franja** (mañana, tarde, noche) con indicador de "pocos lugares".
4. **Confirmación con ceremonia**: mascota, resumen, botón "Añadir al calendario" y enlace de compartir.
5. **Recordatorio** persistente hasta la cita (chip superior y notificación).

## 5. Orden de trabajo sugerido

| Fase | Contenido | Efecto |
| --- | --- | --- |
| 1 · Rápida | C-02, C-03, C-08, C-09 | Quita contradicciones y ruido. |
| 2 · Contenido | C-01 (imágenes por defecto y obligatorias) | La mayor mejora visible en la percepción premium. |
| 3 · Experiencia | C-04, C-05, C-07 | Reserva y tienda al nivel de una plataforma comercial. |
| 4 · Emoción | C-06, C-10 | Fidelización y confianza. |
