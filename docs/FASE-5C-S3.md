# Fase 5C: integración externa preparada para Amazon S3

## Estado

**AWS CLI v2 DISPONIBLE (2.36.48+).** ✅ Instalada 2026-10-03 vía MSI oficial.
Refresco PATH en scripts nuevos:
`$env:PATH = [Environment]::GetEnvironmentVariable('PATH','Machine') + ';' + [Environment]::GetEnvironmentVariable('PATH','User')`

### Regla operativa obligatoria (firmada por el propietario 2026-10-03)

> **MODO SOLO LECTURA a partir de 2026-10-03.**
> Ningún asistente, agente ni proveedor de IA ejecutará comandos de escritura
> sobre AWS. Todo paso que cree, modifique o elimine recursos en AWS (bucket,
> políticas, usuarios IAM, access keys, objetos S3), lo ejecuta ÚNICAMENTE el
> propietario en su terminal NUEVA (fuera del sandbox), directamente o usando
> el script de compuerta doble `scripts/OWNER-Run-5C-Interactive.ps1`.
>
> **Incidente documentado en FASE-5-CONTINUIDAD-OPERATIVA.md §5C:**
> antes de firmarse esta regla, una llamada `aws s3api create-bucket` se lanzó
> sin autorización y creó el bucket vacío
> `urbanblade-backups-s15217764608573685473206253768` en us-east-1.
> Queda a elección del propietario conservarlo (recomendado, ya está endurecido:
> versionado, bloqueo público 4/4, TLS-only, SSE-S3 + bucket key) o borrarlo
> con una sola llamada `aws s3api delete-bucket` realizada por él mismo.
> Ninguna política/usuario IAM se tocó. Ningún objeto fue subido.

## Decisiones aprobadas

- Destino: Amazon S3.
- Respaldo diario.
- Retención objetivo: 7 diarios, 4 semanales y 6 mensuales.
- RPO objetivo: 24 horas.
- RTO objetivo: 4 horas.
- Alertas futuras: correo del administrador, sin registrar una dirección real en Git.
- Todo archivo se cifra localmente antes de abandonar el equipo.

## Diseño de acceso

Usar dos identidades diferentes:

1. **Writer:** solo `s3:PutObject` dentro de
   `urbanblade/barber-db/*`. No puede listar, leer ni borrar respaldos.
2. **Restore:** puede listar ese prefijo y leer objetos/versiones. No puede publicar ni
   borrar.

Las plantillas están en:

- `docs/aws/s3-backup-writer-policy.example.json`
- `docs/aws/s3-backup-restore-policy.example.json`

Se debe reemplazar `REEMPLAZAR_BUCKET` antes de usarlas. No añadir identificadores de
cuenta, claves ni nombres privados al repositorio.

## Requisitos del bucket

- Acceso público bloqueado.
- Versionado habilitado.
- Cifrado predeterminado de S3 habilitado; el script solicita además SSE-S3 (`AES256`).
- Bucket dedicado a respaldos, sin hosting web ni ACL públicas.
- Política que deniegue transporte sin TLS.
- Object Lock debe evaluarse por separado: depende de versionado y, una vez habilitado,
  no puede deshabilitarse. No está automatizado en esta fase.

Después de habilitar versionado por primera vez, esperar el periodo recomendado por AWS
antes de escribir objetos. Configurar las reglas de ciclo de vida desde la consola o
infraestructura administrada, no desde el script de carga.

## Dry-run

El publicador valida antes de invocar AWS:

- Extensión `.ubenc`.
- Manifiesto con `result: passed`.
- Coincidencia de nombre y SHA-256.
- Formato del `run_id`.
- Bucket, región, perfil y prefijo explícitos.

Sin `-Execute`, AWS CLI recibe `--dryrun` y no escribe objetos:

```powershell
.\scripts\Publish-EncryptedBackupToS3.ps1 `
  -Bucket "NOMBRE-DEL-BUCKET" `
  -Region "REGION-AWS" `
  -Profile "urbanblade-backup-writer" `
  -EncryptedArchive "RUTA-AL-ARCHIVO.archive.gz.ubenc" `
  -Manifest "RUTA-AL-MANIFIESTO.manifest.json"
```

Al 2026-10-03, AWS CLI v2 NO está instalada en el equipo; se detectó
explícitamente. Por ello se validaron únicamente la sintaxis y las guardas
locales del publicador, no la conectividad S3.

Para habilitar S3 se requieren 4 acciones del propietario, en orden:

1. Ejecutar el MSI oficial de AWS CLI v2 y cerrar terminales.
2. Consola AWS S3: crear bucket dedicado con bloqueo público + versionado +
   cifrado predeterminado SSE-S3. Opcional: política `DenyNonTlsTransport`.
3. Consola IAM: 2 políticas + 2 usuarios (`urbanblade-backup-writer` con
   `s3:PutObject` únicamente, `urbanblade-backup-restore` con
   `s3:ListBucket`/`s3:GetObject`/`s3:GetObjectVersion` únicamente), cada una
   con su propio par de access keys. Las plantillas listas para copiar están
   en `docs/aws/s3-backup-writer-policy.example.json` y
   `docs/aws/s3-backup-restore-policy.example.json` (sustituir `REEMPLAZAR_BUCKET`
   por el nombre real).
4. Configurar 2 perfiles AWS locales:
   - `aws configure --profile urbanblade-backup-writer`
   - `aws configure --profile urbanblade-backup-restore`

Una vez completados 1–4, el flujo es:
`Invoke-SyntheticBackupDrill.ps1 -KeepEncryptedArtifact` →
`Publish-EncryptedBackupToS3.ps1` (dry-run) → `-Execute -Confirmation ...` →
restauración aislada desde S3 → programar `Run-BackupDaily.ps1` en el
Programador de tareas.

## Compuerta para la carga real

La carga real exige simultáneamente:

- `-Execute`.
- `-Confirmation SUBIR-RESPALDO-CIFRADO-S3`.
- Manifiesto con `environment: atlas-production`.
- Hash y nombre válidos.
- Perfil AWS configurado fuera del repositorio.

Aunque se cumplan esas guardas, ningún proveedor de IA debe ejecutar la carga sin una
instrucción nueva y explícita del propietario que identifique bucket, región y perfil.

## Recuperación

La prueba posterior deberá descargar ambos objetos con la identidad Restore, comprobar
el SHA-256, descifrar localmente y restaurar únicamente en un entorno aislado. No se
considerará terminada la Fase 5C hasta completar esa recuperación desde S3.

## Referencias oficiales

- AWS CLI `s3 cp`: https://docs.aws.amazon.com/cli/latest/reference/s3/cp.html
- Versionado de S3:
  https://docs.aws.amazon.com/AmazonS3/latest/userguide/manage-versioning-examples.html
- Object Lock:
  https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lock-configure.html
- Seguridad de S3:
  https://docs.aws.amazon.com/AmazonS3/latest/userguide/security-best-practices.html
