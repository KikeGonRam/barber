## Qué cambia y por qué

<!-- Una o dos frases. Enlaza la tarea o historia (por ejemplo T077 / HT-26). -->

## Lista de verificación del revisor (T077)

Marca cada punto antes de aprobar. Si no aplica, escribe «N/A» y por qué.

### Estándares
- [ ] El cambio hace una sola cosa y el título del PR la describe (en español, sin créditos de herramientas).
- [ ] El código sigue el estilo del archivo que toca: nombres, comentarios y formato (Pint).
- [ ] No quedan código muerto, `dd()`, `dump()` ni comentarios de depuración.
- [ ] Los mensajes de la API y los textos para el usuario están en español.

### Seguridad
- [ ] No hay secretos, tokens, contraseñas ni correos reales en el código, los datos de prueba ni los comentarios.
- [ ] Toda entrada se valida (FormRequest o reglas) y las respuestas pasan por un API Resource.
- [ ] Un endpoint nuevo o cambiado tiene prueba de qué rol sí y cuál no puede usarlo.
- [ ] Ninguna consulta de Mongo arma filtros con datos sin validar.
- [ ] Una dependencia nueva está justificada y `composer audit` no empeora.

### Pruebas
- [ ] Hay pruebas nuevas o actualizadas que fallan sin el cambio.
- [ ] PHPUnit y PHPStan pasaron en local y el CI está en verde (la línea base de PHPStan no crece).
- [ ] Probé el caso feliz y al menos un caso de error.

### Documentación y contrato
- [ ] El README o la documentación afectada está actualizada.
- [ ] Si cambió la API, `openapi.yaml` está actualizado y `scripts/verificar_contrato_api.mjs` pasa.

### Despliegue
- [ ] Sé si requiere migración, variable de entorno nueva o reinicio, y está anotado aquí.
- [ ] Sé cómo volver atrás si falla.

## Cómo lo probé

<!-- Comandos ejecutados y resultado. -->
