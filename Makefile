.DEFAULT_GOAL := help
COMPOSE := docker compose
PHP := $(COMPOSE) exec php

##@ Setup

.PHONY: install
install: ssl build up ## Full first-time setup (SSL + build + start)

.PHONY: ssl
ssl: ## Generate self-signed SSL certificate
	@chmod +x docker/nginx/generate-ssl.sh
	@bash docker/nginx/generate-ssl.sh

.PHONY: build
build: ## Build Docker images
	$(COMPOSE) build --pull

##@ Runtime

.PHONY: up
up: ## Start all services in the background
	$(COMPOSE) up -d

.PHONY: down
down: ## Stop and remove containers (keeps volumes)
	$(COMPOSE) down

.PHONY: restart
restart: down up ## Restart all services

.PHONY: ps
ps: ## Show running containers
	$(COMPOSE) ps

.PHONY: logs
logs: ## Follow logs for all services
	$(COMPOSE) logs -f

##@ Symfony / PHP

.PHONY: bash
bash: ## Open a shell inside the PHP container
	$(COMPOSE) exec php sh

.PHONY: cc
cc: ## Clear the Symfony cache
	$(PHP) php bin/console cache:clear

.PHONY: migrate
migrate: ## Run database migrations
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: diff
diff: ## Generate a Doctrine migration diff
	$(PHP) php bin/console doctrine:migrations:diff

.PHONY: schema
schema: ## Update the database schema directly (dev only)
	$(PHP) php bin/console doctrine:schema:update --force

.PHONY: console
console: ## Run an arbitrary bin/console command  (e.g. make console CMD="debug:router")
	$(PHP) php bin/console $(CMD)

.PHONY: composer
composer: ## Run Composer inside the container  (e.g. make composer CMD="require foo/bar")
	$(PHP) composer $(CMD)

##@ Help

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)
