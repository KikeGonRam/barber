# Acceso y roles de UrbanBlade

Esta guía documenta el proceso de acceso, no un inventario de cuentas.
No contiene correos reales, contraseñas ni tokens.
Las cantidades de usuarios y perfiles cambian y no se mantienen en un README.

## Entrar

El login de producto está en el frontend Nuxt:
`http://localhost:3000/login` en desarrollo.
Para un entorno remoto, utiliza la URL entregada por el responsable.
El backend expone autenticación por API y las redirecciones web heredadas.

![Pantalla de acceso ilustrativa](assets/login.png)

## Alta y recuperación

- Un cliente se registra desde la interfaz pública o mediante Google.
- Las altas y cambios de roles internos se realizan desde la administración
  autorizada del producto.
- El registro público y Google no permiten escoger un rol privilegiado.
- Los perfiles de cliente/barbero se enlazan al usuario; un rol por sí solo
  no demuestra que exista un perfil operativo completo.
- Para recuperar acceso, utilizar el flujo de recuperación; no publicar
  contraseñas temporales ni ejecutar Tinker con credenciales pegadas en el comando.
- El bootstrap de una instalación aislada es distinto de crear una cuenta en
  producción. Los seeders modifican datos y requieren revisar el destino.

## Roles

| Rol | Ámbito |
| --- | --- |
| Administrador | Configuración y gestión de negocio conforme a permisos. |
| Recepcionista | Agenda, atención, clientes, cobros y pedidos autorizados. |
| Barbero | Citas y perfil propios, horario, portafolio y actividad personal. |
| Cliente | Reservas propias, compras, recibos y servicios de su cuenta. |
| Ingeniero | Consultas autorizadas de estado, reportes y observabilidad. |

El backend valida rol, permisos y pertenencia de cada recurso.
El menú visible del frontend no es una barrera de seguridad.
La asignación de roles se describe en
[arquitectura](DOCUMENTACION_TECNICA.md).

## Custodia

El propietario administra las credenciales fuera del repositorio.
No incluir capturas del gestor de secretos, resultados completos de
autenticación ni exportaciones de usuarios en documentación pública.
Un usuario eliminado lógicamente puede seguir ocupando su correo en un índice;
no se recomienda `forceDelete` como solución rutinaria.

Consulta [seguridad](../SECURITY.md), [gestión de credenciales](PLAN_ROTACION_CREDENCIALES.md)
y [manual de usuario](MANUAL_USUARIO.md).
