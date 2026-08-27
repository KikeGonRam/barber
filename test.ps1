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
# Uso: .\test.ps1  [argumentos extra para "php artisan test", ej. --filter=Loyalty]

docker exec --env-file .env.testing barber-app php artisan test @args
