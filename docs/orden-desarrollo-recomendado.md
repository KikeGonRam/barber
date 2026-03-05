# Orden de desarrollo recomendado (módulo por módulo)

1. **Base técnica y seguridad**
   - Instalar Breeze, Spatie Permission, Activity Log, DomPDF, Laravel Excel.
   - Configurar roles iniciales, middleware y políticas.
   - Definir layout Blade + Bootstrap responsive.

2. **Módulo Auth + Perfiles**
   - Registro/login por tipo de usuario.
   - Gestión de perfil `Barber` y `Client`.
   - Preferencias de notificación por cliente.

3. **Módulo Servicios y Precios**
   - CRUD de servicios/categorías.
   - Combos y descuentos.
   - Reglas de duración por servicio.

4. **Módulo Citas y Reservaciones**
   - Agenda por barbero y general.
   - Validación de disponibilidad en tiempo real.
   - Reagendamiento, cancelación y lista de espera.

5. **Módulo Pagos y Facturación**
   - Registro de pago por cita.
   - Ticket PDF.
   - Cierre de caja diario.

6. **Módulo Inventario**
   - CRUD de productos.
   - Entradas/salidas con trazabilidad.
   - Alertas de stock mínimo.

7. **Notificaciones y automatización**
   - In-App + correo + SMS/WhatsApp.
   - Scheduler + queues para recordatorios.

8. **Dashboard y reportes**
   - KPI en panel admin (Chart.js).
   - Exportables PDF/Excel por filtros.

9. **Calidad y despliegue**
   - Seeders/factories realistas.
   - Tests de feature para flujos críticos.
   - Hardening, logs y monitoreo.
