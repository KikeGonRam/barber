.PHONY: help build up down restart logs shell migrate seed setup ps clean

DC := docker compose

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

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
	$(DC) exec -u laravel app bash

migrate: ## Ejecuta migraciones
	$(DC) exec app php artisan migrate

seed: ## Ejecuta seeders
	$(DC) exec app php artisan db:seed

setup: build up migrate ## Configuracion inicial completa
	@echo "Proyecto listo en http://localhost:8000"
	@echo "Mailpit listo en http://localhost:8025"
	@echo "Adminer listo en http://localhost:8080"

clean: ## Baja contenedores y elimina volumenes anonimos
	$(DC) down --remove-orphans --volumes
