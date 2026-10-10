ENV_FILE = config/docker.env
COMPOSE = docker compose --env-file $(ENV_FILE)

.PHONY: up down build restart ps logs app bash composer artisan migrate fresh seed node npm install e2e fistall skills-update skills-restore wt-test wt-build workers-restart mobile-https

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

skills-update:
	npx -y skills@latest update --project -y

skills-restore:
	npx -y skills@latest experimental_install

# Worktree-safe commands: docker mounts the main checkout, so from a git
# worktree use these to run code from *this* worktree (vendor/node_modules
# are borrowed from the main checkout).
MAIN_ROOT := $(shell dirname "$$(git rev-parse --path-format=absolute --git-common-dir)")
WT_RUN = docker compose --env-file $(MAIN_ROOT)/$(ENV_FILE) -f $(MAIN_ROOT)/docker-compose.yml --project-directory $(MAIN_ROOT) run --rm --no-deps -v $(CURDIR)/src:/var/www/html

wt-test:
	$(WT_RUN) -v $(MAIN_ROOT)/src/vendor:/var/www/html/vendor app php artisan test $(ARGS)

wt-build:
	$(WT_RUN) -v $(MAIN_ROOT)/src/node_modules:/var/www/html/node_modules node npm run build

workers-restart:
	$(COMPOSE) restart horizon

mobile-https:
	./scripts/setup-mobile-https.sh
