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

### Шаг 1 — Создать PostgreSQL сервис

1. Dokploy → **New Service → Database → PostgreSQL**
2. Задай имя сервиса, например `postgres`
3. Запомни **имя сервиса** — оно будет `DB_HOST`
4. Запомни `Database`, `Username`, `Password` — они задаются при создании

### Шаг 2 — Создать сервис приложения

1. Dokploy → **New Service → Application**
2. Источник: **GitHub** (укажи репозиторий и ветку `main`)
3. Dokploy автоматически найдёт `Dockerfile` в корне репозитория

### Шаг 3 — Добавить том (Volume) для хранилища

Это важно: в `storage/` хранятся авто-сгенерированный `APP_KEY` и маркер первого запуска.

В настройках сервиса → **Volumes**:

| Host path или Volume name | Container path |
|---|---|
| `dashboard_storage` | `/var/www/html/storage` |

> Если не добавить Volume, APP_KEY будет пересоздаваться при каждом рестарте и сессии будут сбрасываться.

### Шаг 4 — Задать переменные окружения

В настройках сервиса → **Environment Variables**:

```env
# Приложение
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# База данных
DB_CONNECTION=pgsql
DB_HOST=postgres          # имя сервиса из Шага 1
DB_PORT=5432
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Дополнительно
SESSION_DRIVER=file
CACHE_STORE=file
LOG_CHANNEL=stderr
```

**Опционально:**

```env
# APP_KEY генерируется автоматически при первом запуске и сохраняется в volume.
# Если хочешь стабильный ключ (рекомендуется) — сгенерируй и вставь сюда:
# Для получения: php artisan key:generate --show  (или посмотри логи первого запуска)
APP_KEY=base64:...

# Для AI-функций (анализ расходов, чат-советник)
ANTHROPIC_API_KEY=sk-ant-...

# Если настраиваешь Telegram-бот
TELEGRAM_BOT_TOKEN=...
```

> `APP_KEY` не обязателен — при первом старте контейнер сгенерирует его сам, сохранит в volume и напечатает в логах. Скопируй оттуда и вставь в переменные для надёжности.

### Шаг 5 — Настроить Network (связь с базой)

В настройках сервиса приложения → **Network**:
- Убедись, что сервис находится в одной сети с PostgreSQL
- В Dokploy это обычно происходит автоматически внутри одного проекта

### Шаг 6 — Deploy

1. Нажми **Deploy**
2. Следи за логами сборки — Nuxt + Composer занимают 2–4 минуты
3. После успешной сборки контейнер стартует и:
   - Дождётся готовности PostgreSQL
   - Применит миграции
   - На **первом запуске** автоматически заполнит базу начальными данными
4. Healthcheck `GET /api/health` проверяется каждые 15 секунд — контейнер станет `healthy` примерно через 1–2 минуты после старта

### Шаг 7 — Первый вход

- Открой `https://your-domain.com`
- Пароль по умолчанию: **`secret`**
- Сразу смени пароль в **Настройках**

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
