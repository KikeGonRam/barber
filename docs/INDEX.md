# 📚 Índice de Documentación - BarberPro Elite

## 🚀 Inicio Rápido

- **[ACCESO.md](./ACCESO.md)** - Credenciales de todos los usuarios (Admin, Recepcionista, Barbero, Cliente)
- **[QUICK_REFERENCE.md](./QUICK_REFERENCE.md)** - Referencia rápida de comandos esenciales
- **[README.md](../README.md)** - Información general del proyecto

---

## 📖 Documentación Completa

### 1️⃣ Guías de Inicio
- **[TESTING_GUIDE.md](./TESTING_GUIDE.md)** - Cómo ejecutar tests y validaciones
- **[QUICK_REFERENCE.md](./QUICK_REFERENCE.md)** - Comandos diarios y referencias útiles

### 2️⃣ Monitoreo y Performance
- **[MONITORING_GUIDE.md](./MONITORING_GUIDE.md)** - Sentry + K6 configuración y setup
- **[VALIDATION_CHECKLIST.md](./VALIDATION_CHECKLIST.md)** - Checklist completo de validación

### 3️⃣ Historial de Mejoras
- **[MEJORAS_IMPLEMENTADAS.md](./MEJORAS_IMPLEMENTADAS.md)** - Todas las mejoras realizadas al proyecto

### 4️⃣ Acceso a Usuarios
- **[ACCESO.md](./ACCESO.md)** - Credenciales de acceso para todos los roles

---

## 🗂️ Estructura del Proyecto

```
barberpro-elite/
├── 📁 app/                    # Código fuente de Laravel
├── 📁 config/                 # Configuración de la aplicación
│   ├── docker/               # Configuración Docker (docker-compose, Dockerfile)
│   ├── deployment/           # Configuración de despliegue
│   └── ...
├── 📁 database/              # Migraciones y seeders
├── 📁 docs/                  # 📍 DOCUMENTACIÓN (este archivo)
├── 📁 k6/                    # Tests de carga (K6)
├── 📁 resources/             # Frontend (Vue, CSS, assets)
├── 📁 routes/                # Rutas de API y web
├── 📁 tests/                 # Tests PHPUnit
├── 📁 scripts/               # Scripts de utilidad
├── 📁 data/                  # Backups y datos temporales
├── 📁 .github/workflows/     # CI/CD pipelines
│
├── docker-compose.yml        # Orquestación Docker (copia desde config/docker/)
├── Dockerfile                # Definición de imagen Docker
├── .env                      # Variables de entorno
├── package.json              # Dependencias Node
├── composer.json             # Dependencias PHP
└── README.md                 # Información general
```

---

## 🔐 Acceso Rápido

| Rol | Email | Contraseña | Dashboard |
|-----|-------|------------|-----------|
| 👨‍💼 Admin | `al222310427@gmail.com` | `password` | http://localhost:8000 |
| 👨‍💼 Recepcionista | `recepcionista@test.com` | `password` | http://localhost:8000 |
| ✂️ Barbero | `barbero@test.com` | `password` | http://localhost:8000 |
| 👤 Cliente | `cliente@test.com` | `password` | http://localhost:8000 |

Ver [ACCESO.md](./ACCESO.md) para credenciales completas.

---

## 🛠️ Servicios Disponibles

| Servicio | URL | Descripción |
|----------|-----|------------|
| 🌐 Frontend | http://localhost:8000 | Dashboard BarberPro |
| 📧 Mailpit | http://localhost:8025 | Captura de emails |
| 🗂️ Adminer | http://localhost:8081 | Gestor de base de datos |
| 📊 PHPMyAdmin | http://localhost:8082 | Interface MySQL |

---

## 📋 Comandos Esenciales

```bash
# Iniciar servicios
docker-compose up -d

# Ejecutar tests
npm run test

# Tests de carga
npm run test:load

# Build frontend
npm run build

# Ver logs
docker-compose logs -f app
```

Ver [QUICK_REFERENCE.md](./QUICK_REFERENCE.md) para más comandos.

---

## 📊 Estado del Proyecto

✅ **85% Profesional**

- ✅ Sentry (monitoreo)
- ✅ K6 (load testing)
- ✅ 212 tests (PHPUnit)
- ✅ Docker (9 servicios)
- ✅ CI/CD (GitHub Actions)
- ✅ Documentación completa

---

## 🚀 Próximos Pasos

1. **Acceder al dashboard:** http://localhost:8000
2. **Usar credenciales:** Ver [ACCESO.md](./ACCESO.md)
3. **Ejecutar tests:** `npm run test:load`
4. **Leer guías:** Consultar documentación

---

## 💬 Preguntas Frecuentes

**P: ¿Dónde están las credenciales?**  
R: En [ACCESO.md](./ACCESO.md) - todos los usuarios y roles

**P: ¿Cómo ejecuto los tests?**  
R: Ver [TESTING_GUIDE.md](./TESTING_GUIDE.md)

**P: ¿Cómo configuro monitoreo?**  
R: Ver [MONITORING_GUIDE.md](./MONITORING_GUIDE.md)

**P: ¿Qué servicios están corriendo?**  
R: Ver [QUICK_REFERENCE.md](./QUICK_REFERENCE.md#servicios)

---

**Última actualización:** 2026-05-08 23:27  
**Versión:** 1.0  
**Estado:** ✅ Production-Ready
