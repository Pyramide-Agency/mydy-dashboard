# Vektron — Backend API

Laravel 11 REST API for the Vektron personal dashboard.

## Stack

- **PHP 8.4** + Laravel 11
- **PostgreSQL** with schema-per-user multitenancy (`user_N` schemas)
- **pgvector** — AI memory vector search
- **Laravel Sanctum** — token-based auth
- **Anthropic / OpenAI / Groq** — AI providers

## Architecture

### Multi-tenant (Schema-per-user)
Each registered user gets their own PostgreSQL schema (`user_1`, `user_2`, ...). The `TenantMiddleware` sets `search_path = user_{id}` on every authenticated request — no cross-user data leakage, no `user_id` columns in tenant tables.

### Key Services
- `CanvasService` — Canvas LMS sync
- `MemoryService` — AI memory via Jina embeddings + pgvector
- `OnboardingService` — provision new user schema + seed defaults
- `TenantMigrationService` — create/migrate individual user schemas

## Running locally

```bash
cp .env.example .env
# Edit: DB_*, APP_KEY, TELEGRAM_BOT_TOKEN, etc.

php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

API available at `http://localhost:8000/api`

Default credentials: `admin@mydy.local` / `secret`

## Key Routes

```
POST   /api/auth/login
POST   /api/auth/register
GET    /api/auth/me

GET    /api/finance/transactions
POST   /api/finance/transactions
GET    /api/finance/categories

GET    /api/boards
GET    /api/kanban/{boardId}/tasks

GET    /api/lms/courses
GET    /api/lms/assignments
POST   /api/lms/sync

GET    /api/ai/conversations
POST   /api/ai/send-message
GET    /api/ai/memories

POST   /api/work/checkin
POST   /api/work/checkout
GET    /api/work/sessions

GET    /api/settings
POST   /api/settings

POST   /api/telegram/webhook
POST   /api/telegram/register
```

## Artisan Commands

```bash
php artisan deadline:notify          # Telegram deadline reminders
php artisan lms:deadline-notify      # LMS deadline notifications
```
