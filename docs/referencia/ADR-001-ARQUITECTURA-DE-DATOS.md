# ADR-001: separar datos por carga de trabajo

**Estado:** aceptada. Configuración contrastada con el código el 28 de octubre de 2026.
**Decisión original:** 16 de septiembre de 2026.
**Ámbito:** backend y consumidores de datos; no acredita roles remotos efectivos.

## Contexto

Citas, identidad, pagos, pedidos, inventario y lealtad comparten identificadores y
operaciones de negocio. Separarlos por colección introduciría coordinación entre
bases y transacciones distribuidas sin una necesidad demostrada.

Los derivados analíticos son reconstruibles y tienen otro ciclo de publicación.
Pulse necesita almacenamiento SQL; Redis atiende caché y trabajo asíncrono.
Desarrollo y pruebas requieren aislamiento para no afectar datos operativos.

## Decisión

1. Mantener juntos los datos operativos en la conexión `mongodb`.
   El valor predeterminado del código sigue siendo `barber_db`.
2. Leer los derivados a través de `mongodb_analytics`.
   El modelo [AnalyticsInsight](../../app/Models/AnalyticsInsight.php) fija esa conexión.
3. Mantener Pulse en su conexión dedicada y Redis fuera de la fuente de verdad.
4. Usar `mongo-dev` persistente para desarrollo y `mongo-test` aislado para pruebas.
   Ambos usan replica set.
5. Web y Android acceden mediante API; no reciben credenciales de base de datos.

## Implementación comprobable

| Elemento | Fuente |
| --- | --- |
| Conexiones y fallback analítico | [config/database.php](../../config/database.php) |
| Contextos shared/development/testing | [DataEnvironmentGuard](../../app/Support/DataEnvironmentGuard.php) |
| Mongo de desarrollo, rsdev y volumen propio | [docker-compose.development.yml](../../docker-compose.development.yml) |
| Mongo de pruebas y su inicialización | [docker-compose.yml](../../docker-compose.yml) |
| Guardia de ambas conexiones en pruebas | [tests/TestCase.php](../../tests/TestCase.php) |
| Ejecución local segura | [test.ps1](../../test.ps1) |

La conexión analítica hereda URI/base operativa si sus variables no están
definidas. Por tanto, su mera existencia en código no garantiza separación física.
Verifica la configuración efectiva antes de afirmar que hay dos bases aisladas.

## Alternativas descartadas

- **Una base por dominio:** añade consultas cruzadas y coordinación para pagos,
  citas e inventario.
- **Todo en una base sin separación de entornos:** permite que pruebas o publicación
  de derivados afecten la operación.
- **Otro servidor por cada tipo de datos:** aumenta coste y administración sin
  necesidad comprobada.

Renombrar la base a `urbanblade_core` queda como propuesta opcional, no como
migración aprobada por este documento. No aporta aislamiento por el nombre.

## Consecuencias y límites

- El core conserva consistencia transaccional dentro de un replica set.
- Los consumidores analíticos deben tener lectura del core y escritura limitada a
  derivados. Esto depende de permisos de servidor, no de una promesa del código.
- Un Mongo local no sustituye un respaldo restaurable de producción.
- Una guarda de entorno reduce errores conocidos, pero no autoriza usar datos
  reales en pruebas.
- No se recrean contenedores, usuarios, volúmenes ni migraciones como parte de una
  actualización documental.

El procedimiento vigente está en [MongoDB](MONGODB_ATLAS.md),
[clonado](CLONAR_PROYECTOS.md), [pruebas](PRUEBAS.md) y
[continuidad](CONTINUIDAD_OPERATIVA.md). Los reportes de fases anteriores se
consolidaron en estas guías.
