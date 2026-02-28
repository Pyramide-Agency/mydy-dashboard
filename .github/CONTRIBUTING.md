# Contributing to MYDY Dashboard

Thank you for your interest in contributing! Here's how to do it properly.

---

## Before You Start

- Check [open issues](https://github.com/Pyramide-Agency/mydy-dashboard/issues) to avoid duplicates
- For large changes, open an issue first to discuss the approach
- Make sure you've read the [LICENSE](../LICENSE) — contributions are accepted under the same terms

---

## How to Contribute

### 1. Fork & Clone

```bash
git clone https://github.com/your-username/mydy-dashboard.git
cd mydy-dashboard
```

### 2. Set Up Locally

```bash
# Backend
cd back && cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed

# Frontend
cd ../front && npm install

# Start both
cd .. && ./start.sh
```

### 3. Create a Branch

```bash
git checkout -b fix/your-fix-name
# or
git checkout -b feat/your-feature-name
```

### 4. Make Your Changes

- Keep changes focused — one fix or feature per PR
- Follow the existing code style (Laravel conventions for back, Vue 3 Composition API for front)
- Test your changes locally before submitting

### 5. Commit

Use clear, conventional commit messages:

```
fix: correct timezone in deadline notifications
feat: add weekly summary to Telegram bot
docs: update local setup instructions
```

### 6. Open a Pull Request

- Use the PR template that appears automatically
- Describe what you changed and why
- Link the related issue if applicable

---

## What's Welcome

- Bug fixes
- Performance improvements
- UI/UX improvements
- Documentation fixes
- Translations (new languages beyond EN/RU)

## What's Not Welcome

- Breaking changes without prior discussion
- Adding heavy dependencies without justification
- Changes that remove attribution or license notices

---

## Attribution

By submitting a contribution, you agree that your changes will be licensed under the same [LICENSE](../LICENSE) as this project. You will be credited in the commit history.

---

*Questions? Open an issue or reach out to [@odilovicc](https://github.com/odilovicc)*
