# Vektron Rebrand & Design System

**Date:** 2026-03-08

## Summary
Rename project from "MYDY Dashboard" to **Vektron** and introduce a centralized config-driven design system with theme switching.

## Name
- **Vektron** — vector + control, precision, system

## Color System
Two theme configs (accent color):
- `vektron-violet` — primary accent: Violet/Indigo (#7C3AED / #818CF8)
- `vektron-emerald` — primary accent: Emerald (#10B981 / #34D399)

Each config supports **dark + light** mode (4 combinations total).

## Typography
Two font configs:
- **Syne config** — headings: Syne, body: DM Sans
- **Cabinet config** — headings: Cabinet Grotesk, body: Instrument Sans

## Config Architecture
```
configs/
  themes/
    vektron-violet.css   # CSS custom properties for violet theme
    vektron-emerald.css  # CSS custom properties for emerald theme
```

CSS custom properties cover: colors, fonts, border-radius, shadows.
Settings page exposes: theme picker (violet/emerald) + mode (dark/light) + font config.

## Implementation Steps
1. Create `configs/themes/` with two CSS files
2. Update Tailwind config to use CSS variables
3. Rename all "MYDY" references to "Vektron" (config.json, app.vue, layouts, etc.)
4. Update Settings page with theme/font switchers
5. Apply new fonts via Google Fonts / Fontsource
