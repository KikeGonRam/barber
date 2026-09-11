---
name: urbanblade-market-web
description: Implementa en frontend-urban capacidades vendibles de UrbanBlade para barberías, basadas en el benchmark de reservas, operación y fidelización. Úsala al construir o mejorar UX web/PWA; no la uses para la futura app nativa.
---

# UrbanBlade market web

## Objetivo

Convertir `frontend-urban` en una experiencia web/PWA vendible para barberías mexicanas: reserva pública, operación diaria y retención. El resultado debe resolver una necesidad comprobable antes de añadir otra pantalla decorativa.

## Alcance y límites

- La UI real vive en `C:\Users\luis1\Documents\UrbanBlade\frontend-urban`; `barber` es una API JSON. Inspecciona ambos repositorios, pero no cambies el backend salvo que el flujo requiera un contrato aditivo explícito.
- No crear un marketplace nacional, una app nativa ni una nueva identidad visual como parte de una mejora puntual.
- No desplegar, hacer push, cambiar secretos, migrar, sembrar ni modificar datos reales sin autorización expresa.
- Conserva los cuatro temas por tokens, navegación desktop/móvil separada, accesibilidad, estados de carga/error/vacío y trabajo local ajeno. No reinicies el servidor de desarrollo si no se pidió.
- Si se toca API, permisos, pagos, inventario, pruebas o documentación del backend, leer primero `urbanblade-guardrails` y las skills especializadas aplicables. El contrato `/api/v1` es también para Android: preferir adiciones versionadas/documentadas.

## Mapa de producto priorizado

### P0: producto que una barbería puede vender hoy

1. Perfil público de negocio: galería, ubicación, redes, servicios, precio, duración, equipo, reseñas y políticas.
2. Reserva breve: servicio → profesional opcional → horario real → confirmación. Debe funcionar desde enlace, QR y redes sin forzar una cuenta antes de mostrar disponibilidad.
3. Agenda operativa: día/semana por profesional, estados de cita, check-in, iniciar, cobrar, reprogramar, cancelar y no-show.
4. Cliente 360: historial, preferencias, notas y referencias visuales, siempre bajo autorización por rol.
5. Cobro y cierre: servicio, extras, productos, descuentos, propina, método de pago y corte de caja. Nunca confiar en precio enviado por cliente.

### P1: retención y mejor ocupación

- Recordatorios y enlace seguro de reprogramación/cancelación.
- Depósitos y políticas anti-no-show; lista de espera para llenar huecos.
- Re-reserva al finalizar, campañas segmentadas con consentimiento, reseña verificada, promociones y referidos.
- Membresías, paquetes, gift cards, puntos, comisiones e inventario con alertas.

### P2: escala, no antes

- Multi-sucursal, reportes comparativos, app/PWA de operación para equipo y directorio UrbanBlade.
- Automatización o IA solo cuando exista una fuente de datos autorizada, trazable y una acción humana segura.

## Patrón UX por audiencia

**Cliente, móvil primero:** catálogo visual; precio/duración visibles; profesional y disponibilidad real; confirmación inequívoca; próxima cita, re-reserva, puntos y membresía. Cada flujo debe tener una acción principal y permitir volver sin perder datos.

**Recepción/dueño, escritorio primero:** agenda como pantalla central; acciones rápidas y estados inequívocos; detalle lateral de cliente/cita; métricas accionables y reales, no indicadores inventados.

**Barbero, móvil/PWA:** `Mi día`, próximas citas, perfil permitido del cliente, notas/fotos autorizadas y actualización de estado. No exponer ingresos, clientes o configuración de otras personas.

## Forma de trabajo

1. Elegir una capacidad concreta de P0/P1/P2 y definir usuario, dolor, resultado y métrica observable.
2. Inventariar la pantalla, composable y endpoint existentes antes de codificar. Reutilizar componentes y tokens; no duplicar lógica de reserva o autorización.
3. Comparar el flujo con el contrato API. Si falta algo, proponer primero el cambio mínimo, aditivo y documentado; no simular datos críticos ni romper respuestas actuales.
4. Implementar una rebanada vertical completa: loading, vacío, error, permisos, responsive y accesibilidad.
5. Verificar el flujo real en navegador y las validaciones apropiadas del repositorio. Reportar lo que no se pudo verificar; no afirmar paridad solo por pasar un build.

## Criterios de aceptación

- Un cliente entiende servicio, precio, duración y siguiente paso sin explicación.
- La disponibilidad y los estados siempre vienen de datos reales; el backend sigue siendo autoridad de conflictos, precio, pago e inventario.
- La pantalla funciona en móvil y escritorio, teclado y lectores de pantalla básicos; respeta `prefers-reduced-motion`.
- No se introducen dependencias, rutas, permisos ni métricas ficticias sin justificación y prueba.
- Los cambios de frontend y backend se revisan, validan y versionan por separado cuando el usuario autorice publicar.
