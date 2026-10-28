# Verificación del workspace

El orquestador local está en [verificar.ps1](verificar.ps1) y comparte utilidades
mediante [lib/comun.ps1](lib/comun.ps1). Localiza los proyectos desde su ubicación;
`UB_RAIZ` permite indicar otro workspace.

Desde la raíz de barber:

```powershell
.\scripts\verificacion\verificar.ps1 -Proyecto seguridad,documentacion
.\scripts\verificacion\verificar.ps1 -Proyecto barber
.\scripts\verificacion\verificar.ps1 -Proyecto frontend -Rapido
```

Revisa los parámetros del script antes de usar otros grupos. Sin filtro, el alcance
incluye proyectos del workspace; no es necesario ejecutar toda la batería por editar Markdown.

La puerta de seguridad revisa configuración y archivos versionados antes de las
pruebas. Un aviso no equivale a un bloqueo y un grupo omitido no equivale a una prueba
aprobada. Comprueba el resumen de ejecución, no solo el código de salida.

En backend se utiliza exclusivamente [test.ps1](../../test.ps1), con MongoDB local
de pruebas; nunca sustituirlo por Artisan/PHPUnit directo. Las condiciones, dependencias
y cobertura están en [PRUEBAS.md](../../docs/PRUEBAS.md).

Los grupos de frontend pueden ejecutar lint, tipos, build y Playwright según sus
opciones y dependencias; `-Rapido` omite trabajo costoso. Los de Spark y Android
requieren sus propios entornos. Una prueba local no acredita un despliegue.

El detector local de seguridad es una ayuda, no una certificación de ausencia de
secretos. La revisión documental y sus límites están en
[SEGURIDAD_DOCUMENTACION.md](../../docs/SEGURIDAD_DOCUMENTACION.md).
