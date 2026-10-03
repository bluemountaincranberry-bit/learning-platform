ENV_FILE = config/docker.env
COMPOSE = docker compose --env-file $(ENV_FILE)

.PHONY: up down build restart ps logs app bash composer artisan migrate fresh seed node npm install e2e fistall

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

restart:
	$(COMPOSE) down
	$(COMPOSE) up -d

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs -f --tail=200

app:
	$(COMPOSE) exec app php -v

bash:
	$(COMPOSE) exec app bash

composer:
	$(COMPOSE) exec app composer $(ARGS)

artisan:
	$(COMPOSE) exec app php artisan $(ARGS)

test:
	$(COMPOSE) exec app php artisan test $(ARGS)

migrate:
	$(COMPOSE) exec app php artisan migrate

fresh:
	$(COMPOSE) exec app php artisan migrate:fresh

seed:
	$(COMPOSE) exec app php artisan db:seed

node:
	$(COMPOSE) exec node sh

npm:
	$(COMPOSE) exec node npm $(ARGS)

e2e:
	$(COMPOSE) exec -T browser npm run e2e

install:
	$(COMPOSE) up -d
	$(COMPOSE) exec app composer install
	$(COMPOSE) exec app sh -lc "[ -f .env ] || cp .env.example .env"
	$(COMPOSE) exec app php artisan key:generate
	$(COMPOSE) exec app php artisan migrate
