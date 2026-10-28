# Cómo contribuir

## Preparar un cambio

1. Revisa el README, el índice de documentación y las instrucciones del repositorio.
2. Consulta `git status --short` y conserva cualquier trabajo ajeno.
3. Actualiza la referencia remota con `git fetch origin` y crea una rama desde
   `origin/main`, por ejemplo `docs/actualizar-guia` o `fix/corregir-reserva`.
4. Limita el cambio a un problema; evita mezclar documentación, dependencias y funciones.
5. Ejecuta las comprobaciones que correspondan y registra el comando y resultado.
6. Revisa `git diff --check` y el diff; agrega al commit únicamente las rutas revisadas.
7. Abre un PR a `main` con título y descripción en español.

Los commits siguen `tipo(alcance): descripción` y la autoría humana configurada.
La rama publicada se conserva. La integración pasa por PR y los checks requeridos;
no se utiliza push directo ni forzado a `main`.

## Validación y documentación

Los comandos vigentes están en el [README](README.md) y
[CI/CD](docs/CI_CD.md). Para documentación:

```powershell
python scripts/check_docs.py
git diff --check
```

El validador revisa los enlaces locales de los documentos de entrada, no todas las
guías históricas ni los enlaces externos o sus anclas. No necesita credenciales,
dependencias del producto, red ni acceso a datos.

Reutiliza la guía que ya es dueña de un tema y enlázala desde el
[índice documental](docs/README.md). Identifica como históricos los resultados
antiguos; no los presentes como estado actual. Las capturas y los conteos de
pruebas son evidencia de una ejecución, no garantías permanentes.

No añadas pruebas de producto para un cambio exclusivamente editorial.
Cuando cambia el comportamiento, cubre el caso afectado y un error relevante.
Distingue las pruebas simuladas de la validación con usuarios y datos reales.

## Seguridad y alcance

Nunca incluyas secretos, contraseñas, datos personales, archivos `.env` reales,
logs privados ni exportaciones de bases de datos. Usa datos ficticios y
[SECURITY.md](SECURITY.md) para reportar vulnerabilidades.

Las actualizaciones de documentación, plantillas y CI se revisan separadas del
código desplegable. No requieren migraciones, seeders, regeneración del contrato
ni despliegues. Cualquier cambio de infraestructura o publicación debe tener un
alcance y autorización propios.

## Antes de solicitar revisión

Describe el problema, el resultado, las comprobaciones ejecutadas y sus límites.
Marca como no aplicables las comprobaciones ajenas al cambio, explicando por qué.
No declares CI verde antes de ver los checks del commit del PR.
