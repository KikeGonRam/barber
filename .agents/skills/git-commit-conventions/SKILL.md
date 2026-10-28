---
name: git-commit-conventions
description: Flujo de Git y publicación de UrbanBlade, subordinado a la instrucción vigente del propietario.
---

# Git y publicación

1. Inspecciona estado y rama de cada repositorio; preserva cambios preexistentes.
2. Trabaja en una rama por cambio desde origin/main actualizado, sin perder ediciones.
3. Valida el alcance con los comandos canónicos; en barber, pruebas solo con .\test.ps1.
4. Usa staging selectivo por rutas; revisa diff --cached antes de un commit.
5. El primer commit, push o PR de cada cambio requiere la orden del propietario
   (por ejemplo, “súbelo” o “haz el PR”). La orden de otro cambio no se hereda.
6. Publica por PR a main; fusiona cuando lo autorice el usuario y estén verdes los
   checks correspondientes al SHA actual. Conserva ramas y respeta protecciones.
7. Separa commit local, push, CI, fusión y despliegue en el reporte.
   No ejecutes un despliegue si el usuario limita el trabajo a documentación.
8. Antes de un push autorizado, revisa automatismos externos: un archivo .md puede
   disparar builds de Vercel. No deduzcas ausencia de deploy solo por la extensión.

Commits y PR en español, con título `tipo(alcance): descripción` y evidencia real.
Conserva la autoría humana configurada; sin Co-Authored-By ni Generated with.
No fuerces push, no reescribas historial publicado ni borres ramas del trabajo.
No publiques credenciales o datos personales en commits, PR o logs.

Los procedimientos operativos están en docs/referencia/CI_CD.md y
docs/referencia/DESPLIEGUE_AWS_STAGING.md. Las autorizaciones históricas no sustituyen el
alcance que el propietario fija para la tarea actual.
Mantén esta guía sincronizada en .claude/skills y .agents/skills.
