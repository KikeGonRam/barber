# Oracle Cloud Always Free: entorno de pruebas

## Estado al 11 de septiembre de 2026

Se inició la preparación de un entorno de pruebas en Oracle Cloud Infrastructure
(OCI), región **Mexico Northeast (Monterrey)**. No se desplegó la aplicación ni
se modificó MongoDB Atlas, Redis, credenciales ni el código de UrbanBlade.

Recursos creados para habilitar una futura VM pública:

- VCN: `urbanblade-staging-vcn` (`10.0.0.0/16`).
- Internet Gateway: `urbanblade-staging-igw`.
- Regla de ruta: `0.0.0.0/0` hacia ese Internet Gateway.
- Subred pública regional: `urbanblade-staging-public-subnet`
  (`10.0.1.0/24`), asociada a la tabla de rutas y lista de seguridad
  predeterminadas de la VCN.

La consola informó que no había formas de cómputo disponibles para la imagen
seleccionada al abrir un nuevo asistente de VM. Por ello **no existe aún una
instancia, IP pública, despliegue o dominio de UrbanBlade**. Antes de continuar,
validar disponibilidad de capacidad Always Free en OCI; no cambiar a una forma
de pago ni actualizar el plan sin una autorización explícita del propietario.

La administración de regiones confirma que la cuenta solo tiene suscrita la
región principal, Mexico Northeast (Monterrey), y que ya alcanzó el máximo de
regiones permitido para esta tenencia. No es posible mover estos recursos ni
probar otra región desde esta cuenta sin un cambio de límites o de plan que el
propietario autorice expresamente.

## Configuración objetivo de la VM

Cuando OCI vuelva a ofrecer capacidad, crear una sola instancia de pruebas:

- Nombre sugerido: `urbanblade-staging-01`.
- Imagen: Oracle Linux 9.
- Forma: `VM.Standard.A1.Flex`, elegible Always Free, con 1 OCPU y 6 GB RAM.
- Volumen de arranque predeterminado: 46.6 GB, cifrado en tránsito.
- Conectividad: usar la VCN y subred pública documentadas arriba; asignar IPv4
  pública automáticamente.
- Acceso: generar o aportar una llave SSH. La llave privada solo se conserva en
  el equipo del propietario; nunca se agrega al repositorio, documentación,
  chat ni variables de entorno.

## Puesta en marcha posterior

La VM solamente habilita infraestructura. Antes de desplegar, preparar un
archivo de producción independiente del entorno local y revisar Docker Compose:

1. Conservar MongoDB Atlas como servicio externo; no iniciar contenedores de
   pruebas (`mongo-test`, Mailpit ni Ollama) en el servidor.
2. Configurar secretos, `APP_ENV=production`, `APP_DEBUG=false`, URL del
   frontend, CORS y credenciales de servicios solo mediante el mecanismo
   seguro del servidor.
3. Publicar Laravel detrás de Nginx, ejecutar worker y scheduler como servicios
   persistentes, y servir Nuxt de `frontend-urban` de forma independiente.
4. Abrir únicamente los puertos estrictamente necesarios: 22 restringido a la
   IP administrativa cuando sea posible, y 80/443 para web. No exponer MongoDB
   ni Redis a Internet.
5. Verificar primero con una URL temporal y TLS; el dominio definitivo y
   servicios externos se configuran solo con autorización adicional.

## Reversión y control de costo

Si se abandona la prueba, eliminar en este orden la instancia, su IP pública y
después los recursos de red que ya no tengan dependencias. Antes de borrar,
confirmar que no contienen otros servicios. Revisar periódicamente el panel de
costos y la etiqueta **Always Free**: esta guía no autoriza recursos de pago.
