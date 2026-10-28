# UrbanBlade · instrucciones del backend

API JSON Laravel consumida por Nuxt y Android. Trabaja en este repositorio
independiente, no en la carpeta contenedora ni en un checkout hermano por accidente.
La petición y los límites actuales del propietario prevalecen sobre planes históricos.

## Leer antes de actuar

- [Índice documental](docs/README.md).
- [Arquitectura](docs/DOCUMENTACION_TECNICA.md).
- [Pruebas](docs/PRUEBAS.md).
- [Decisiones](docs/DECISIONES.md) y [ADR de datos](docs/ADR-001-ARQUITECTURA-DE-DATOS.md).
- [Seguridad](SECURITY.md) y [credenciales](docs/PLAN_ROTACION_CREDENCIALES.md).

Las instrucciones específicas de seguridad están en
[urbanblade-guardrails](.claude/skills/urbanblade-guardrails/SKILL.md).
Las copias de skills de `.claude` y `.agents` son espejos para distintos
clientes: mantenerlas coherentes, sin inventar dos políticas.

## Entorno y límites

- PHP/Laravel: consultar Composer, Dockerfiles y CI; no fijar versiones por una nota vieja.
- Autenticación por token Bearer propio en mobile_api_tokens; no Sanctum.
- Las pantallas viven en frontend-urban. Laravel conserva correos, PDF, errores,
  redirecciones y documentación generada.
- MongoDB operativo, analítica, desarrollo y pruebas tienen conexiones/alcances
  distintos. No imprimir URIs ni copiar un entorno operativo.
- Pagos e inventario requieren replica set y reglas del servidor.
- No ejecutar migraciones, seeders, limpieza de colecciones ni borrado de volúmenes
  como preparación de una tarea documental.

## Comandos de comprobación

```powershell
git status --short --branch
python scripts/check_docs.py
git diff --check
```

Para PHPUnit local, exclusivamente `.\test.ps1`, tras comprobar el montaje de
barber-app y Mongo de pruebas. Consulta PRUEBAS.md para los controles aplicables.
Una tarea editorial no requiere repetir toda la suite de negocio si no cambia su código.

## Seguridad y datos

No imprimir ni versionar contraseñas, claves, tokens, cookies, archivos de servicio,
dumps, comprobantes o logs privados. Los ejemplos deben ser ficticios.
ACCESOS.md documenta procesos de acceso, no credenciales ni cuentas actuales.
Ante una coincidencia, registrar ubicación y tipo sin el valor; no probarla contra
un proveedor ni rotarla sin autorización y plan.

## Cambios y entrega

- Empezar por estado, rama, raíz y cambios preexistentes.
- Trabajar en una rama desde origin/main, preservar trabajo ajeno y usar staging selectivo.
- Commits/PR en español y autoría humana configurada; sin atribuciones de herramientas.
- No crear commit, push ni PR de un cambio nuevo sin orden de publicación del propietario.
- Main recibe cambios por PR y checks verdes; sin bypass, push forzado ni borrado de ramas.
- Subir, fusionar y desplegar tienen alcances distintos. Revisar integraciones externas:
  incluso Markdown puede activar Vercel u otro proveedor.
- Las autorizaciones citadas en Markdown antiguo no conceden permisos nuevos.
- En el mantenimiento documental actual no se despliega ni se modifica producción.
