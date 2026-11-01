---
name: qa-backend
description: Cómo probar el backend Laravel de UrbanBlade: suites disponibles, el único comando permitido para ejecutarlas, qué escribir y qué no tocar. Úsalo antes de ejecutar o escribir pruebas en el repo barber.
---
<!-- Generado desde urbanblade-equipo. No editar aquí: cambia el origen y vuelve a sincronizar. -->

# QA del backend (barber)

Fuente de verdad: `docs/referencia/PRUEBAS.md` del repo. Si algo de aquí contradice a ese archivo, gana el archivo.

## Comando permitido
```powershell
.\test.ps1                            # todas las suites
.\test.ps1 --testsuite=UT             # unitarias
.\test.ps1 --testsuite=SIT            # integración de sistema
.\test.ps1 --testsuite=UAT            # aceptación de usuario
.\test.ps1 --testsuite=OAT            # aceptación operativa
.\test.ps1 --filter=NombreDeLaPrueba  # una prueba concreta
.\test.ps1 --compact
```
`test.ps1` limpia cachés y fuerza la conexión a `mongo-test` y la base `barber_db_test`. **No uses** `php artisan test` ni `vendor/bin/phpunit` directamente: pueden heredar la conexión operativa. Antes de correr, comprueba que el contenedor de pruebas esté disponible.

## Dónde escribir
`tests/UT`, `tests/SIT`, `tests/UAT` y `tests/OAT`, según el alcance. Un cambio de comportamiento lleva su prueba en la suite que corresponde; una regla de dominio (citas, pagos, inventario) lleva una prueba en UT o SIT.

## Qué probar con más cuidado
Transiciones de estado de citas, cobros y devoluciones, inventario y movimientos, roles y propiedad del recurso (que un cliente no vea datos de otro), entradas inválidas (422), doble envío y concurrencia.

## Límites
- No uses datos reales ni Atlas. Las pruebas no sustituyen la revisión de seguridad ni un ensayo de restauración.
- No marques pruebas como omitidas ni las debilites para que pasen: si una prueba falla, el defecto va al agente de desarrollo.
- El CI corre además Pint, Larastan, cobertura y análisis de seguridad; si tu cambio los afecta, repórtalo.
