# ADR-001: Separar datos por carga de trabajo, no por entidad

**Estado:** Propuesto; requiere aprobación del usuario antes de implementar  
**Fecha:** 2026-09-16  
**Decisor:** propietario de UrbanBlade

## Resultado recomendado

Mantener juntos en una sola base operativa los datos que participan en los mismos
flujos de negocio y transacciones. Separar físicamente analítica, observabilidad,
caché/colas y pruebas. Crear más bases solo cuando exista una necesidad comprobable,
no una base por colección o módulo.

Esta decisión no autoriza migraciones, creación de contenedores, escrituras en Atlas,
`commit`, `push` ni despliegues. Primero se aprueba el plan; después se implementa por
fases reversibles.

## Contexto comprobado

- `barber` es el servidor Laravel/JSON y hoy usa MongoDB Atlas `barber_db` como base
  predeterminada.
- Usuarios, perfiles, citas, pagos, pedidos, inventario, fidelización, membresías y
  permisos comparten identificadores y operaciones transaccionales. Separarlos ahora
  aumentaría consultas cruzadas, consistencia eventual y complejidad de rollback.
- `spark` lee datos operativos de `barber_db` y escribe `analytics_insights` en esa
  misma base.
- Laravel Pulse ya usa SQLite separado porque es observabilidad efímera y no dato de
  negocio.
- Redis ya separa caché y colas.
- `mongo-test` es un replica set local, efímero y exclusivo de pruebas.
- El contenedor normal de `barber` recibe el `.env` que puede apuntar a Atlas. Ya
  ocurrieron incidentes de borrado/contaminación de datos por confundir entornos.
- `frontend-urban` no debe conectarse directamente a ninguna base: consume únicamente
  el contrato HTTP de `barber`.

## Decisión propuesta

### 1. Base operativa: `urbanblade_core`

Conservar juntas las colecciones que requieren consistencia de negocio:

- identidad, roles, permisos y tokens;
- clientes, barberos y configuración;
- servicios, horarios, citas y lista de espera;
- pagos, caja, pedidos, productos e inventario;
- membresías, fidelización, referidos y facturas;
- notificaciones, campañas y actividad de auditoría que deba acompañar una operación.

Durante la transición, `barber_db` continúa siendo el nombre real. Renombrarla o
moverla a `urbanblade_core` es una fase posterior opcional; no aporta seguridad por sí
solo y exige backup, copia, verificación y corte controlado.

### 2. Base analítica: `urbanblade_analytics`

Mover a una base MongoDB separada únicamente los productos derivados y
reconstruibles de Spark, comenzando por `analytics_insights`. `spark` mantiene acceso
de solo lectura a la base operativa y escritura limitada a la base analítica. Laravel
lee esa base mediante una conexión explícita `mongodb_analytics`.

No se duplicarán clientes, citas o pagos como fuente de verdad. Si después se necesita
histórico para análisis, se diseñará una exportación incremental, seudonimizada y
reconstruible; nunca escritura de Spark sobre colecciones operativas.

### 3. Observabilidad: SQLite dedicado

Mantener Pulse en `database/pulse.sqlite`. No mover allí datos de negocio. Si el
volumen o la concurrencia lo exigen en despliegue, evaluar PostgreSQL dedicado para
observabilidad como una decisión independiente.

### 4. Caché y trabajo asíncrono: Redis

Mantener Redis para caché, sesiones y colas, usando bases/prefijos distintos. Redis no
será fuente de verdad.

### 5. Desarrollo y pruebas: MongoDB local aislado

Agregar, tras aprobación, un servicio `mongo-dev` persistente y en replica set para el
desarrollo diario. Debe usar un volumen propio y una base `urbanblade_dev`. El servicio
actual `mongo-test` seguirá siendo efímero y usará `barber_db_test`.

Los servicios deben quedar inequívocamente separados:

| Entorno | Servicio | Base | Persistencia | Uso permitido |
|---|---|---|---|---|
| Desarrollo | `mongo-dev` | `urbanblade_dev` | volumen local | desarrollo manual |
| Pruebas | `mongo-test` | `barber_db_test` | efímera | solo `./test.ps1` |
| Compartido/Atlas | externo | `barber_db` inicialmente | Atlas | datos operativos controlados |
| Analítica | Atlas o local según entorno | `urbanblade_analytics` | separada | derivados de Spark |

