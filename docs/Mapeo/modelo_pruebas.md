# Modelo Integral de Pruebas – Sistema de Gestión de Servicios (Barbería)

## 1. Análisis del Sistema

### Módulos Principales Identificados
- Autenticación y Autorización
- Gestión de Usuarios
- Gestión de Citas
- Pagos y Facturación
- Panel Administrativo
- API/Backend
- Frontend/UI

### Riesgos Detectados por Módulo
- **Autenticación:** Fuga de credenciales, fuerza bruta, sesión insegura, CSRF, MFA ausente.
- **Usuarios:** Escalada de privilegios, exposición de datos, validaciones débiles, XSS.
- **Citas:** Manipulación de horarios, acceso no autorizado, integridad de datos.
- **Pagos:** Fraude, MITM, almacenamiento inseguro, inyección SQL, validación de transacciones.
- **Admin:** Acceso indebido, manipulación de configuraciones, auditoría insuficiente.
- **API/Backend:** Inyección, control de acceso, errores de lógica, DoS, exposición de endpoints.
- **Frontend/UI:** Validaciones insuficientes, XSS, manipulación de formularios, errores de UX.

### Tipos de Pruebas Necesarios por Módulo
- **Autenticación:** Seguridad, validación, integración, regresión, rendimiento.
- **Usuarios:** Seguridad, validación, integración, regresión, lógica de negocio.
- **Citas:** Validación, integración, lógica de negocio, seguridad.
- **Pagos:** Seguridad, integración, validación, rendimiento, regresión.
- **Admin:** Seguridad, validación, integración, auditoría.
- **API/Backend:** SAST, DAST, integración, rendimiento, seguridad, regresión.
- **Frontend/UI:** Validación, DAST, UX, regresión, integración.

**Justificación:** Cada módulo presenta riesgos y funcionalidades críticas que requieren pruebas específicas para garantizar seguridad, estabilidad y cumplimiento de requisitos de negocio.

## 2. Clasificación Dinámica

| Propósito      | Enfoque         | Naturaleza      | Ejemplo de Pruebas           |
|---------------|-----------------|-----------------|------------------------------|
| Validación    | Caja negra      | Dinámica        | Pruebas funcionales, UI      |
| Seguridad     | Gris/Blanca     | Estática/Dinámica| SAST, DAST, fuzzing          |
| Rendimiento   | Negra/Gris      | Dinámica        | Stress, carga, profiling     |
| Estabilidad   | Negra           | Dinámica        | Regresión, smoke             |
| Integración   | Gris            | Dinámica        | API, módulos, E2E            |

- **SAST:** Análisis de código fuente (estática, caja blanca/gris, backend principalmente).
- **DAST:** Pruebas dinámicas sobre la app en ejecución (caja negra/gris, frontend/backend).

---

## 3. Generación Modular de Pruebas

A continuación, se presentan los primeros 50 casos de prueba, organizados por módulo. Cada caso incluye todos los campos requeridos.

**[Bloque 1/5: Pruebas 1-50]**

