---
name: urbanblade-profile-security
description: Reglas de autorización y privacidad para perfiles y cuentas.
---

# Perfiles y seguridad de cuenta

- Comprobar rol y propiedad en servidor; users.role_id contiene los roles de MongoDB.
- Omitir secretos y datos personales ajenos en Resources y respuestas.
- Validar tipo, tamaño y manejo de errores de fotos. No asumir que el almacenamiento
  local ofrece la privacidad de un bucket privado.
- Cambios de API requieren ejemplos y pruebas de autorización adecuados.
- En backend probar exclusivamente con .\test.ps1, sin Atlas ni datos reales.
- Coordinar clientes mediante el contrato, sin publicar automáticamente.
Consulta docs/ACCESOS.md y docs/PRUEBAS.md en barber.
Esta guía mantiene reglas vigentes; no es un roadmap pendiente ni una autorización
para cambiar cuentas. Mantén sincronizadas las copias .claude/.agents.
