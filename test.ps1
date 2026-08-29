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
# Uso: .\test.ps1  [argumentos extra para "php artisan test", ej. --filter=Loyalty]

docker exec barber-app php artisan config:clear | Out-Null
docker exec --env-file .env.testing barber-app php artisan test @args
