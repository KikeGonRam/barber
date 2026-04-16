# Matriz Global de Cobertura de Pruebas

| Módulo              | Tipo de Prueba         | Técnica      | Área        | Riesgos Cubiertos                                                                                 |
|---------------------|-----------------------|--------------|-------------|---------------------------------------------------------------------------------------------------|
| Autenticación       | Validación, Seguridad | SAST, DAST   | Frontend, Backend | Fuga de credenciales, fuerza bruta, sesión insegura, CSRF, MFA, SQLi, XSS, control de sesiones    |
| Gestión de Usuarios | Validación, Seguridad | SAST, DAST   | Frontend, Backend | Escalada de privilegios, exposición de datos, XSS, CSRF, control de acceso, eliminación insegura   |
| Gestión de Citas    | Validación, Seguridad, Lógica de negocio | SAST, DAST | Frontend, Backend | Manipulación de horarios, acceso no autorizado, integridad de datos, DoS, SQLi, XSS, CSRF         |
| Pagos y Facturación | Validación, Seguridad, Lógica de negocio | SAST, DAST | Frontend, Backend | Fraude, MITM, almacenamiento inseguro, SQLi, XSS, DoS, CSRF, control de acceso, integridad recibos |
| Panel Administrativo| Validación, Seguridad, Auditoría | SAST, DAST | Frontend, Backend | Acceso indebido, manipulación de configuraciones, XSS, SQLi, DoS, segregación de funciones, logs   |
| API/Backend         | Validación, Seguridad, Integración | SAST, DAST | Backend           | Inyección, control de acceso, errores de lógica, DoS, endpoints no documentados, CSRF, logs       |
| Frontend/UI         | Validación, Seguridad, Regresión, Rendimiento | SAST, DAST | Frontend | Validaciones insuficientes, XSS, manipulación de formularios, errores de UX, accesibilidad        |

---

# Tabla Dinámica de Pruebas

| Módulo              | Tipo de Prueba         | Técnica      | Nivel de Riesgo | Prioridad |
|---------------------|-----------------------|--------------|-----------------|-----------|
| Autenticación       | Seguridad             | SAST, DAST   | Crítico/Alto    | Crítica/Alta |
| Autenticación       | Validación            | DAST         | Alto/Medio      | Alta/Media  |
| Gestión de Usuarios | Seguridad             | SAST, DAST   | Crítico/Alto    | Crítica/Alta |
| Gestión de Usuarios | Validación            | DAST         | Alto/Medio      | Alta/Media  |
| Gestión de Citas    | Seguridad             | SAST, DAST   | Crítico/Alto    | Crítica/Alta |
| Gestión de Citas    | Lógica de negocio     | SAST         | Alto            | Alta        |
| Gestión de Citas    | Validación            | DAST         | Alto/Medio      | Alta/Media  |
| Pagos y Facturación | Seguridad             | SAST, DAST   | Crítico/Alto    | Crítica/Alta |
| Pagos y Facturación | Lógica de negocio     | SAST         | Alto            | Alta        |
| Pagos y Facturación | Validación            | DAST         | Alto/Medio      | Alta/Media  |
| Panel Administrativo| Seguridad             | SAST, DAST   | Crítico/Alto    | Crítica/Alta |
| Panel Administrativo| Auditoría             | SAST, DAST   | Alto            | Alta        |
| Panel Administrativo| Validación            | DAST         | Alto/Medio      | Alta/Media  |
| API/Backend         | Seguridad             | SAST, DAST   | Crítico/Alto    | Crítica/Alta |
| API/Backend         | Integración           | DAST         | Alto            | Alta        |
| API/Backend         | Validación            | DAST         | Alto/Medio      | Alta/Media  |
| Frontend/UI         | Seguridad             | SAST, DAST   | Crítico/Alto    | Crítica/Alta |
| Frontend/UI         | Regresión             | DAST         | Medio           | Alta        |
| Frontend/UI         | Rendimiento           | DAST         | Medio           | Media       |
| Frontend/UI         | Validación            | DAST         | Alto/Medio      | Alta/Media  |

---

# Recomendaciones DevSecOps para Automatización en CI/CD

- **Autenticación:**
  - Integrar SAST (ej. SonarQube, PHPStan) en cada push/PR.
  - Automatizar DAST (OWASP ZAP) en entorno de staging.
  - Pruebas de fuerza bruta y CSRF automatizadas con scripts.
- **Gestión de Usuarios:**
  - SAST en modelos/controladores de usuario.
  - DAST para XSS/SQLi en endpoints de usuario.
  - Pruebas de regresión automatizadas tras cada despliegue.
- **Gestión de Citas:**
  - SAST para lógica de disponibilidad y control de acceso.
  - DAST para validaciones y DoS con herramientas de carga.
- **Pagos y Facturación:**
  - SAST para cifrado y logs de pagos.
  - DAST para MITM, CSRF y duplicidad de pagos.
  - Integrar pruebas de integración con sandbox de pagos.
- **Panel Administrativo:**
  - SAST para control de acceso y segregación de funciones.
  - DAST para XSS/SQLi en panel y logs.
  - Auditoría automatizada de logs críticos.
- **API/Backend:**
  - SAST en cada build para endpoints y control de acceso.
  - DAST para fuzzing y DoS en endpoints.
  - Pruebas de integración API automatizadas (Postman/Newman, PHPUnit).
- **Frontend/UI:**
  - DAST para validaciones, XSS y regresión visual (Percy, Cypress).
  - Pruebas de accesibilidad automatizadas (axe-core).
  - Medición de rendimiento UI en pipeline.

**Pipeline recomendado:**
1. SAST en cada push/PR.
2. Build y pruebas unitarias.
3. Despliegue a entorno de staging.
4. DAST y pruebas de integración.
5. Pruebas de regresión y accesibilidad.
6. Auditoría de logs y reportes de cobertura.
7. Despliegue a producción solo si todo pasa.

---

**Fin del modelo integral de pruebas.**
