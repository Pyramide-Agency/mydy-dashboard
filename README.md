# Personal Dashboard

Personal Dashboard — личная панель для задач и финансов.  
Фронтенд (Nuxt SPA) и бэкенд (Laravel API) работают в одном Docker‑контейнере, с PostgreSQL отдельно.

## Стек

- **Frontend**: Nuxt 4 (SPA), Vue 3, Tailwind CSS, shadcn‑nuxt (Reka UI)
- **Backend**: Laravel 11 (PHP 8.3), REST API
- **DB**: PostgreSQL 16
- **Infra**: Docker, Nginx, Supervisor

## Архитектура (кратко)

- Nuxt генерируется как **SPA** (`nuxt generate`) и раздаётся Nginx.
- Все запросы к `/api/*` проксируются в Laravel (PHP‑FPM).
- Всё это живёт в **одном** контейнере приложения.

## Локальная разработка

### Backend

```bash
cd back
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8000
```

### Frontend

```bash
cd front
npm install
npm run dev
```

### Старт обоих сервисов

```bash
./start.sh
```

## Деплой в Dokploy (один контейнер)

Проект уже содержит готовый Dockerfile для single‑container деплоя:

- `Dockerfile` — multi‑stage: Nuxt build → Composer → PHP‑FPM + Nginx
- `docker/nginx.conf` — роутинг `/api` → Laravel, всё остальное → Nuxt SPA
- `docker/entrypoint.sh` — миграции, кеши, запуск Supervisor

### Шаги в Dokploy

1. **Создай новый проект** и укажи репозиторий.
2. **Dockerfile path**: `Dockerfile`
3. **Expose port**: `80`
4. **Environment variables** (минимум):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=base64:...            # сгенерируй и вставь свой
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=personal_dashboard
DB_USERNAME=dashboard
DB_PASSWORD=your_password
ANTHROPIC_API_KEY=            # опционально
```

5. **PostgreSQL**:
   - В Dokploy добавь отдельный PostgreSQL сервис
   - Укажи те же `DB_*` параметры
   - DB host в переменных должен совпадать с именем сервиса (обычно `postgres`)

6. **Deploy** — при старте контейнер сам выполнит миграции.

### Важно

- `NUXT_PUBLIC_API_BASE` **не требуется** — в Dockerfile он фиксирован как `/api`.
- Приложение работает по одной доменной зоне: frontend и backend в одном контейнере.

## Переменные окружения (локально)

Пример лежит в `.env.example`. Для локального Docker‑запуска:

```bash
cp .env.example .env
```

## Локальный Docker запуск (опционально)

```bash
docker compose up --build
```

Откроется на `http://localhost` (порт по умолчанию 80).
