---
name: frontend-form-ux-standards
description: Directrices y checklist para crear o auditar formularios accesibles en Nuxt 4 (UrbanBlade), con modales, validacion por campo, archivos y respuestas 422 alineadas con Laravel.
---

# Estándares de Diseño UI/UX y Formularios (UrbanBlade Nuxt 4)

## Objetivo

Garantizar que los formularios de UrbanBlade sean accesibles, coherentes con los cuatro temas basados en tokens y alineados con los campos y reglas reales del backend Laravel. El frontend vive en el repositorio hermano `../frontend-urban`; revisar allí `AGENTS.md` y el estado Git antes de editar.

---

## 1. Principios Innegociables de UI/UX

1. **Cero `alert()` y `confirm()` del navegador:**
   - Queda terminantemente prohibido usar los métodos nativos del navegador `window.alert()`, `window.confirm()` o `window.prompt()`.
   - Reemplazar por modales de confirmación accesibles integrados en el DOM o toasts/banners de notificación.

2. **Feedback por Campo (Field-Level Validation):**
   - Cuando el backend devuelve un error de validación HTTP 422 (`{ message: string, errors: { [campo]: string[] } }`), el error debe mostrarse **debajo del input infractor específico**, resaltando su borde con el color de error del tema (`text-red-400 border-red-500/50`).
   - Evitar mostrar únicamente un string genérico en la parte inferior del modal.

3. **Accesibilidad (a11y) y Foco:**
   - Todo campo debe tener un `<label :for="id">` explícito, nunca solo un `placeholder`.
   - Asociar mensajes de error mediante `aria-describedby` y marcar inputs inválidos con `aria-invalid="true"`.
   - Mantener el tamaño de los objetivos táctiles (touch targets) en al menos 44×44 px en dispositivos móviles.
   - Permitir envío con la tecla `Enter` y cierre de modales con `Esc` o clic en el backdrop (`@click.self`).

4. **Estados de Carga y Bloqueo de Doble Envío:**
   - Mientras una mutación asíncrona está en vuelo (`saving.value === true`), todos los botones de acción deben deshabilitarse (`:disabled="saving"`) y mostrar un spinner/texto indicador ("Guardando…", "Publicando…").
   - Proteger contra envíos duplicados por clics múltiples.

5. **Previsualización Instantánea de Archivos (Imágenes/Medios):**
   - En campos de subida de fotos (avatar, portafolio, servicios, productos), generar previsualización inmediata del lado cliente usando `URL.createObjectURL(file)` antes de la subida.
   - Validar tipo MIME y tamaño máximo en el cliente antes de disparar la petición de red (ahorra ancho de banda).

---

## 2. Matriz de Correspondencia de Formularios vs Backend

