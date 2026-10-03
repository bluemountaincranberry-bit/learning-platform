# Rewrite: investor readiness и operational hardening

## Цель

Сделать техническое состояние проекта проверяемым для инвестора, инженера и
будущей эксплуатации.

## Scope

- CI/CD, quality gates и security scanning.
- Health checks, structured logs, traces и queue operations.
- AI token/cost/quality dashboards.
- Secrets, permissions, backups, restore и migration policy.
- Architecture map, runbooks, onboarding и risk register.

## Out of scope

- Конкретный cloud deployment без отдельного решения.
- Kubernetes/Kafka ради enterprise-вида.

## План

- [x] Определить initial measurable SLO/quality/cost metrics в
  `docs/operations/technical-due-diligence.md`; production values остаются для
  staging validation.
- [x] Настроить CI checks: `.github/workflows/ci.yml` запускает Pint для
  rewrite-owned module boundaries, backend tests, `vue-tsc` и production
  build. Repository-wide legacy Pint cleanup оставлен отдельным risk.
- [x] Добавить dependency security checks: production npm audit и Composer
  lockfile audit — blocking; dev-tool advisories остаются отдельным review.
- [x] Обновить frontend lockfile в пределах текущих semver ranges и устранить
  production npm advisories; production audit now reports zero vulnerabilities.
- [x] Обновить Composer lockfile в пределах текущих constraints; Composer
  lockfile audit now reports no security advisories.
- [x] Провести security/permissions review: Sanctum boundaries, learner resource
  ownership, admin role/ability gates and public-content checks were reviewed;
  exercise and content-scoped sentence practice now reject non-public content.
- [x] Перенести audit revisions и consumed event log persistence в
  Infrastructure module с сохранением compatibility aliases и focused tests.
- [x] Подготовить due-diligence package с фактическими quality-gate evidence и
  открытыми operational рисками.
- [x] Добавить `/health` readiness endpoint с database check и безопасным
  503 response при недоступной БД.
- [x] Провести изолированный локальный PostgreSQL backup/restore drill и
  документировать процедуру, evidence и production RPO/RTO follow-up.
- [x] Подготовить production sign-off checklist с владельцами, evidence и
  acceptance criteria без выдумывания provider-specific RPO/RTO.

## Проверка

- Локальные quality gates зелёные: 990 backend tests / 2973 assertions,
  `vue-tsc --noEmit` и `vue-tsc -- -b`, production build.
- Browser smoke: public `/catalog` renders content cards; guest
  `/repetitions` redirects to `/login?redirect=/repetitions`.
- CI now runs the seeded Compose Playwright rewrite smoke covering login,
  catalog/detail/study, repetitions and AI authorization boundaries.
- Authenticated browser smoke: existing local admin login reaches the practice
  mode surface; staff access to `/chat` is explicitly restricted by the SPA.
- Visual smoke: catalog and practice surfaces render with visible headings,
  controls and expected disabled states in the local browser.
- Failure/retry/restore drills.
- Проверка queue, logs, tracing и AI cost signals.

## Документация

- `docs/architecture/`, `README.md` и operational docs.
- `docs/operations/technical-due-diligence.md` содержит текущие evidence,
  operational boundaries и открытые риски.
- `docs/operations/production-sign-off-checklist.md` содержит обязательные
  launch sign-offs для выбранной production платформы.

## Зависимости

- Все backend, AI, domain и frontend tasks.
