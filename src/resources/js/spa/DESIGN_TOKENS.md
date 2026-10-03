# SPA design tokens

Colors and radii are centralized in **`resources/css/app.css`** as CSS variables (`:root`) and exposed via **Tailwind theme** in `tailwind.config.js`.

## CSS variables (`:root`)

| Variable | Usage |
|----------|--------|
| `--spa-primary`, `--spa-primary-hover` | Primary actions, links |
| `--spa-danger`, `--spa-danger-bg` | Errors, destructive |
| `--spa-success`, `--spa-success-bg`, `--spa-success-fg` | Success state, learned |
| `--spa-warning`, `--spa-warning-bg`, `--spa-warning-border`, `--spa-warning-fg` | Warnings, recommendations |
| `--spa-muted`, `--spa-muted-light` | Secondary text |
| `--spa-surface`, `--spa-surface-alt` | Backgrounds (cards, tables) |
| `--spa-border`, `--spa-border-strong` | Borders |
| `--spa-fg`, `--spa-fg-secondary` | Text (primary / secondary) |
| `--spa-radius`, `--spa-radius-lg` | Border radius |

## Tailwind classes (semantic)

Use these in Vue templates instead of raw Tailwind colors:

- **Primary:** `bg-primary`, `hover:bg-primary-hover`, `text-primary`
- **Danger:** `text-danger`, `bg-danger-bg`
- **Success:** `text-success`, `bg-success`, `bg-success-bg`, `text-success-fg`
- **Warning:** `text-warning`, `bg-warning-bg`, `border-warning-border`, `text-warning-fg`
- **Muted:** `text-muted`, `text-muted-light`
- **Surface:** `bg-surface`, `bg-surface-alt`
- **Border:** `border-border`, `border-border-strong`
- **Foreground:** `text-fg`, `text-fg-secondary`
- **Radius:** `rounded-spa`, `rounded-spa-lg`

## Changing the theme

1. Edit `resources/css/app.css` — change the hex values in `:root`.
2. For dark mode later: add a `.dark` (or `[data-theme="dark"]`) block and override the same variables.
