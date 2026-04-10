# Usuarios de Prueba

A continuación se documentan los usuarios de prueba creados para los diferentes roles del sistema:

| Rol | Email | Password | Responsabilidad |
| :--- | :--- | :--- | :--- |
| **Cliente** | cliente@test.com | password | Agendar y consultar citas. |
| **Cliente** | cliente1@test.com | password | Agendar y consultar citas. |
| **Cliente** | cliente2@test.com | password | Agendar y consultar citas. |
| **Barbero** | barbero@test.com | password | Cambiar estados de citas (updateStatus). |
| **Barbero** | barbero1@test.com | password | Cambiar estados de citas (updateStatus). |
| **Barbero** | barbero2@test.com | password | Cambiar estados de citas (updateStatus). |
| **Recepcionista** | recepcionista@test.com | password | Gestión de Inventario (products/movements). |
| **Recepcionista** | recepcionista1@test.com | password | Gestión de Inventario (products/movements). |
| **Recepcionista** | recepcionista2@test.com | password | Gestión de Inventario (products/movements). |
| **Administrador** | kikeramirez160418@gmail.com | password | Acceso total, logs y pagos. |

> **Nota:** El sistema valida roles mediante middleware. El rol `staff` en el código se refiere a la capacidad de gestionar inventario (Admin o Recepcionista).

> Todos los usuarios de prueba ya existen en la base de datos y pueden usarse para pruebas automatizadas y manuales.
