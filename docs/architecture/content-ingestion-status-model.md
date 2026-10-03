# Content Ingestion Status Model

Этот документ фиксирует target state для content ingestion до следующих задач по рефакторингу pipeline.

Он нужен как архитектурная опора для:
- `TC-40` submit -> processing refactor;
- `TC-41` orchestration layer;
- `TC-42` real transcript provider;
- `TC-43` failure model;
- `TC-47` user-facing submission statuses.

## Scope

Документ описывает:
- статусную модель `Content`;
- смысл каждого статуса;
- допустимые переходы;
- целевой flow для `user-submitted YouTube content`;
- роль admin retry и moderation.

Документ не описывает:
- phrase extraction;
- grammar extraction;
- AI enrichment;
- детали конкретного transcript provider.

## Статусы

### `draft`

Контент существует, но ingestion ещё не запускался.

Типичные источники:
- admin manually created content;
- заготовка для редактирования;
- content, который ещё не готов к обработке.

### `pending`

Контент принят системой и ожидает следующего асинхронного шага.

Для user-submitted YouTube это означает:
- ссылка принята;
- запись создана;
- pipeline должен быть запущен или повторно запущен.

`pending` не должен означать одновременно и "ждёт модерации", и "ждёт processing", и "processing уже завершён". Если нужен отдельный moderation decision, он должен выражаться отдельно, а не перегружать смысл `pending`.

### `processing`

Идёт активная фоновая обработка.

Типичные шаги:
- валидация ingest-ready условий;
- transcript fetch;
- нормализация source text;
- извлечение учебных единиц.

### `ready`

Контент успешно обработан и доступен для публичного каталога и learning flow.

В текущей модели публичная видимость должна зависеть именно от `ready`.

### `failed`

Фоновая обработка завершилась технической ошибкой.

Примеры:
- transcript provider недоступен;
- transcript отсутствует;
- source URL невалиден для processing;
- processing job завершилась ошибкой.

`failed` — это техническое состояние, а не модерационное решение.

Техническая диагностика для `failed` должна храниться отдельно от moderation data:
- `processing_failure_reason` хранит причину технического сбоя pipeline;
- `moderation_comment` используется только для product / operator decision в `rejected`.

### `rejected`

Контент отклонён продуктово или операционно и не должен попадать в публичный каталог.

Это отдельное состояние от `failed`.

## Допустимые переходы

Целевая status machine:

- `draft -> pending`
- `draft -> rejected`
- `pending -> processing`
- `pending -> failed`
- `pending -> rejected`
- `processing -> ready`
- `processing -> failed`
- `ready -> rejected`
- `failed -> pending`
- `failed -> rejected`
- `rejected -> pending`

Что это означает practically:
- `pending -> processing` запускает worker/job flow;
- `processing -> ready` происходит только после успешного ingest + extraction;
- `processing -> failed` фиксирует технический сбой;
- `failed -> pending` используется для retry;
- `rejected -> pending` используется, если контент вернули в обработку после ручного решения.

## Целевой flow для user-submitted YouTube

### User flow

1. Пользователь отправляет `YouTube URL`.
2. API создаёт `Content` со статусом `pending`.
3. Система диспатчит бизнес-факт `ContentSubmitted`.
4. На основе этого факта запускается `ContentProcessingRequested`.
5. Listener решает, какой processing path нужен:
   - `FetchTranscriptJob`, если нужен transcript fetch;
   - `ProcessContentJob`, если `source_text` уже есть.
6. Во время активной обработки статус переводится в `processing`.
7. При успехе контент переходит в `ready`.
8. При технической ошибке контент переходит в `failed`.

### Admin flow

- admin видит `pending`, `processing`, `failed`, `ready`, `rejected`;
- для `failed` admin видит отдельный `processing_failure_reason`, не смешанный с `moderation_comment`;
- admin может retry для `failed` через переход `failed -> pending`;
- admin может отклонить контент через `rejected`;
- admin не должен запускать альтернативный pipeline в обход общего orchestration flow.

## Event and job expectations

Целевой event/job contract:

- `ContentSubmitted`
  бизнес-факт: новая user submission принята.

- `ContentProcessingRequested`
  бизнес-факт: ingestion должен быть запущен или перезапущен.

- `FetchTranscriptJob`
  интеграционный шаг: получить transcript для YouTube.

- `ProcessContentJob`
  application шаг: превратить `source_text` в учебные единицы.

Следующие задачи могут ввести:
- `ContentProcessingCompleted`
- `ContentProcessingFailed`

Но уже на этом этапе важно зафиксировать саму статусную модель и целевой orchestration flow.

## Visibility rules

- публичный каталог и публичное чтение контента работают только для `ready`;
- `pending`, `processing`, `failed`, `rejected`, `draft` не считаются публично доступными;
- user-facing submission API может показывать непубличные статусы владельцу submission.

## Почему это решение выбрано

Это решение:
- сохраняет `Content` как source of truth;
- соответствует event/job подходу проекта;
- не смешивает технические ошибки с moderation decisions;
- даёт понятную основу для retry, admin actions и user-facing statuses;
- упрощает следующий refactor submit -> processing pipeline.
