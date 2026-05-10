SAIL := ./vendor/bin/sail
COMPOSER_BOOTSTRAP := docker run --rm \
	-v "$(CURDIR):/var/www/html" \
	-w /var/www/html \
	laravelsail/php84-composer:latest \
	composer install --ignore-platform-reqs

.DEFAULT_GOAL := help
.PHONY: help setup up down restart shell ps logs test build fix migrate

help: ## Show available targets
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup: ## One-shot bootstrap (idempotent)
	@if [ ! -d vendor ]; then \
		echo "==> composer install (bootstrap via docker)"; \
		$(COMPOSER_BOOTSTRAP); \
	else \
		echo "==> vendor/ exists, skipping composer install"; \
	fi
	@if [ ! -f .env ]; then \
		echo "==> creating .env from .env.example"; \
		cp .env.example .env; \
	else \
		echo "==> .env exists, skipping"; \
	fi
	@if [ ! -f .env.testing ]; then \
		echo "==> creating .env.testing from .env.example"; \
		cp .env.example .env.testing; \
		sed -i.bak 's/^APP_ENV=.*/APP_ENV=testing/' .env.testing; \
		sed -i.bak 's/^DB_HOST=.*/DB_HOST=mysql.test/' .env.testing; \
		sed -i.bak 's/^DB_DATABASE=.*/DB_DATABASE=testing/' .env.testing; \
		rm -f .env.testing.bak; \
	else \
		echo "==> .env.testing exists, skipping"; \
	fi
	@echo "==> starting Sail containers"
	@$(SAIL) up -d
	@printf "==> waiting for MySQL "
	@for i in $$(seq 1 60); do \
		if $(SAIL) exec -T mysql mysqladmin ping --silent >/dev/null 2>&1; then \
			echo "ready"; \
			break; \
		fi; \
		printf "."; \
		sleep 1; \
		if [ $$i -eq 60 ]; then \
			echo " timeout"; \
			exit 1; \
		fi; \
	done
	@if grep -qE '^APP_KEY=base64:.+' .env; then \
		echo "==> APP_KEY exists in .env, skipping key:generate"; \
	else \
		echo "==> generating APP_KEY for .env"; \
		$(SAIL) artisan key:generate; \
	fi
	@if grep -qE '^APP_KEY=base64:.+' .env.testing; then \
		echo "==> APP_KEY exists in .env.testing, skipping key:generate"; \
	else \
		echo "==> generating APP_KEY for .env.testing"; \
		$(SAIL) artisan key:generate --env=testing --force; \
	fi
	@echo "==> running migrations"
	@$(SAIL) artisan migrate
	@if [ ! -d node_modules ]; then \
		echo "==> npm install"; \
		$(SAIL) npm install; \
	else \
		echo "==> node_modules/ exists, skipping npm install"; \
	fi
	@echo "==> setup complete"

up: ## Start Sail containers
	@$(SAIL) up -d

down: ## Stop Sail containers
	@$(SAIL) down

restart: down up ## Restart Sail containers

shell: ## Shell into the app container
	@$(SAIL) shell

ps: ## Show container status
	@$(SAIL) ps

logs: ## Tail container logs
	@$(SAIL) logs -f

test: ## Run the test suite
	@$(SAIL) composer test

build: ## Run all static analysis (csf + cs + sa + md)
	@$(SAIL) composer build

fix: ## Auto-fix code style (PHP CS Fixer + PHP CodeSniffer)
	@$(SAIL) composer csf-fix
	@$(SAIL) composer cs-fix

migrate: ## Run pending migrations
	@$(SAIL) artisan migrate
