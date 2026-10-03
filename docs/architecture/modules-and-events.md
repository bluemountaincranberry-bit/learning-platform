# Modules and Events

## Модули системы

### User

Отвечает за:
- пользователя;
- роли и права;
- профиль;
- auth и identity;
- user-level настройки.

### Content

Отвечает за:
- источники контента;
- curated и user-submitted content;
- статусы обработки;
- каталог;
- видимость;
- модерацию;
- извлечение лексем и связи контента с учебным материалом.

### Learning

Отвечает за:
- учебные сценарии;
- study flow;
- learned state;
- learning sessions;
- личные уроки (`Lesson`), заметки, runs разбора и результаты (`LessonAnalysisRun`,
  `LessonLexemeCandidate`, `LessonGrammarCandidate`);
- self-check;
- progress read-models.

Уроки: модели, `/api/lessons` и SPA API-клиент принадлежат Learning.
Создание, список и открытие доступны любому авторизованному пользователю
(включая admin) без AI feature flags и дневной AI-квоты. Таблицы и ID уроков,
история сообщений и результаты существующих разборов сохраняются.

AI владеет assistant conversations, `LessonAgentService`, анализом и matching,
а также `RunLessonAnalysisJob`. Learning вызывает их через общий контракт
`App\Contracts\Ai\LessonAssistant`. AI читает контекст, сохраняет кандидатов
и меняет состояние runs через `LessonAnalysisStoreInterface`, а извлечённый
текст дописывает через `LessonNotesWriterInterface` из Learning. Между
модулями передаются ID, DTO и массивы, без импорта чужих Eloquent-моделей.
Создание записи assistant conversation не вызывает AI-провайдер; сообщения
агенту по-прежнему проверяют agent flag и дневную квоту, а запуск разбора
проверяет общий AI flag. Редактор заметок и архивирование — следующий VIK-12.

### SRS

Отвечает за:
- spaced repetition;
- due items;
- review logic;
- интервалы;
- history review.

### AI

Отвечает за:
- explain word;
- chat;
- recommendations;
- embeddings;
- AI clients/providers;
- AI-specific orchestration.

### Admin

Отвечает за:
- backoffice;
- управление контентом;
- управление пользователями;
- операционные действия;
- статистику и наблюдаемость на уровне админки.

### Integrations

Отвечает за:
- внешние API;
- OAuth / Passport;
- внешние потребители;
- webhooks / integration contracts.

### Observability / Infrastructure

Отвечает за:
- очереди;
- мониторинг;
- логи;
- события;
- Kafka / Redis / Horizon / Telescope / Pulse / Sentry;
- операционную поддержку системы.

---

## Принцип модульности

Это modular monolith.

Финальная граница проверяется автоматически: приватные межмодульные импорты и
циклы запрещены. Общие AI capabilities находятся в `App\\Contracts\\Ai`, а
остальные порты принадлежат модулю-потребителю и реализуются владельцем данных.

Значит:
- система живёт в одном приложении;
- но внутри неё есть понятные бизнес-границы;
- новые фичи не должны бездумно смешивать `Content`, `Learning`, `SRS`, `AI`, `Admin`.

Главный вопрос перед изменением:

`К какому модулю это относится по бизнес-смыслу?`

---

## Способы взаимодействия модулей

### 1. Direct call

Подходит, когда:
- use case простой и синхронный;
- нет fan-out;
- связь локальна и понятна.

Типовой путь:
- controller -> application service -> repository/domain logic

### 2. Job / queue

Подходит, когда:
- работа тяжёлая;
- она не должна выполняться в HTTP request;
- есть внешние вызовы или обработка контента;
- нужен retry / timeout / background execution.

### 3. Event

Подходит, когда:
- произошёл бизнес-факт;
- на него могут реагировать несколько частей системы;
- не хочется жёстко связывать модули;
- нужны analytics / notifications / Kafka fan-out.

Правило:
- событие описывает факт;
- listeners реализуют побочные реакции;
- core flow не должен зависеть от случайных secondary effects.

---

## Event-first подход

Не всё должно быть event-driven, но важные бизнес-факты лучше выражать явно.

Типовые события, которые логично иметь в системе:
- `ContentSubmitted`
- `ContentProcessingRequested`
- `ContentProcessingCompleted`
- `ContentProcessingFailed`
- `LexemeMarkedLearned`
- `LearningSessionCompleted`
- `SrsCardReviewed`
- `LexemeExplanationRequested`
- `AiConversationStarted`
- `AiMessageSent`

Это полезно потому что:
- secondary effects можно выносить в listeners;
- analytics не размазывается по core logic;
- Kafka позже можно подключать поверх уже существующих событий.

---

## Async mindset

Операция — кандидат на job/event-driven flow, если она:
- тяжёлая;
- нестабильная;
- зависит от внешнего сервиса;
- не нужна пользователю немедленно;
- может требовать retry и отдельной observability.

---

## Redis и Kafka

### Redis

Базовый выбор для:
- queues;
- cache;
- возможно sessions.

### Kafka

Используется не как замена приложению, а как event bus для:
- аналитики;
- интеграций;
- независимых consumers;
- обучающего опыта с event-driven architecture.

Главная идея:
- приложение остаётся source of truth;
- Kafka — способ распространять уже произошедшие события.

---

## Frontend domain entrypoints

SPA тоже следует доменным границам.

Рекомендуемая схема:
- `spa/domains/content` - каталог, content details, lexemes, categories, recommendations;
- `spa/domains/learning` - learned words, self-check, progress, SRS;
- `spa/domains/ai` - chat, recommendations, explain flows;
- `spa/domains/user` - auth, profile, session state.

Правило:
- route screens импортируют доменные входы, а не разрозненные API helper-ы;
- shared UI primitives остаются в `spa/shared/ui`;
- это упрощает читаемые names, future refactor и event-driven thinking на клиенте.
