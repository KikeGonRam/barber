.PHONY: help env build up down restart logs shell migrate seed setup ps clean assets key storage optimize test validate audit

DC := docker compose

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

env: ## Crea .env desde .env.example si todavia no existe
	@test -f .env || cp .env.example .env

build: ## Construye las imagenes de Docker
	$(DC) build

up: ## Levanta los contenedores en segundo plano
	$(DC) up -d

down: ## Detiene y elimina los contenedores
	$(DC) down

restart: ## Reinicia los contenedores
	$(DC) restart

logs: ## Muestra logs de todos los servicios
	$(DC) logs -f

ps: ## Lista el estado de los contenedores
	$(DC) ps

shell: ## Abre terminal bash en app
	$(DC) exec app bash

key: ## Genera APP_KEY
	$(DC) exec app php artisan key:generate

storage: ## Crea enlace publico de storage
	$(DC) exec app php artisan storage:link

migrate: ## Ejecuta migraciones
	$(DC) exec app php artisan migrate

seed: ## Ejecuta seeders
	$(DC) exec app php artisan db:seed

assets: ## Instala dependencias frontend y compila assets
	npm install
	npm run build

optimize: ## Limpia cache de Laravel
	$(DC) exec app php artisan optimize:clear

test: ## Ejecuta pruebas automatizadas
	$(DC) exec app php artisan test

audit: ## Audita dependencias Composer
	$(DC) exec app composer audit

validate: ## Valida roles, vistas, Composer, Pint y build frontend
	$(DC) exec app php artisan validate:user-roles
	$(DC) exec app php artisan view:cache
	$(DC) exec app composer audit
	$(DC) exec app composer validate --strict
	$(DC) exec app ./vendor/bin/pint --test
	npm run build

setup: env build up key storage migrate seed assets optimize ## Configuracion inicial completa
	@echo "Proyecto listo en http://localhost:8000"
	@echo "Mailpit listo en http://localhost:8025"

clean: ## Baja contenedores y elimina volumenes anonimos
	$(DC) down --remove-orphans --volumes
