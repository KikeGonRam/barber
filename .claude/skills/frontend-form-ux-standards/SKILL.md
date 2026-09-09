---
name: frontend-form-ux-standards
description: Directrices y checklist para crear o auditar formularios accesibles en Nuxt 4 (UrbanBlade), con modales, validacion por campo, archivos y respuestas 422 alineadas con Laravel.
---

# Formularios Nuxt de UrbanBlade

El frontend vive en `../frontend-urban`. Antes de editar, leer su `AGENTS.md`, revisar el estado Git y contrastar el formulario con las rutas, controladores y FormRequest reales de este backend.

## Reglas obligatorias

- No usar `window.alert`, `window.confirm` ni `window.prompt`; usar diálogos, banners o toasts integrados.
- Cada control tiene `id` y `label`. Asociar el error mediante `aria-describedby` y `aria-invalid`.
- Mapear el HTTP 422 `{ message, errors }` debajo de cada campo. Usar `role="alert"` para errores y `role="status"` para éxito.
- Un modal requiere `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, cierre con Escape/backdrop y objetivos táctiles de al menos 44 px.
- Durante una mutación, deshabilitar todas las acciones, mostrar progreso e impedir doble envío o cierre accidental.
- Validar MIME y tamaño de archivos antes de enviarlos; revocar todas las URL de previsualización.
- Conservar los cuatro temas usando tokens semánticos existentes; no fijar colores para un solo tema.
- No confiar en precios o importes del cliente. No cambiar contratos sin revisar Scribe y todos los consumidores.

## Patrón de errores

```ts
const fieldErrors = ref<Record<string, string[]>>({})

try {
  await apiFetch('/resource', { method: 'POST', body: form })
} catch (error: unknown) {
  const data = (error as { data?: { message?: string, errors?: Record<string, string[]> } })?.data
  fieldErrors.value = data?.errors ?? {}
  formError.value = data?.message ?? 'No se pudo completar la operación.'
}
```

## Verificación

1. Ejecutar `rg -n "window\\.(alert|confirm|prompt)" app`.
2. Ejecutar `npm.cmd run lint`, `npm.cmd run build` y `git diff --check` en `../frontend-urban`.
3. Probar teclado, error 422, éxito, móvil, escritorio y los cuatro temas.
4. Confirmar solo archivos propios; no subir con comprobaciones relevantes fallidas.
