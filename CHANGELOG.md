# Changelog

All notable changes to Vektron are documented here.

---

## [Unreleased] — feat/freelancer-tracker

### Added
- **Freelance Tracker** — full time tracking module for freelancers (web only, no TMA)
  - Create client projects with name, color, and optional deadline
  - Server-side timer with start / pause / resume / stop — survives browser close/reload
  - Manual session entry for retroactive logging
  - Session history table with week / month / all filters and per-project filter
  - Weekly and monthly stats with CSS bar chart per project
  - CSV export filtered by project and date range (compatible with Excel / Google Sheets)
  - Active timer widget on the main dashboard with live counter and quick Stop button
  - Sidebar: Work section converted to expandable menu with Check-in and Projects sub-items
  - Full EN / RU localization (`freelance.*` i18n keys)
- New backend: `FreelanceController`, `FreelanceProject` model, `FreelanceSession` model
- New migrations: `freelance_projects`, `freelance_sessions` tables
- New frontend: `useFreelance.ts` composable, `/freelance` page

---

## [1.4.0] — Nutrition Module

### Added
- Nutrition tracking module (meal logs, daily summaries, nutrition profiles)
- AI photo analysis for meals via Claude Vision API
- Telegram commands: `/meal [kcal] [name]`
- TMA tab: Nutrition (8th tab)
- Sidebar: expandable Nutrition section (Today / History / Profile)

---

## [1.3.0] — Canvas LMS Integration

### Added
- Full Canvas LMS integration (courses, assignments, submissions, announcements, calendar, grades)
- Deadline Telegram notifications (24h / 3h / 1h before)
- Pages: `/lms`, `/lms/assignments`, `/lms/calendar`, `/lms/course/[id]`
- Dashboard widget for upcoming deadlines (shown when LMS is configured)

---

## [1.2.0] — AI Memory (pgvector)

### Added
- AI Memory system: stores personal facts as vector embeddings (Jina AI + pgvector)
- Groq-powered fact extraction from conversations
- 3-column advisor layout: conversations | chat | memory panel
- Routes: `GET/DELETE /api/ai/memories`, `DELETE /api/ai/memories/{id}`

---

## [1.1.0] — Multi-Tenant Auth & Onboarding

### Added
- Schema-per-tenant architecture (`user_N` PostgreSQL schemas)
- Registration with automatic schema provisioning
- 5-step onboarding flow (Welcome, Language/Currency, Features, Telegram, Done)
- `usePlan.ts` composable for plan-based feature gates
- Landing page with pricing section and FAQ

---

## [1.0.0] — Initial Release

### Added
- Kanban board with drag-and-drop, archive, and multi-board support
- Finance tracker with categories, charts, and history
- AI Financial Advisor (Claude / OpenAI / Groq) with streaming and conversation history
- Work Time Tracker with iOS Shortcuts webhook
- Telegram Bot + Telegram Mini App (TMA)
- Two themes: Violet (Syne + DM Sans) and Emerald (Cabinet Grotesk + Instrument Sans), dark/light mode
- EN / RU localization
- Laravel 11 + Nuxt 4 + PostgreSQL stack
