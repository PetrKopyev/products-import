# Импорт/Экспорт товаров

Полноценное приложение для импорта товаров из `.xlsx` с асинхронной обработкой через очередь.

- **Backend:** PHP 8.3, Slim 4, Doctrine ORM 3 (миграции), Symfony Messenger + RabbitMQ, JWT-аутентификация, PostgreSQL
- **Frontend:** Angular 19 (standalone, Vite-based dev-server), NgRx Store + Effects, Angular Material, TypeScript `strict`
- **Инфраструктура:** Docker Compose (multi-stage образы), GitHub Actions

## Страницы

| Страница | Что умеет |
|----------|-----------|
| `/login` | Вход по email/паролю, JWT |
| `/import` | Загрузка `.xlsx`, постановка задачи в очередь, **polling прогресса**, отчёт об ошибках по строкам |
| `/products` | Список: серверная пагинация, поиск по `name`, фильтр по диапазону `price` (NgRx) |
| `/products/:id` | Карточка: поля, доп. атрибуты, изображения |

## Быстрый старт (Docker)

```bash
cp .env.example .env   # при необходимости поправьте переменные
make up                # сборка и запуск всех сервисов
```

Откройте http://localhost (порт настраивается `HTTP_PORT`).

При старте контейнеры `app`/`worker` автоматически накатывают миграции и сидеры.
Демо-доступ (сидер): **admin@example.com / admin123** (задаётся `ADMIN_EMAIL`/`ADMIN_PASSWORD`).

Сервисы: `app` (php-fpm + API), `worker` (консьюмер RabbitMQ), `db` (PostgreSQL 16),
`rabbitmq` (брокер, management UI на `:15672`, guest/guest), `nginx` (SPA + прокси `/api` и `/images`).

RabbitMQ management: http://localhost:15672

## Локальная разработка (без Docker)

```bash
# backend: SQLite + синхронный транспорт импорта (ext-amqp не нужен)
make dev-backend        # http://localhost:8080 (миграции/сидеры запускаются вручную:
                        #  cd backend && php bin/console.php app:migrate && php bin/console.php db:seed)

# frontend: дев-сервер с прокси /api и /images на :8080
cd frontend && npm install && npm start   # http://localhost:4200
```

> Если `composer install` ругается на `ext-amqp` — это нормально для локальной машины
> (амqp-транспорт нужен только в docker): `composer install --ignore-platform-req=ext-amqp`.
> Асинхронный режим с реальным RabbitMQ локально: `make dev-worker` с `MESSENGER_TRANSPORT_DSN=amqp://...`.

## Тесты и качество

```bash
make test-backend   # PHPUnit: unit + integration + API/e2e (SQLite, синхронный импорт)
make stan           # PHPStan level 5
make cs-check       # PHP CS Fixer (dry-run)
make test-frontend  # production-сборка Angular
make e2e            # Cypress e2e против поднятого стека (make up)
```

## API

Интерактивная документация (Swagger UI): **/api/docs** (OpenAPI-спека: `/api/openapi.yaml`, файл `backend/openapi.yaml`).

Кратко (все endpoint'ы кроме `/api/auth/login`, `/api/health` и `/images/*` требуют `Authorization: Bearer <token>`):

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/api/auth/login` | Вход → `{token, user}` |
| GET | `/api/auth/me` | Текущий пользователь |
| GET | `/api/health` | Health check |
| POST | `/api/import` | Загрузка `.xlsx` → 202 `{jobId, statusUrl}`; rate limit 5/мин/IP |
| GET | `/api/import/{id}` | Статус задачи: `status`, `total`, `processed`, `errors[]` |
| GET | `/api/products?page=&limit=&name=&price_min=&price_max=` | Список с пагинацией и фильтрами |
| GET | `/api/products/{id}` | Карточка товара |

## Как устроен импорт

1. Контроллер валидирует файл (MIME, расширение, размер), сохраняет в storage и создаёт `ImportJob`.
2. В RabbitMQ отправляется сообщение `ImportProducts` через **Symfony Messenger** (обработка в `worker`).
3. `ImportService`: парсинг построчно (PhpSpreadsheet, маппинг по заголовкам) → upsert по `external_code`
   (атомарная транзакция «товар + атрибуты + изображения» на строку).
4. Изображения скачиваются пулом запросов в `storage/images/` (dedup по md5 URL);
   ошибка скачивания попадает в отчёт, но не валит товар.
5. Невалидные строки не прерывают импорт — собираются в `import_jobs.errors` и отдаются клиенту.
6. `discount` в % вычисляется при импорте: `(Цена продажи − Закупочная) / Цена продажи × 100`
   (расчёт в целых копейках, без float-ошибок; формат цен в файле — «1320,00»).

Столбцы `Доп. поле: *` → `product_attributes` (key–value, пустые пропускаются);
`Доп. поле: Ссылки на фото` (URL через запятую) → `product_images`.

## Переменные окружения

См. [.env.example](.env.example) — все переменные с комментариями
(DATABASE_URL, JWT_SECRET, MESSENGER_TRANSPORT_DSN, лимиты загрузки и rate limit, данные админа).

## Структура

```
backend/            Slim + Doctrine API
  config/           settings, DI-контейнер, маршруты
  src/Domain/Entity products, product_attributes, product_images, import_jobs, users
  src/Service/      XlsxParser, ImportService, ImageDownloadService, AuthService, ProductService
  src/Repository/   ProductRepository, ImportJobRepository, UserRepository
  src/Migrations/   Doctrine-миграция (PostgreSQL)
  src/Message(H)    Symfony Messenger: ImportProducts + handler
  src/Console/      app:migrate, db:seed, messenger:consume, import:file
  tests/            PHPUnit: Unit, Integration (CRUD), Api (e2e-импорт, auth, rate limit)
  fixtures/         import-example.xlsx (файл из задания)
frontend/           Angular SPA
  src/app/models/   Product, ProductAttribute, ProductImage, ImportJob, User
  src/app/services/ ProductService, ImportService, AuthService
  src/app/store/    NgRx: actions/effects/reducer/selectors (products)
  src/app/pages/    login, import, product-list, product-detail (lazy)
  src/app/guards|interceptors/  authGuard, authInterceptor (JWT + 401/5xx)
  cypress/e2e/      e2e-спеки
docker-compose.yml  app, worker, db, rabbitmq, nginx
.github/workflows/  CI: backend (phpstan/cs-fixers/phpunit), frontend build, docker build
```

## Примечания

- Имена колонок маппятся по заголовкам первой строки (поддерживаются оба формата разделителя цен).
- `MESSENGER_TRANSPORT_DSN=sync://` — синхронный режим (локальная разработка/тесты);
  `amqp://...` — асинхронный через RabbitMQ (docker, прод).
- Повторный импорт того же файла обновляет существующие товары (upsert по `external_code`), дубликаты не создаются.
