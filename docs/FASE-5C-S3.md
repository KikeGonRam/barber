# Fase 5C: integración externa preparada para Amazon S3

## Estado

**Preparación implementada; ejecución de dry-run pendiente de un perfil AWS. Carga real
no ejecutada.** No se crearon buckets, usuarios, roles, claves ni objetos en AWS.

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

Al 2026-09-23, AWS CLI v2 está instalada, pero no existe ningún perfil configurado en
el equipo. Por ello se validaron la sintaxis y las guardas localmente, pero no se debe
afirmar que este comando ya fue ejecutado contra una cuenta AWS.

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
