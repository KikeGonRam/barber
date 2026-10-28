# MongoDB: conexiones, permisos y operación

Revisión del código: 28 de octubre de 2026. Los nombres, roles, IP autorizadas,
tier de Atlas y respaldos remotos deben comprobarse en el entorno correspondiente.
Esta guía no publica usuarios, contraseñas ni URIs de conexión reales.

## Separación de datos

| Uso | Configuración | Valor de referencia en código |
| --- | --- | --- |
| Operación | `MONGODB_URI`, `MONGO_DATABASE`. | `barber_db` como base predeterminada. |
| Derivados de analítica | `ANALYTICS_MONGODB_URI`, `ANALYTICS_MONGO_DATABASE`. | Conexión `mongodb_analytics`; hereda core si faltan variables. |
| Desarrollo | Overlay de Compose y `.env.development`. | `mongo-dev`, `rsdev`, `urbanblade_dev`. |
| Pruebas | `.env.testing` aplicada por `test.ps1`. | `mongo-test`, `rs0`, `barber_db_test`. |

La arquitectura está documentada en [ADR-001](ADR-001-ARQUITECTURA-DE-DATOS.md).
Web y Android consumen la API y no se conectan directamente a Atlas.

## Guardas de entorno

[DataEnvironmentGuard](../../app/Support/DataEnvironmentGuard.php) valida los
contextos `shared`, `development` y `testing`.
La [configuración de base](../../config/database.php) la invoca al arrancar.
[TestCase](../../tests/TestCase.php) rechaza conexiones operativas o Atlas en
pruebas antes de los métodos de limpieza.

No cambies el nombre de una base ni desactives una guarda para superar un error.
Un contenedor puede conservar variables y cachés de una configuración anterior.
Consulta [PRUEBAS.md](PRUEBAS.md); nunca ejecutes PHPUnit directamente con el
entorno operativo del contenedor.

## Privilegios esperados

| Consumidor | Necesidad |
| --- | --- |
| Laravel operativo | Lectura/escritura solo en la base operativa del entorno. |
| Laravel analítico | Lectura sobre la base de derivados. |
| Spark core | Lectura del core. |
| Publicador analítico | Escritura limitada a su base de derivados y operaciones de publicación necesarias. |
| Operador de respaldo | Lectura y capacidades de backup acordadas; restauración con identidad y destino controlados. |

Son requisitos de mínimo privilegio, no una certificación de cuentas existentes.
Los reportes antiguos de fases contenían afirmaciones contradictorias sobre una
excepción de acceso amplio de Spark. Esa excepción no se elimina ni se cambia
con documentación; un operador autorizado debe verificar los privilegios actuales
sin escribir documentos de prueba en la base operativa.

Para una revisión, registra entorno, fecha, base y capacidades necesarias.
No vuelques usuarios reales, URIs ni resultados completos de autenticación
en un issue o archivo público.

## Red y configuración

- Verifica la lista de acceso y la conectividad efectiva de Atlas.
- No declares que existen NAT, peering, PrivateLink o IP fijas basándote en un
  diagrama antiguo.
- Mantén credenciales distintas por consumidor y entorno; no distribuyas una
  credencial de escritura junto con acceso de lectura.
- La codificación de caracteres especiales se realiza al configurar la URI;
  no pegues una URI completa en comandos que terminen en historial o logs.
- Una copia de `.env` o un archivo ignorado por Git sigue siendo sensible.
- Los modelos con borrado lógico pueden seguir ocupando índices únicos.

## Respaldos y recuperación

Un backup válido debe tener origen identificado, integridad comprobada y una
restauración ensayada en un destino aislado.
No puede calificarse como el último respaldo por aparecer en una guía de septiembre.

El backup ZIP de la aplicación no debe confundirse con un archivo BSON completo
con índices. Su implementación está en
[DatabaseBackupService](../../app/Services/System/DatabaseBackupService.php).
Consulta [continuidad operativa](CONTINUIDAD_OPERATIVA.md).

Antes de restaurar o modificar índices: confirmar origen/destino, respaldo,
ventana, dependencias y autorización. Un conteo igual no prueba por sí solo
integridad ni capacidad de recuperación.

## Rotación

El procedimiento es [PLAN_ROTACION_CREDENCIALES.md](PLAN_ROTACION_CREDENCIALES.md).
Cambiar una credencial puede requerir actualizar consumidores y recrear procesos;
es una operación técnica separada de este mantenimiento, con posible impacto en
producción. No se rota ni se prueban credenciales reales durante la revisión documental.
