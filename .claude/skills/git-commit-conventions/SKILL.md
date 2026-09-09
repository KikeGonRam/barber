---
name: git-commit-conventions
description: "Convención de mensajes de commit para UrbanBlade: en español, con un solo autor humano. Usar antes de crear cualquier commit en barber o frontend-urban."
---

# Convención de commits — UrbanBlade

## Idioma

Todos los mensajes de commit van en **español**, tanto el resumen (primera
línea) como el cuerpo. Términos técnicos sin traducción natural (nombres de
archivos, comandos, clases, métodos, mensajes de error citados tal cual) se
dejan en su idioma original.

## Autoría

Los commits de este repositorio deben quedar a nombre de un solo autor
humano: el dueño del proyecto (`git config user.name`/`user.email` ya
configurado localmente como `KikeGonRam`). No agregar líneas de coautoría de
herramientas de IA (`Co-Authored-By: ...`, `Generated with ...`, badges de
"AI-assisted", o similares) al final del mensaje.

**Excepción conocida, no evitable**: las sesiones de Claude Code (Anthropic)
están configuradas por su propia plataforma anfitriona para añadir
automáticamente una línea `Co-Authored-By: Claude ...` a cada commit que
crean — esto lo decide el host de Claude Code, no el contenido de este
archivo, así que ninguna instrucción de repo puede desactivarlo. Si aparece
esa línea en el historial, es de una sesión de Claude Code respetando una
regla de su propia plataforma, no un error de esta convención. No hace falta
"corregirla" reescribiendo commits ya publicados en `main` — reescribir
historia compartida es una operación destructiva que solo debe hacerse si el
dueño del proyecto lo pide explícitamente.

## Formato

- Primera línea: resumen corto en imperativo (`Corrige`, `Agrega`,
  `Actualiza`, `Elimina` — no `Corrigiendo`/`Corregido`/`Agregando`), menos
  de 72 caracteres, sin punto final.
- Cuerpo (si el cambio necesita contexto): explica el *por qué*, no solo el
  *qué* — el diff ya muestra el qué. Útil para decisiones no obvias, bugs
  encontrados durante el cambio, o hallazgos que quedan pendientes a
  propósito.
- Nunca incluir credenciales, tokens, contraseñas ni otros datos sensibles en
  el mensaje — igual que en cualquier otro archivo versionado de este repo
  (ver guardrail de secretos en `urbanblade-guardrails`).

## Ejemplo

```
Corrige el gate de perfil incompleto en login y registro

login() y register() mandaban siempre a /dashboard sin mirar
profile_complete. Como el registro por correo nunca pide teléfono ni fecha
de nacimiento, esos usuarios entraban con el perfil incompleto y nada
volvía a pedírselo -- solo el callback de Google respetaba el gate.
```
