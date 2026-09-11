---
name: urbanblade-market-mobile
description: Mapea y, cuando exista su repositorio, implementa la app móvil nativa de UrbanBlade usando el contrato API estable y los flujos comerciales priorizados. No editar el antiguo proyecto mobil ni iniciar una app sin repositorio y autorización.
---

# UrbanBlade market mobile

## Objetivo

Dejar una guía ejecutable para que la futura app nativa lleve al móvil los flujos de mayor valor sin duplicar ni romper la web o la API: reserva para cliente y operación ligera para personal.

## Límite actual

- El proyecto móvil anterior está discontinuado. Mientras no exista un repositorio nativo nuevo y una instrucción explícita, este skill es de arquitectura, mapeo y preparación: no crear, modificar ni publicar una app.
- `barber` es una API JSON con Bearer tokens propios (`mobile_api_tokens`), no Sanctum. No asumir rutas, campos ni autenticación: inspeccionar el contrato vigente antes de integrar.
- No cambiar el contrato existente de `/api/v1` para acomodar una pantalla móvil. Proponer primero campos/endpoints aditivos, permisos, documentación Scribe y pruebas.
- No tocar secretos, producción, esquemas, seeders, pagos ni datos reales sin autorización. Para backend, aplicar `urbanblade-guardrails`.

## Mapa de aplicaciones por rol

### Cliente: primera entrega móvil

1. Inicio: próxima cita, volver a reservar, barberías/favoritos y notificaciones relevantes.
2. Descubrir y reservar: perfil visual, servicios con precio/duración, profesional, disponibilidad real y confirmación.
3. Mis citas: detalle, política, reprogramar/cancelar permitido y comprobante.
4. Mi relación: historial, puntos, membresías/paquetes, gift cards y reseñas posteriores a una cita real.

### Equipo: segunda entrega móvil

1. `Mi día`: agenda propia, estado y próximos clientes.
2. Detalle de cita: datos mínimos autorizados, preferencias, notas y referencias de servicio.
3. Operación: check-in, iniciar/finalizar, observaciones y aviso a recepción. Los importes, cobros y permisos siguen validados por servidor.

### Dueño/recepción: tercera entrega

- Vista compacta de agenda, check-in, alertas y métricas reales del día. La configuración compleja, caja y reportes extensos deben conservar una experiencia de escritorio hasta que haya evidencia de uso móvil.

## Decisiones UX

- Diseñar para una acción principal por pantalla, carga rápida y controles alcanzables con el pulgar.
- Mostrar precio, duración, profesional, zona horaria y política antes de confirmar; nunca confirmar una disponibilidad cacheada si el servidor la rechazó.
- Usar navegación por rol, no una copia en miniatura del dashboard web.
- Incluir estados offline, reintento e idempotencia solo después de definir con la API qué acciones son seguras para repetir. Pagos, stock y cambios de cita no pueden inventarse offline.
- Pedir permisos de cámara, notificaciones o ubicación justo cuando aporten valor y ofrecer alternativa; no recolectar PII innecesaria.

## Preparación antes de que exista el repositorio

Crear o mantener un mapa de contrato por flujo: pantalla, actor, endpoint, método, payload, respuesta, errores, autorización y prueba de regresión. Identificar vacíos como disponibilidad, reprogramación, depósitos, membresías o notificaciones antes de empezar UI.

## Cuando exista el repositorio nativo

1. Leer su `AGENTS.md`, stack y reglas de compilación; confirmar plataforma y herramientas elegidas por el usuario.
2. Revisar el contrato real de backend y usar datos de prueba seguros. No usar credenciales ni Atlas para pruebas destructivas.
3. Implementar una rebanada vertical de cliente antes de abrir múltiples pantallas: login seguro, perfil de negocio, disponibilidad, reserva y mis citas.
4. Añadir pruebas de contrato y recorridos de dispositivo/emulador para éxito, 401/403, 422, red lenta y reintentos.
5. Mantener cambios de app y backend en repositorios/commits separados. No publicar sin la autorización y la validación correspondiente.

## Criterios de aceptación

- La app muestra solo datos y acciones permitidos al rol autenticado.
- Reserva, estado y pago se confirman contra servidor; no hay éxitos visuales falsos.
- El flujo cliente funciona con una mano, es comprensible y recuperable ante fallos.
- La app comparte el mismo vocabulario, políticas y fuentes de verdad que la web, sin copiar lógicas críticas al cliente.
