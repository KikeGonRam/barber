# Fase 5: continuidad operativa de datos

## Estado

**Fases 5A y 5B completadas. Fase 5C preparada; ejecución de dry-run, carga y
recuperación pendientes. Fase 5D no autorizada.** Este documento no autoriza escrituras
en Atlas, cambios de credenciales, cargas a servicios externos ni tareas programadas.

## Objetivo

Disponer de respaldos operativos cifrados, restaurables y monitoreados sin modificar
la separación ya aceptada entre `barber_db`, `urbanblade_analytics`, Pulse, Redis,
`mongo-dev` y `mongo-test`.

## Alcance y exclusiones

Incluye:

- Respaldo BSON de la base operativa con metadatos e índices.
- Copia externa cifrada con acceso restringido.
- Política de frecuencia, retención y eliminación segura.
- Restauración ensayada en un MongoDB aislado.
- Evidencia y alertas sin exponer datos ni secretos.

No incluye:

- Renombrar `barber_db` o dividirla por colección.
- Crear otro contenedor equivalente a `mongo-dev` o `mongo-test`.
- Respaldar Redis como fuente de verdad.
- Modificar, rotar o eliminar la cuenta `luis`.
- Usar datos reales en desarrollo o pruebas automatizadas.

## Decisiones pendientes del propietario

Antes de implementar debe aprobarse por escrito:

1. **Destino externo:** almacenamiento de objetos con versionado o repositorio de
   respaldos administrado. No usar Git, carpetas públicas ni sincronización sin
   cifrado del lado del cliente.
2. **Custodia de la clave:** gestor de secretos distinto del destino del respaldo,
   con recuperación documentada y acceso mínimo.
3. **Objetivos:** RPO máximo aceptable y RTO máximo aceptable.
4. **Retención:** cantidad de copias diarias, semanales y mensuales.
5. **Ventana de ejecución:** horario y límite de impacto sobre producción.
6. **Canal de alertas:** destinatarios para fallos y copias vencidas.

## Diseño recomendado

Flujo propuesto:

`Atlas/barber_db -> mongodump archive -> cifrado autenticado -> hash SHA-256 -> destino externo`

Cada ejecución debe generar un manifiesto sin secretos ni datos personales con:

- Identificador y fecha UTC.
- Base de origen y ambiente, sin URI ni credenciales.
- Tamaño del archivo cifrado y SHA-256.
- Versiones de las herramientas.
- Resultado, duración y fecha de expiración.
- Referencia de la última restauración validada.

El archivo sin cifrar debe existir solamente durante la ejecución en una ubicación
temporal controlada y eliminarse al finalizar. Los logs nunca deben imprimir URI,
contraseñas, documentos ni contenido del respaldo.

## Política inicial sugerida

Esta política es una propuesta y debe ajustarse a los RPO/RTO aprobados:

- Respaldo diario cifrado.
- Siete copias diarias, cuatro semanales y seis mensuales.
- Versionado o bloqueo contra borrado accidental en el destino.
- Alerta inmediata si falla la ejecución.
- Alerta si la última copia válida supera 30 horas.
- Restauración de ensayo mensual en un entorno aislado.
- Revisión trimestral de accesos y recuperación de la clave.

## Prueba obligatoria de restauración

Una carga exitosa no demuestra que el respaldo sea recuperable. El ensayo debe:

1. Descargar una copia sin reemplazar archivos locales existentes.
2. Verificar el hash antes de descifrar.
3. Descifrar en una ubicación temporal protegida.
4. Restaurar en un MongoDB aislado que no apunte a Atlas ni a `mongo-dev`.
5. Comparar colecciones, documentos e índices contra el manifiesto de referencia.
6. Ejecutar comprobaciones de integridad sin mostrar datos personales.
7. Registrar duración, resultado y diferencias.
8. Destruir de forma segura el entorno temporal al terminar.

El ensayo se considera aprobado únicamente si no faltan colecciones ni índices, los
conteos coinciden con la referencia esperada y el tiempo total cumple el RTO.

