---
name: urbanblade-data-architecture
description: Planifica o modifica conexiones, bases, MongoDB, Redis, Pulse, Docker o integración de datos entre barber, frontend-urban y spark. Usar antes de cualquier cambio de persistencia o contenedores.
---

# Arquitectura de datos de UrbanBlade

Lee primero `docs/ADR-001-ARQUITECTURA-DE-DATOS.md`. Su estado es **Propuesto**: no
implementes fases, no crees contenedores y no migres datos hasta que el usuario apruebe
expresamente la fase concreta.

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
