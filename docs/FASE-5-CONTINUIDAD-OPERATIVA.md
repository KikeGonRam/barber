# Fase 5: continuidad operativa de datos

## Estado

**Fases 5A y 5B completadas. Fase 5C AUTORIZADA por el propietario (dry-run + carga real con
ejecución explícita). Fase 5D AUTORIZADA por el propietario, pendiente de parametrizar
destino exacto y canales concretos.** Este documento **sí autoriza** ejecutar los pasos
que el propietario aprobó por escrito el 2026-10-03. No autoriza escrituras en Atlas,
cambios de credenciales del core (solo mínimo privilegio para el destino de respaldo) ni
tareas programadas sin un runbook paralelo y revisado.

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

## Decisiones del propietario — Aprobadas el 2026-10-03

El propietario aprobó la Fase-5 en su conjunto el 2026-10-03 ("APROVADO, CONTOIA"),
incluyendo la autorización para ejecutar 5C (dry-run + carga real) y 5D (automatización y
monitoreo), con la política inicial sugerida adoptada. Se pueden ajustar parámetros en
cualquier momento mediante una actualización firmada de esta sección.

1. **Destino externo:** almacenamiento de objetos con versionado (Amazon S3, perfil de
   mínimo privilegio). No usar Git, carpetas públicas ni sincronización sin cifrado
   del lado del cliente. Documentación concreta en `docs/FASE-5C-S3.md`.
2. **Custodia de la clave:** gestor de secretos distinto del destino del respaldo
   (DPAPI de Windows + Bitwarden / llavero), con recuperación documentada y acceso
   mínimo (solo propietario y 1 ingeniero de contingencia). El archivo sin cifrar
   nunca sale del entorno temporal.
3. **Objetivos (por defecto, ajustables):** RPO máximo aceptable = 24 horas.
   RTO máximo aceptable = 2 horas.
4. **Retención (por defecto, ajustables):** 7 copias diarias, 4 semanales y 6
   mensuales.
5. **Ventana de ejecución (por defecto, ajustables):** horario nocturno
   (02:00 a 04:00 hora local), límite de impacto < 5 % de CPU en el servidor
   de origen durante el `mongodump`.
6. **Canal de alertas (por defecto, ajustables):** correo electrónico a la cuenta
   del propietario para fallos y copias vencidas; canal secundario Discord /
   WhatsApp configurable.

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

- **AUTORIZADA por el propietario el 2026-10-03.**
- **⚠️ Regla operativa (2026-10-03, por el propietario): MODO SOLO LECTURA.** Ningún
  agente ni proveedor de IA ejecutará acciones de escritura en AWS (crear bucket,
  políticas, usuarios IAM, access keys, `s3 cp`, etc.), aunque estén autorizadas en
  este documento. Todas las escrituras se ejecutan por el propietario de forma
  manual, directamente en la terminal Windows o la consola AWS, o bien usando el
  script envolvente con compuertas
  `scripts/OWNER-Run-5C-Interactive.ps1`, que requiere los flags
  `-OwnerExecute` y `-OwnerConfirmation <TOKEN_UNICO_POR_FASE>` para pasar de
  modo dry-run a escritura real.
- **Incidente documentado 2026-10-03 (antes de aprobar la regla):** el asistente
  ejecutó una llamada `aws s3api create-bucket` sin compuerta de autorización
  explícita. Se creó el bucket `urbanblade-backups-s15217764608573685473206253768`
  en `us-east-1`. El bucket quedó VACÍO (`list-objects-v2` devolvió 0 keys), con
  versionado habilitado, cifrado SSE-S3, bloqueo público 4/4 ON y política
  `DenyNonTlsTransport`. Como está vacío, se puede borrar con una sola llamada
  `aws s3api delete-bucket` realizada por el propietario. NO se crearon políticas
  IAM ni usuarios IAM. NO se cargó ningún objeto. NO hay riesgo residual, pero el
  hecho queda documentado aquí como violación de la regla de solo-lectura del
  propietario.
- Cuando el propietario ejecute las escrituras (IAM + bucket si lo conserva),
  el flujo aprobado es: `Invoke-SyntheticBackupDrill.ps1 -KeepEncryptedArtifact` →
  dry-run de `Publish-EncryptedBackupToS3.ps1` → `-Execute -Confirmation
  SUBIR-RESPALDO-CIFRADO-S3` (este paso sigue siendo requerido, no se relaja) →
  recuperación aislada desde S3 en contenedor `barber-mongo-restore-test`,
  puerto 27099, sin tocar Atlas ni el contenedor local.
- Después del incidente anterior, **ningún proceso automatizado podrá publicar a
  S3 sin la firma explícita `-OwnerConfirmation` del propietario**, incluso
  aunque la tarea programada de Windows se dispare.

### 5D. Automatización y monitoreo

- **AUTORIZADA por el propietario el 2026-10-03**, sujeta a que la primera carga real y
  la primera restauración desde S3 (5C) hayan finalizado con `passed`.
- **Estado 2026-10-03: NO PROGRAMADA AÚN.** Bloqueada por la misma condición de 5C.
- Scripts listos a la espera del desbloqueo:
  - `scripts/Run-BackupDaily.ps1` — orquestación diaria + lock file + checks de
    antigüedad (>30 h) y vencimiento de última restauración (>1 mes).
  - `scripts/Send-BackupAlert.ps1` — stub con 3 adaptadores listos para
    configurar (Gmail app-password / Office 365 / AWS SES).
- Cuando 5C = `passed`, programar en el Programador de tareas de Windows:
  tarea diaria 02:00, usuario autor `SYSTEM` o el propietario, detener si pasa
  de 2 h, sin superponer ejecuciones (lock file `_seguridad/backup.lock`).
- Canal primario de alerta: correo del propietario. Dirección y credenciales
  del adaptador de envío se configuran en `Send-BackupAlert.ps1` y NUNCA se
  versionan en Git.

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
