---
name: urbanblade-data-architecture
description: Planifica o modifica conexiones, bases, MongoDB, Redis, Pulse, Docker o integración de datos entre barber, frontend-urban y spark. Usar antes de cualquier cambio de persistencia o contenedores.
---

# Arquitectura de datos de UrbanBlade

Lee primero `docs/ADR-001-ARQUITECTURA-DE-DATOS.md`: es la fuente de verdad y su
estado es **Aceptado**. Las fases 0 a 4 están terminadas. No repitas inventarios,
contenedores, migraciones ni cambios de usuarios ya realizados. Cualquier fase nueva
requiere planificación y autorización expresa antes de escribir datos, cambiar
infraestructura o actuar sobre Atlas.

Estado vigente:

- La base operativa continúa siendo `barber_db`; no la renombres ni la dividas por
  colección sin una nueva decisión arquitectónica aprobada.
- Los derivados permanecen en `urbanblade_analytics.analytics_insights`.
- `mongo-dev` (`rsdev`, `urbanblade_dev`) es persistente y `mongo-test`
  (`barber_db_test`) es aislado para pruebas; no crees contenedores equivalentes.
- La siguiente etapa pendiente es operacional: copia externa cifrada, retención,
  restauración ensayada y monitoreo. Planifícala antes de implementarla.
- La cuenta personal `luis` se conserva en Spark por decisión explícita del
  propietario. No la elimines, rotes, sustituyas ni reduzcas sin una orden nueva.

Invariantes:

1. `barber` es la única puerta de escritura de negocio; `frontend-urban` solo consume
   su API y nunca recibe credenciales de base.
2. Mantén juntos los datos operativos acoplados. No crees una base por colección.
3. Separa derivados analíticos en `urbanblade_analytics`; Spark lee core y solo escribe
   analytics con mínimo privilegio.
4. Pulse permanece en SQLite y Redis no es fuente de verdad.
5. Desarrollo, pruebas y Atlas deben usar nombres, credenciales y volúmenes distintos.
   `mongo-dev` será persistente; `mongo-test` seguirá efímero. Ambos requieren replica
   set.
6. Antes de una migración exige inventario, backup restaurable, conteos de referencia,
   ensayo local, corte reversible y ventana sin borrar el origen.
7. Nunca ejecutes pruebas fuera de `./test.ps1` ni comandos de escritura contra Atlas
   sin autorización explícita.
8. Ninguna IA ejecuta `git commit`, `git push`, merge, rebase ni publica PR. Entrega al
   usuario el resumen, validaciones y mensaje de commit sugerido en español.
9. Antes de editar, ejecuta `git status` en cada repositorio implicado y preserva los
   cambios locales. Trabaja únicamente en `main`.
