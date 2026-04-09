# Usuarios de Prueba

A continuación se documentan los usuarios de prueba creados para los diferentes roles del sistema:

| Rol | Email | Password | Responsabilidad |
| :--- | :--- | :--- | :--- |
| **Cliente** | cliente1@demo.com | password | Agendar y consultar citas. |
| **Barbero** | staff1@demo.com | password | Cambiar estados de citas (updateStatus). |
| **Recepcionista** | recepcionista1@demo.com | password | Gestión de Inventario (products/movements). |
| **Administrador** | kikeramirez160418@gmail.com | password | Acceso total, logs y pagos. |

> **Nota:** El sistema valida roles mediante middleware. El rol `staff` en el código se refiere a la capacidad de gestionar inventario (Admin o Recepcionista).

> Nota: El usuario administrador ya existe. Los demás deben ser creados mediante el endpoint de registro o directamente en la base de datos para pruebas.
