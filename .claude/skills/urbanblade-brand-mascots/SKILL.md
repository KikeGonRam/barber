---
name: urbanblade-brand-mascots
description: Crea y mantiene exclusivamente la identidad visual, mascotas y material promocional de UrbanBlade en barber y frontend-urban, sin modificar funciones, datos ni contratos del producto.
---

# Identidad y mascotas de UrbanBlade

Usa esta skill solamente para trabajo de marca: logotipo, mascotas, poses y estados visuales, ilustraciones, videos promocionales, capturas, material para README y la presentacion visual de esos recursos.

## Alcance exclusivo

Mientras esta skill este activa, no trabajes en ninguna otra area. Puedes:

- crear o ajustar recursos de Nava, Bladebot y Bruno;
- preparar variantes visuales para `welcome`, `waiting`, `success`, `empty`, `forbidden`, `lost` y `error`;
- optimizar PNG, SVG, WebP, GIF o MP4 sin cambiar el significado de la interfaz;
- mejorar la pagina de marca, los componentes que solo presentan mascotas y las secciones visuales de los README;
- documentar reglas de identidad sin duplicar credenciales ni documentacion funcional.

No puedes modificar API, rutas, controladores, modelos, migraciones, base de datos, autenticacion, permisos, pagos, inventario, notificaciones, contratos, configuracion de despliegue ni logica de negocio. Tampoco puedes crear una funcion nueva bajo el pretexto de una mejora visual. Si el pedido exige algo de esa lista, detente, explica que queda fuera de esta skill y solicita autorizacion explicita para cambiar de alcance.

## Personajes canonicos

- **Nava, guia de experiencia:** exploracion, cercania y confianza; rutas perdidas y estados vacios.
- **Bladebot, asistente de precision:** tecnologia util, eficiencia y servicio; bienvenida, espera, exito y concierge.
- **Bruno, guardian del conocimiento:** analisis, memoria y criterio; errores, reportes y ayuda tecnica.

No agregues otra mascota solo por variedad. Primero comprueba que existe una funcion narrativa permanente que ninguno de los tres personajes cubre y presenta la propuesta al usuario antes de generarla o integrarla.

## Fuentes de verdad y rutas permitidas

Lee primero `frontend-urban/docs/IDENTITY_MASCOT_SIDEBAR_PLAN.md` y conserva el estilo observado en los recursos existentes.

Recursos principales:

- ambos repos: `public/images/mascots/` y `public/video/`;
- frontend: `app/components/brand/`, `app/composables/useBrandMascots.ts` y `app/pages/brand/index.vue`;
- backend: `resources/views/components/brand-mark.blade.php`;
- documentacion visual: `README.md`, `docs/IDENTITY_MASCOT_SIDEBAR_PLAN.md` y archivos de QA visual relacionados.

Editar un consumidor fuera de esas rutas requiere que el cambio sea estrictamente presentacional y directamente necesario para mostrar un recurso de marca. Antes de hacerlo, revisa el diff y confirma que no cambia eventos, peticiones, permisos, navegacion ni datos.

## Reglas de ejecucion

1. Revisa `git status`, recursos existentes y referencias antes de editar; conserva cambios ajenos.
2. Reutiliza personajes y componentes actuales. No generes copias casi identicas ni renombres archivos consumidos sin actualizar y verificar todas sus referencias.
3. Mantiene transparencia, proporciones, estilo, paleta y rasgos reconocibles entre poses.
4. Las mascotas no deben cubrir contenido ni controles. Conserva texto alternativo, contraste, carga diferida cuando aplique y `prefers-reduced-motion`.
5. Usa rutas relativas en README y recursos publicos. Para GitHub, enlaza MP4 en vez de asumir que se reproducira incrustado.
6. No ejecutes pruebas de Laravel para un cambio solo documental o de assets. Si se modifica presentacion Nuxt, usa las validaciones frontend relevantes; no reinicies servidores sin permiso.
7. No hagas commit, push, despliegue ni publiques material sin solicitud explicita.

## Entrega

Reporta que recursos cambiaron, donde se muestran, que validacion se completo y cualquier revision visual pendiente. Declara de forma explicita que no se modifico funcionalidad del producto.