| Pantalla Frontend | Endpoint Backend | FormRequest / Validador | Campos Requeridos / Soportados |
| :--- | :--- | :--- | :--- |
| **`pages/profile.vue`** (Perfil de Usuario) | `PUT /api/v1/profile`<br>`PUT /api/v1/profile/password`<br>`DELETE /api/v1/profile` | `ProfileController` | `name`, `email`, `telefono`, `fecha_nacimiento`<br>Contraseña: `current_password`, `password`, `password_confirmation`<br>Eliminar: `password` |
| **`pages/barber/profile.vue`** (Perfil Barbero) | `POST /api/v1/barber/profile` | `UpdateBarberProfileRequest` | `especialidades` (string), `descripcion` (string), `foto` (file max 4MB) |
| **`pages/barber/schedule.vue`** (Horario Barbero) | `PUT /api/v1/barber/schedule` | `UpdateBarberScheduleRequest` | `schedules`: `[{ day_of_week, start_time, end_time, is_active }]` |
| **`pages/barber/portfolio.vue`** (Portafolio Barbero) | `POST /api/v1/barber/works` | `StoreWorkRequest` | `title` (string max 255), `description` (string max 2000), `media[]` (files max 50MB, img/video) |
| **`pages/services/index.vue`** (Catálogo Servicios) | `POST /api/v1/services/manage`<br>`PUT /api/v1/services/manage/{slug}` | `StoreServiceRequest`<br>`UpdateServiceRequest` | `nombre` (max 120), `categoria` (max 100), `precio` (numeric min 0), `duracion_min` (5-600), `descripcion`, `activo` (bool), `imagen` (file max 2MB) |
| **`pages/inventory/products/index.vue`** (Productos) | `POST /api/v1/inventory/products`<br>`PUT /api/v1/inventory/products/{id}` | `StoreProductRequest`<br>`UpdateProductRequest` | `nombre`, `categoria`, `descripcion`, `precio_compra`, `precio_venta`, `stock_actual`, `stock_minimo`, `tipo`, `imagen` (file max 2MB), `activo` (bool) |
| **`pages/inventory/movements/index.vue`** (Movimientos) | `POST /api/v1/inventory/movements` | `StoreInventoryMovementRequest` | `product_id`, `tipo` (entrada/salida), `cantidad` (min 1), `motivo` |
| **`pages/clients/index.vue`** (Gestión Clientes) | `POST /api/v1/admin/clients`<br>`PUT /api/v1/admin/clients/{slug}` | `ClientAdminController` | `name`, `email`, `telefono`, `password` (solo create, min 8), `fecha_nacimiento` |
| **`pages/users/index.vue`** (Gestión Cuentas) | `POST /api/v1/users`<br>`PUT /api/v1/users/{id}` | `StoreUserRequest`<br>`UpdateUserRequest` | `name`, `email`, `role`, `password` (create: required confirmed; edit: optional confirmed) |
| **`pages/settings/index.vue`** (Configuración) | `PUT /api/v1/settings` | `UpdateBarbershopSettingRequest` | `nombre`, `direccion`, `telefono`, `horario_apertura`, `horario_cierre`, `politica_cancelacion`, redes sociales, datos bancarios |

---

## 3. Guía de Implementación Paso a Paso

1. **Manejo de Errores de API:**
   ```ts
   const fieldErrors = ref<Record<string, string[]>>({})
   const generalError = ref('')

   try {
     await apiFetch('/...', { method: 'POST', body: form })
   } catch (err: unknown) {
     const data = (err as { data?: { message?: string, errors?: Record<string, string[]> } })?.data
     if (data?.errors) {
       fieldErrors.value = data.errors
     }
     generalError.value = data?.message ?? 'Ocurrió un error inesperado.'
   }
   ```

2. **Renderizado de Campo con Error Asociado:**
   ```html
   <div>
     <label :for="`input-${name}`" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted">
       {{ label }}
     </label>
     <input
       :id="`input-${name}`"
       v-model="value"
       :class="[
         'ui-input w-full',
         fieldErrors[name] ? 'border-red-500/60 focus:border-red-500' : 'border-line focus:border-gold'
       ]"
       :aria-invalid="!!fieldErrors[name]"
       :aria-describedby="fieldErrors[name] ? `error-${name}` : undefined"
     >
     <p v-if="fieldErrors[name]" :id="`error-${name}`" class="mt-1 text-xs text-red-400">
       {{ fieldErrors[name][0] }}
     </p>
   </div>
   ```

3. **Confirmaciones Visuales Seguras:**
   - Usar un modal tipo `<UiConfirmModal>` que solicite confirmación con texto claro, botón destructivo en rojo y botón cancelar con foco por defecto.

4. **Temas y contratos:**
   - Usar tokens semánticos existentes (`bg-main`, `bg-card`, `text-ink`, `text-muted`, `border-line`, `bg-gold`) para conservar los cuatro temas.
   - No confiar en precios o importes enviados por el cliente; el backend relee siempre la fuente de verdad.
   - No cambiar rutas, nombres de campos ni formas de respuesta sin revisar controladores, FormRequest, documentación Scribe y consumidores.
   - Revocar cada URL creada con `URL.createObjectURL` al reemplazar el archivo o desmontar el componente.

## 4. Verificación antes de entregar

1. Comparar el formulario contra las rutas, controladores y FormRequest actuales del backend.
2. Buscar diálogos nativos con `rg -n "window\\.(alert|confirm|prompt)" app`.
3. Ejecutar `npm.cmd run lint` y `npm.cmd run build` dentro de `../frontend-urban`.
4. Probar teclado, cierre con Escape, bloqueo de doble envío, error 422, éxito y los cuatro temas en móvil y escritorio.
5. Ejecutar `git diff --check` y confirmar únicamente archivos propios. No subir si una comprobación relevante falla.