## Implementación por compuertas

### 5A. Documentación

- Aprobar destino, cifrado, RPO, RTO, retención y responsables.
- Definir formato del manifiesto y procedimiento de recuperación de clave.
- Sin conexión externa y sin manipulación de datos reales.

### 5B. Ensayo local no sensible

- Ejecutar `scripts/Invoke-SyntheticBackupDrill.ps1` usando exclusivamente
  `barber-mongo-test`. El script rechaza otros contenedores, crea bases temporales con
  prefijo `urbanblade_backup_drill_`, usa datos sintéticos y elimina esas bases al
  terminar.
- Verificar cifrado, hash, manifiesto, retención y restauración aislada.
- Validar que fallan de forma segura ante credenciales o destinos incorrectos.

Ejemplo (no conserva el artefacto cifrado):

```powershell
.\scripts\Invoke-SyntheticBackupDrill.ps1
```

Para inspeccionar el artefacto cifrado después de una ejecución aprobada:

```powershell
.\scripts\Invoke-SyntheticBackupDrill.ps1 -KeepEncryptedArtifact
```

La frase se solicita de forma interactiva y no se escribe en el manifiesto. La salida
vive en `storage/app/backup-drills/`, ignorada por Git. El manifiesto solo registra
metadatos, hash, conteos e índices; nunca documentos ni credenciales.

Validación completada el 2026-09-23:

- Sintaxis PowerShell analizada sin errores.
- La guarda rechaza un contenedor distinto de `barber-mongo-test`.
- La carpeta de salida está limitada a `storage/app/backup-drills/`.
- Ensayo real local `20260923T182413Z-a8716c51`: `mongodump`, cifrado AES-256-GCM,
  descifrado y `mongorestore` completados en `barber-mongo-test`.
- Resultado: 5 documentos sintéticos restaurados sin fallos, distribuidos en 2
  colecciones. Se verificaron 2 índices en `clients` y 2 en `appointments`, contando
  el índice `_id_` de cada colección.
- El manifiesto local terminó con `result: passed`. El artefacto cifrado y los archivos
  planos se eliminaron al finalizar porque no se solicitó conservarlos.
- Se comprobó que no permanecen bases con prefijo `urbanblade_backup_drill_` después
  de la limpieza.

### 5C. Integración externa controlada

- La preparación y el procedimiento de dry-run están documentados en
  `docs/FASE-5C-S3.md`; su ejecución está pendiente de configurar un perfil AWS.
- El script `scripts/Publish-EncryptedBackupToS3.ps1` valida artefacto y manifiesto y
  funciona en dry-run por defecto.
- La carga real y su recuperación requieren una nueva autorización explícita.
- Configurar credenciales de mínimo privilegio fuera del repositorio.
- Cargar únicamente el artefacto cifrado y comprobar su recuperación.

### 5D. Automatización y monitoreo

- Requiere nueva autorización explícita.
- Programar la tarea sin superponer ejecuciones.
- Alertar por fallo, antigüedad, corrupción o restauración vencida.
- Documentar pausa, reversión y rotación de credenciales.

## Criterios de terminación

La Fase 5 se completa cuando existen evidencias de:

- Una copia externa cifrada creada con mínimo privilegio.
- Una restauración aislada exitosa desde esa copia.
- Conteos e índices verificados sin exponer información sensible.
- RPO y RTO medidos y aceptados.
- Retención y alertas verificadas.
- Runbook de recuperación utilizable por una persona distinta al autor.

## Reversión

La integración debe poder desactivarse sin modificar Atlas ni las aplicaciones. La
reversión consiste en detener la programación, revocar únicamente la credencial del
destino externo y conservar la última copia válida durante el periodo aprobado. No se
eliminan respaldos ni claves durante una reversión sin autorización del propietario.

## Reglas Git

Ningún proveedor de IA ejecuta commit, push, merge, rebase ni publica PR. Debe entregar
al usuario el resumen, las validaciones y un comando de commit limitado a los archivos
de esta fase. El usuario realiza el commit y el push.
