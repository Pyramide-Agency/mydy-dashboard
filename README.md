<p align="center">
  <img src="./front/public/mydy-logo-dark.svg" height="72" alt="MYDY Dashboard" />
</p>

<h1 align="center">MYDY DASHBOARD</h1>

<p align="center">
  Личный дашборд с канбан-доской, трекером расходов и AI-советником.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel&logoColor=white" />
  <img src="https://img.shields.io/badge/Nuxt-4-00DC82?style=flat-square&logo=nuxt.js&logoColor=white" />
  <img src="https://img.shields.io/badge/PostgreSQL-16-4169E1?style=flat-square&logo=postgresql&logoColor=white" />
  <img src="https://img.shields.io/badge/Claude_API-Anthropic-6B46C1?style=flat-square" />
  <img src="https://img.shields.io/badge/Telegram_Bot-2CA5E0?style=flat-square&logo=telegram&logoColor=white" />
</p>

---

## Возможности

| Модуль | Описание |
|---|---|
| **Канбан** | Доски, колонки, перетаскивание задач, архив |
| **Финансы** | Расходы по категориям, графики (день / неделя / месяц), история |
| **AI** | Анализ расходов за день и чат-советник на базе Claude |
| **Telegram** | Быстрое добавление расходов через бота (`/add 500 кофе`) |
| **Авторизация** | Один пользователь, вход по паролю, смена пароля в настройках |

---

## Деплой в Dokploy
[Посмотрите гайд](https://www.youtube.com/watch?v=EaMZUBrVKag)
[![Смотреть видео-гайд](https://img.youtube.com/vi/EaMZUBrVKag/maxresdefault.jpg)](https://www.youtube.com/watch?v=EaMZUBrVKag)

### Шаг 1 — Создать PostgreSQL

Dokploy → **New Service → Database → PostgreSQL**

Запомни **имя сервиса** (будет `DB_HOST`), `Database`, `Username`, `Password`.

### Шаг 2 — Создать сервис приложения

Dokploy → **New Service → Application** → источник **GitHub** → выбери репо и ветку `main`.

Dokploy автоматически найдёт `Dockerfile` в корне репозитория.

### Шаг 3 — Задать переменные окружения

В настройках сервиса → **Environment Variables**:

```env
# Обязательные
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=postgres           # имя сервиса из Шага 1
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Опциональные
ANTHROPIC_API_KEY=sk-ant-...   # для AI-функций
TELEGRAM_BOT_TOKEN=...         # для Telegram-бота
APP_KEY=base64:...             # можно добавить из логов после первого деплоя
```

> `APP_KEY` генерируется автоматически при первом старте — рекомендуется вынести в переменные для стабильности.

### Шаг 4 — Deploy

Нажми **Deploy**. Сборка займёт 2–4 минуты.

После старта контейнер сам дождётся PostgreSQL, применит миграции и заполнит базу данными по умолчанию.

> **Пароль по умолчанию: `secret`** — смени сразу в Настройках.

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

**Через Docker Compose:**

```bash
cp .env.example .env
# Задать: DB_PASSWORD, APP_KEY (php artisan key:generate --show)

docker compose up --build
```

---

## Переменные окружения

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
├── back/               Laravel 11 API
├── front/              Nuxt 4 SPA (shadcn-vue, Tailwind v3)
├── docker/
│   ├── entrypoint.sh
│   ├── nginx.conf
│   └── supervisord.conf
├── Dockerfile          Multi-stage: Nuxt build → Composer → PHP-FPM + Nginx
├── docker-compose.yml  Для локальной разработки
└── start.sh            Запуск dev-серверов
```

---

## Telegram Bot

1. Создай бота через [@BotFather](https://t.me/BotFather), получи токен
2. В настройках дашборда → **Telegram** — вставь токен и нажми «Подключить»
3. Webhook зарегистрируется автоматически

**Команды:**

| Команда | Описание |
|---|---|
| `/add 500 кофе` | Добавить расход |
| `/today` | Расходы за сегодня |
| `/help` | Список команд |
