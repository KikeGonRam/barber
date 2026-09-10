# Corre la suite de PHPUnit dentro de barber-app contra el Mongo LOCAL de
# pruebas (contenedor "mongo-test", ver docker-compose.yml + .env.testing),
# nunca contra la Atlas compartida con spark/.
#
# Necesario porque barber-app usa `env_file: .env` en docker-compose.yml, lo
# que "hornea" las variables de Atlas como variables de entorno reales del
# contenedor al crearlo. Symfony Dotenv (que usa Laravel) nunca sobreescribe
# una variable de entorno que ya existe, asi que .env.testing por si solo NO
# alcanza: hay que forzar el override en el propio `docker exec`.
#
# INCIDENTE (2026-08-28): .docker/entrypoint.sh corre "php artisan optimize"
# en cada arranque del contenedor "app", lo que cachea bootstrap/cache/
# config.php con los valores de Atlas. Una vez cacheado, Laravel deja de
# leer variables de entorno en absoluto -- el --env-file de abajo queda sin
# efecto aunque los env vars del proceso sean correctos (se puede confirmar
# con "docker exec --env-file .env.testing barber-app env", que SI mostraba
# barber_db_test, contra lo que Laravel realmente resolvia). Resultado real:
# la suite completa corrio contra la Atlas compartida con spark/ sin ningun
# error visible, y cada tearDown() de las Feature tests (Client::query()->
# delete(), Appointment::query()->delete(), etc.) borro datos reales.
# "config:clear" antes de cada corrida evita que esto se repita si el
# contenedor se reinicia entre una corrida y otra (propia o de otra sesion).
#
# "route:clear" existe por el mismo motivo pero para rutas: si una corrida
# anterior dejo bootstrap/cache/routes-v7.php cacheado (ver el bloque de
# route:cache al final de este script), un cambio en routes/*.php no se ve
# reflejado en la suite hasta limpiarlo -- encontrado 2026-09-09 al mover
# GET barbers/{barber} fuera de mobile.auth: el test nuevo seguia recibiendo
# 401 con la ruta ya corregida en el archivo.
#
# Uso: .\test.ps1  [argumentos extra para "php artisan test", ej. --filter=Loyalty]

docker exec barber-app php artisan config:clear | Out-Null
docker exec barber-app php artisan route:clear | Out-Null
docker exec --env-file .env.testing barber-app php artisan test @args

# Restaura el cache de config/rutas con el entorno REAL del contenedor
# (Atlas, via env_file: .env de docker-compose -- NO .env.testing, ese solo
# aplica al comando de arriba) al terminar la corrida, pase o falle.
#
# Sin esto, cada corrida de esta suite deja el servidor de desarrollo
# sirviendo sin config/route cache hasta el siguiente restart del
# contenedor: Laravel vuelve a parsear todo config/*.php y a registrar
# todas las rutas en cada request. Con el bind mount de este proyecto
# (Windows -> Docker via 9p/DrvFS, mas lento que un filesystem nativo de
# Linux) ese re-parseo por request es carisimo -- medido en vivo: /up pasa
# de ~0.3s a ~1.6s, /api/v1/dashboard de ~1.1s a ~3.8s. Encontrado
# 2026-09-09 tras varias corridas seguidas de esta suite en la misma
# sesion sin que nada restaurara el cache despues.
docker exec barber-app php artisan config:cache | Out-Null
docker exec barber-app php artisan route:cache | Out-Null
