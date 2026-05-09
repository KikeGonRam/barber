# 🔐 Credenciales de Acceso - BarberPro Elite

> **⚠️ IMPORTANTE:** Estas credenciales son solo para desarrollo y testing.  
> **No usar en producción sin cambiar las contraseñas.**

---

## 🌐 URL de Acceso

```
Frontend:    http://localhost:8000
Adminer:     http://localhost:8081
PHPMyAdmin:  http://localhost:8082
Mailpit:     http://localhost:8025
```

---

## 👨‍💼 ADMINISTRADOR

| Campo | Valor |
|-------|-------|
| **Nombre** | Administrador Barbería |
| **Email** | `al222310427@gmail.com` |
| **Contraseña** | `password` |
| **Rol** | Administrador |
| **Permisos** | Acceso total |

---

## 👨‍💼 RECEPCIONISTAS

### Recepcionista Principal
| Campo | Valor |
|-------|-------|
| **Nombre** | Recepcionista Test |
| **Email** | `recepcionista@test.com` |
| **Contraseña** | `password` |
| **Rol** | Recepcionista |
| **Funciones** | Gestión de citas, clientes |

### Recepcionista 1
| Campo | Valor |
|-------|-------|
| **Nombre** | Recepcionista Test 1 |
| **Email** | `recepcionista1@test.com` |
| **Contraseña** | `password` |
| **Rol** | Recepcionista |
| **Funciones** | Gestión de citas, clientes |

### Recepcionista 2
| Campo | Valor |
|-------|-------|
| **Nombre** | Recepcionista Test 2 |
| **Email** | `recepcionista2@test.com` |
| **Contraseña** | `password` |
| **Rol** | Recepcionista |
| **Funciones** | Gestión de citas, clientes |

---

## ✂️ BARBEROS

### Barbero Principal
| Campo | Valor |
|-------|-------|
| **Nombre** | Barbero Test |
| **Email** | `barbero@test.com` |
| **Contraseña** | `password` |
| **Rol** | Barbero |
| **Especialidades** | Fade, Barba |
| **Horario** | Lunes-Viernes: 09:00 - 21:00 |

### Barbero 1
| Campo | Valor |
|-------|-------|
| **Nombre** | Barbero Test 1 |
| **Email** | `barbero1@test.com` |
| **Contraseña** | `password` |
| **Rol** | Barbero |
| **Especialidades** | Corte clásico |
| **Horario** | Lunes-Viernes: 09:00 - 21:00 |

### Barbero 2
| Campo | Valor |
|-------|-------|
| **Nombre** | Barbero Test 2 |
| **Email** | `barbero2@test.com` |
| **Contraseña** | `password` |
| **Rol** | Barbero |
| **Especialidades** | Corte clásico |
| **Horario** | Lunes-Viernes: 09:00 - 21:00 |

---

## 👤 CLIENTES

### Cliente Principal
| Campo | Valor |
|-------|-------|
| **Nombre** | Cliente Test |
| **Email** | `cliente@test.com` |
| **Contraseña** | `password` |
| **Rol** | Cliente |
| **Teléfono** | +521234567890 |
| **Fecha Nacimiento** | 1990-01-01 |

### Cliente 1
| Campo | Valor |
|-------|-------|
| **Nombre** | Cliente Test 1 |
| **Email** | `cliente1@test.com` |
| **Contraseña** | `password` |
| **Rol** | Cliente |
| **Teléfono** | +521234567891 |
| **Fecha Nacimiento** | 1991-02-01 |

### Cliente 2
| Campo | Valor |
|-------|-------|
| **Nombre** | Cliente Test 2 |
| **Email** | `cliente2@test.com` |
| **Contraseña** | `password` |
| **Rol** | Cliente |
| **Teléfono** | +521234567892 |
| **Fecha Nacimiento** | 1992-02-01 |

---

## 📊 CONFIGURACIÓN DE LA BARBERÍA

| Campo | Valor |
|-------|-------|
| **Nombre** | BarberPro Elite |
| **Horario Apertura** | 09:00 |
| **Horario Cierre** | 21:00 |
| **Política Cancelación** | 24 horas antes |
| **Modo Mantenimiento** | Desactivado |

---

## 🗄️ BASE DE DATOS

| Campo | Valor |
|-------|-------|
| **Host** | mysql (puerto 3306) |
| **Usuario** | barber |
| **Contraseña** | barber |
| **Base de Datos** | barber_db |

### Acceso desde PHPMyAdmin
- URL: http://localhost:8082
- Usuario: `barber`
- Contraseña: `barber`

### Acceso desde Adminer
- URL: http://localhost:8081
- Sistema: MySQL
- Servidor: mysql
- Usuario: `barber`
- Contraseña: `barber`
- Base de datos: `barber_db`

---

## 🔑 Contraseña Común

Todos los usuarios de prueba comparten la misma contraseña para facilitar el testing:

```
password
```

---

## ✅ Procedimiento de Login

1. Ir a http://localhost:8000
2. Hacer clic en "Iniciar Sesión"
3. Seleccionar el rol (Admin, Recepcionista, Barbero, Cliente)
4. Ingresar email y contraseña de la tabla correspondiente
5. ✅ Acceso garantizado

---

## 🧪 Testing por Rol

### Como Administrador
```bash
Email:    al222310427@gmail.com
Password: password
# Acceso: Dashboard completo, gestión de usuarios, reportes
```

### Como Recepcionista
```bash
Email:    recepcionista@test.com
Password: password
# Acceso: Citas, clientes, agenda
```

### Como Barbero
```bash
Email:    barbero@test.com
Password: password
# Acceso: Agenda personal, citas asignadas, perfil
```

### Como Cliente
```bash
Email:    cliente@test.com
Password: password
# Acceso: Reservar citas, ver perfil, historial
```

---

## 📱 Características por Rol

| Funcionalidad | Admin | Recepcionista | Barbero | Cliente |
|---|:---:|:---:|:---:|:---:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Gestionar Usuarios | ✅ | ❌ | ❌ | ❌ |
| Gestionar Barberos | ✅ | ✅ | ❌ | ❌ |
| Gestionar Clientes | ✅ | ✅ | ❌ | ❌ |
| Ver Citas | ✅ | ✅ | ✅ | ✅ |
| Crear Citas | ✅ | ✅ | ❌ | ✅ |
| Reportes | ✅ | ❌ | ❌ | ❌ |
| Configuración | ✅ | ❌ | ❌ | ❌ |

---

## 🚀 Próximos Pasos

1. ✅ Seeders ejecutados
2. 📋 Credenciales documentadas (este archivo)
3. 🔨 Próximo: Ejecutar `npm run build`
4. 📊 Luego: Configurar Sentry
5. 🧪 Finalmente: Tests de carga

---

**Última actualización:** 2026-05-08 23:23  
**Estado:** ✅ Base de datos poblada con éxito
