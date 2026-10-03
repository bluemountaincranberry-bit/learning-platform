# Coding standards

Стандарты кода этого репозитория. Их читает `code-review` (ось Standards) и
агент перед реализацией. Именование: [naming-conventions.md](naming-conventions.md).

## Backend (Laravel)

- Код живёт в модуле-владельце (`src/app/Modules/<Module>`); модули общаются
  через публичные сервисы, контракты и события.
- Контроллер тонкий: Form Request валидирует, Policy/Gate авторизует,
  Action или сервис модуля выполняет use case, API Resource отдаёт контракт.
- Доменная логика не зависит от HTTP и тестируется отдельно.
- Тяжёлая, внешняя и фоновая работа — Job с retry/backoff, timeout и
  идемпотентностью.
- Бизнес-факт — событие в прошедшем времени; побочные эффекты — слушатели.
  Новое событие — в `docs/architecture/event-catalog.md`.
- Каждый async-контур наблюдаем: статус, логи, Horizon/Pulse, понятная ошибка.
- Модель данных расширяется миграциями, которые переносят существующие данные.
- Значимое архитектурное решение — ADR (`domain-modeling`).

## SPA (Vue)

- Слои: `pages/` → `widgets/` → `domains/` → `shared/ui/`.
- Логика — в composables и domain-слое; у компонента одна понятная роль.
- Типы API и маппинг snake_case → camelCase — в domain-слое.
- У экрана есть состояния loading, error, empty и success.
- Learner UI — mobile first: 360 и 390px без горизонтального скролла.

## Тесты (integration-first)

1. Integration: ключевые потоки между модулями, БД, очереди, события, авторизация
   (`Content submit → ingestion job → final state`, `Learning answer → SRS → due list`).
2. Feature: user и admin сценарии через HTTP/API.
3. Unit: вычисления, инварианты, интервалы SRS.

Значимая задача: integration-тест на основной и на сбойный сценарий, feature-тесты
на маршруты, unit-тесты на сложную логику. Тесты пишутся через `tdd` на швах,
которые называет тикет.

## Проверки

Из `src/` (или через `make`): `php artisan test`, `npm run build`,
`composer architecture`, `npm run architecture:frontend`,
`npm run architecture:naming`.
