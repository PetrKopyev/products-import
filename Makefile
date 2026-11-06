.PHONY: help up down build logs migrate seed test test-backend test-frontend stan cs cs-check dev-backend dev-worker dev-frontend e2e

COMPOSE = docker compose
BACKEND = backend
FRONTEND = frontend

help: ## Список команд
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Поднять весь стек в docker
	$(COMPOSE) up -d --build

down: ## Остановить стек
	$(COMPOSE) down -v

build: ## Собрать образы
	$(COMPOSE) build

logs: ## Логи всех сервисов
	$(COMPOSE) logs -f

migrate: ## Накатить миграции (в контейнере app)
	$(COMPOSE) exec app php bin/console.php app:migrate

seed: ## Запустить сидеры (в контейнере app)
	$(COMPOSE) exec app php bin/console.php db:seed

test: test-backend test-frontend ## Все тесты

test-backend: ## PHPUnit: unit + integration + API/e2e (SQLite, синхронный транспорт)
	cd $(BACKEND) && composer install --no-interaction --ignore-platform-req=ext-amqp && ./vendor/bin/phpunit

test-frontend: ## Сборка фронта как проверка
	cd $(FRONTEND) && npm ci && npm run build

stan: ## PHPStan level 5
	cd $(BACKEND) && ./vendor/bin/phpstan analyse --no-progress

cs: ## PHP CS Fixer — исправить стиль
	cd $(BACKEND) && ./vendor/bin/php-cs-fixer fix --verbose

cs-check: ## PHP CS Fixer — проверка без изменений
	cd $(BACKEND) && ./vendor/bin/php-cs-fixer fix --dry-run --diff

# ---- Локальная разработка без docker ----

dev-backend: ## Локальный backend: SQLite + синхронный импорт (http://localhost:8080)
	cd $(BACKEND) && DATABASE_URL=sqlite:///$$(pwd)/storage/dev.db \
		MESSENGER_TRANSPORT_DSN=sync:// IMAGE_STORAGE_PATH=storage \
		php -S localhost:8080 -t public

dev-worker: ## Локальный консьюмер очереди (только с RabbitMQ в MESSENGER_TRANSPORT_DSN)
	cd $(BACKEND) && php bin/console.php messenger:consume

dev-frontend: ## Локальный frontend с прокси /api и /images на :8080 (http://localhost:4200)
	cd $(FRONTEND) && npm start

e2e: ## Cypress e2e против поднятого стека (baseUrl из CYPRESS_BASE_URL или http://localhost)
	cd $(FRONTEND) && npx cypress run