`mongo-dev` no reemplazará ni compartirá volumen con `mongo-test`. Ambos requieren
replica set porque pagos e inventario usan transacciones multi-documento.

## Opciones descartadas por ahora

### Una base por dominio funcional

Separar auth, citas, pagos, inventario y social ofrece aislamiento, pero exige resolver
transacciones distribuidas, referencias entre bases, copias de datos y fallos parciales.
El tamaño y la madurez actuales no justifican ese costo.

### Mantener todo en `barber_db`

Es lo más simple, pero conserva el acoplamiento de Spark y el riesgo de mezclar datos
operativos con derivados. Tampoco resuelve que el desarrollo normal pueda apuntar a
Atlas.

### Un servidor Mongo distinto por cada categoría

Da el mayor aislamiento, pero aumenta costo y operación sin beneficio proporcional.
La primera separación puede usar bases diferentes dentro del mismo cluster; las
credenciales y permisos deben limitarse por base.

## Plan de implementación por fases

### Fase 0: inventario y respaldo

1. Confirmar colecciones, tamaños, índices y consumidores sin imprimir secretos ni
   modificar datos.
2. Identificar todos los campos que consume Spark.
3. Crear y verificar un backup recuperable de `barber_db` antes de cualquier cambio.
4. Documentar conteos y checksums de referencia para verificar copias.

**Salida:** inventario, respaldo probado y plan de rollback. Sin migraciones.

### Fase 1: Mongo local de desarrollo

1. Añadir `mongo-dev` y `mongo-dev-init` al Compose con replica set y volumen propio.
2. Crear una plantilla `.env.development.example` sin secretos.
3. Hacer que el arranque local falle de forma segura si mezcla nombres de base de
   desarrollo/prueba con Atlas.
4. Verificar transacciones, persistencia tras reinicio y aislamiento respecto a
   `mongo-test`.

**Rollback:** detener los nuevos servicios y volver a la configuración anterior; no
se toca Atlas.

### Fase 2: conexión analítica separada

1. Añadir `mongodb_analytics` en Laravel y variables separadas de URI/base.
2. Asignar `AnalyticsInsight` exclusivamente a esa conexión.
3. Configurar Spark con credenciales de lectura para core y otras de escritura limitada
   para analytics.
4. Probar exportación contra bases locales antes de Atlas.

**Compatibilidad:** durante el corte, Laravel puede leer primero analytics y, mediante
una bandera temporal, usar `barber_db.analytics_insights` como fallback de solo lectura.

### Fase 3: copia y corte controlado de analítica

1. Copiar únicamente `analytics_insights` después del backup.
2. Comparar conteos, esquema lógico y una muestra sin PII.
3. Cambiar primero el lector Laravel y después el escritor Spark.
4. Mantener la colección anterior sin borrarla durante una ventana acordada.

**Rollback:** restaurar las variables/conexión anterior. No borrar el origen en la
misma fase.

### Fase 4: endurecimiento

1. Aplicar usuarios de base con mínimo privilegio: Laravel core lectura/escritura,
   Laravel analytics solo lectura, Spark core solo lectura y Spark analytics escritura.
2. Añadir comprobaciones automatizadas que bloqueen pruebas contra nombres no
   permitidos.
3. Actualizar documentación y diagramas después de validar el flujo completo.

## Criterios de aceptación

- `frontend-urban` funciona únicamente contra la API, sin credenciales de base.
- Las pruebas solo usan `barber_db_test` mediante `./test.ps1`.
- El desarrollo local persiste en `urbanblade_dev` y no escribe en Atlas.
- Pagos e inventario conservan atomicidad en replica set.
- Spark no puede escribir en la base operativa.
- `analytics_insights` puede reconstruirse y Laravel la lee desde la conexión analítica.
- Existe backup verificado y rollback ensayado antes del corte.
- No se elimina la colección original durante la misma entrega.

## Regla de entrega Git

Ningún proveedor o agente de IA ejecutará `git commit`, `git push`, merge, rebase ni
creará/publicará PR. La IA puede editar, revisar y validar; al terminar debe entregar
en español el resumen, los archivos afectados, las pruebas realizadas y un mensaje de
commit sugerido. El usuario humano revisa, crea el commit y hace el push.
