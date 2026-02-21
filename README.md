# Personal Dashboard

Личный дашборд с канбан-доской, трекером расходов и AI-советником.

**Стек:** Laravel 11 · Nuxt 4 · PostgreSQL · Claude API · Telegram Bot

---

## Возможности

- **Канбан** — доски, колонки, перетаскивание задач, архив
- **Финансы** — расходы по категориям, графики (день / неделя / месяц), история
- **AI** — анализ расходов за день и чат-советник на базе Claude
- **Telegram** — быстрое добавление расходов через бота (`/add 500 кофе`)
- **Один пользователь** — вход по паролю, смена пароля в настройках

---

## Деплой в Dokploy

### Шаг 1 — Создать PostgreSQL

Dokploy → **New Service → Database → PostgreSQL**

Запомни **имя сервиса** (будет `DB_HOST`), `Database`, `Username`, `Password`.

### Шаг 2 — Создать сервис приложения

Dokploy → **New Service → Application** → источник **GitHub** → выбери репо и ветку `main`.

Dokploy автоматически найдёт `Dockerfile` в корне репозитория.

### Шаг 3 — Задать переменные окружения

В настройках сервиса → **Environment Variables**:

```env
APP_URL=https://your-domain.com

DB_HOST=postgres           # имя сервиса из Шага 1
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

**Опционально:**

```env
ANTHROPIC_API_KEY=sk-ant-...   # для AI-функций
TELEGRAM_BOT_TOKEN=...         # для Telegram-бота
APP_KEY=base64:...             # посмотри в логах после первого деплоя
```

> `APP_KEY` генерируется автоматически — можно добавить позже из логов для стабильности.

### Шаг 4 — Deploy

Нажми **Deploy**. Сборка займёт 2–4 минуты.

После старта контейнер сам дождётся PostgreSQL, применит миграции и заполнит базу данными по умолчанию.

**Пароль по умолчанию: `secret`** — смени сразу в Настройках.

---

## Локальная разработка

**Требования:** PHP 8.4, Composer, Node 20, PostgreSQL

```bash
# 1. Клонировать
git clone <repo> && cd personal-dashboard

# 2. Настроить бэкенд
cd back
cp .env.example .env
# Отредактировать .env: DB_*, APP_KEY
php artisan key:generate
php artisan migrate:fresh --seed
cd ..

# 3. Настроить фронтенд
cd front && npm install && cd ..

# 4. Запустить оба сервера
./start.sh
```

- Frontend: http://localhost:3000
- Backend API: http://localhost:8000/api

**Через Docker Compose (локально):**

```bash
# Скопировать и заполнить .env
cp .env.example .env
# Обязательно задать: DB_PASSWORD, APP_KEY (php artisan key:generate --show)

docker compose up --build
```

---

## Переменные окружения (справочник)

| Переменная | Обязательна | Описание |
|---|---|---|
| `APP_URL` | Да | Публичный URL (`https://your-domain.com`) |
| `DB_HOST` | Да | Хост PostgreSQL (имя сервиса в Dokploy) |
| `DB_DATABASE` | Да | Имя базы данных |
| `DB_USERNAME` | Да | Пользователь БД |
| `DB_PASSWORD` | Да | Пароль БД |
| `APP_KEY` | Нет* | Ключ шифрования. Автогенерируется при первом старте |
| `ANTHROPIC_API_KEY` | Нет | Claude API ключ для AI-функций |
| `TELEGRAM_BOT_TOKEN` | Нет | Токен Telegram-бота |

*Рекомендуется задать вручную после первого запуска (ключ будет в логах).

---

## Структура проекта

```
├── back/           Laravel 11 API
├── front/          Nuxt 4 SPA (shadcn-vue, Tailwind v3)
├── docker/
│   ├── entrypoint.sh
│   ├── nginx.conf
│   └── supervisord.conf
├── Dockerfile      Multi-stage: Nuxt build → Composer → PHP-FPM + Nginx
├── docker-compose.yml  Для локальной разработки
└── start.sh        Запуск dev-серверов
```

## Telegram Bot

1. Создай бота через [@BotFather](https://t.me/BotFather), получи токен
2. В настройках дашборда → **Telegram** — вставь токен и нажми "Подключить"
3. Webhook зарегистрируется автоматически

Команды бота:
- `/add 500 кофе` — добавить расход
- `/today` — расходы за сегодня
- `/help` — список команд
