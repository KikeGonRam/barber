# Continuidad operativa y respaldos

Guía consolidada el 28 de octubre de 2026 a partir de los antiguos reportes de
fases. Describe requisitos y herramientas existentes; no declara un servicio de
backup activo ni autoriza operaciones externas.

## Alcance

Proteger la base operativa y los archivos necesarios para recuperar el servicio.
MongoDB operativo, derivados, Pulse y Redis tienen responsabilidades distintas;
consulta [ADR-001](ADR-001-ARQUITECTURA-DE-DATOS.md).

No se guardan dumps, claves de cifrado, exportaciones de usuarios ni
comprobantes en Git. No publicar rutas privadas, inventarios de cuentas o
ubicaciones de custodia que permitan recuperar secretos.

## Capacidad disponible y evidencia necesaria

| Elemento | Qué permite | Qué falta comprobar en cada entorno |
| --- | --- | --- |
| DatabaseBackupService | Exportación ZIP desde la aplicación. | Base efectiva, colecciones incluidas, conteos e índices que no conserva. |
| Scripts de respaldo | Preparación, cifrado, ensayo y publicación según cada script. | Parámetros, permisos y comportamiento real; no todos son jobs activos. |
| Almacenamiento externo | Copia fuera del equipo cuando se configura. | Cifrado, acceso, versionado, retención, costes y alertas. |
| Atlas | Funciones disponibles según el clúster contratado. | Tier y Backup/PITR actualmente habilitados. |
| Ensayo de restauración | Evidencia de que un backup puede recuperarse. | Fecha, integridad, tiempos y destino aislado. |

Los ensayos sintéticos y dry-runs no demuestran que exista una copia externa de
datos operativos. Tampoco debe presentarse un RPO/RTO propuesto como garantía.

## Flujo de respaldo

1. Identificar el entorno y el origen sin imprimir credenciales.
2. Elegir formato y alcance: datos, índices y metadatos necesarios.
3. Preparar una copia con permisos restrictivos en almacenamiento temporal.
4. Cifrar antes de salir del equipo y calcular un hash para verificar integridad.
5. Publicar únicamente en el destino autorizado.
6. Comprobar integridad y conservar evidencia sin datos personales.
7. Ejecutar restauraciones periódicas en un destino aislado.
8. Registrar vencimientos, fallos, retención y responsable de atender alertas.

Las identidades de escritura de backups y recuperación deben tener permisos
diferentes. La clave de cifrado no se almacena junto al objeto cifrado.

## Objetivos y decisiones pendientes

Los planes anteriores proponían una copia diaria, retención de 7 diarias,
4 semanales y 6 mensuales. Contenían RTO distintos (2 y 4 horas).
Son antecedentes de diseño, no una política remota comprobada.

El responsable debe fijar RPO, RTO, retención, ventana y canales de alerta,
y aportar un ensayo que demuestre su cumplimiento. No se configura una tarea
programada ni se crea un bucket para completar esta documentación.

## Restauración

- Verificar formato, hash, fecha, origen y autorización del backup.
- Restaurar primero en un destino aislado con nombre y credenciales propios.
- Validar índices, relaciones, estados y funciones críticas además de conteos.
- Acordar el corte y el rollback antes de modificar datos operativos.
- No usar una suite de pruebas ni seeders como herramienta de restauración.
- No borrar el origen ni backups anteriores como parte del ensayo.

## Seguridad y mantenimiento

Para rotación, consultar [seguridad de credenciales](PLAN_ROTACION_CREDENCIALES.md).
Para conexiones, [MongoDB](MONGODB_ATLAS.md).
Las aprobaciones citadas en reportes antiguos son contexto histórico; no conceden
permisos a un asistente ni reemplazan el alcance solicitado por el propietario.
