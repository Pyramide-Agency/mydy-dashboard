# Security Policy

## Supported Versions

This is a personal dashboard project. Only the latest version on the `main` branch receives security fixes.

| Version | Supported |
|---|---|
| `main` (latest) | ✅ |
| Older forks | ❌ |

---

## Reporting a Vulnerability

**Please do not report security vulnerabilities via public GitHub issues.**

If you discover a security vulnerability, report it privately:

1. Go to the [Security tab](https://github.com/Pyramide-Agency/mydy-dashboard/security) → **"Report a vulnerability"**
2. Or contact the author directly: [@odilovicc](https://github.com/odilovicc)

Please include:
- A description of the vulnerability
- Steps to reproduce
- Potential impact
- (Optional) A suggested fix

---

## What to Report

- Authentication bypass
- SQL injection or data leakage
- API token exposure
- XSS vulnerabilities in the frontend
- Insecure webhook handling (Telegram, iOS Shortcuts)

---

## Response Time

I'll acknowledge the report within **72 hours** and aim to release a fix within **14 days** depending on severity.

---

## Scope

This project is self-hosted. Vulnerabilities in your own deployment environment (misconfigured server, exposed `.env`, etc.) are outside the scope of this policy.
