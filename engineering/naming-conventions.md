# Naming conventions

Стандарт именования для всего кода. Код, написанный до стандарта, приводится к нему, когда задача его затрагивает.

## Предметный словарь

| Термин | Значение | Пример переменной |
|---|---|---|
| Lexeme | Каноническая словарная единица | lexemeId |
| ContentLexeme | Лексема в конкретном контенте | contentLexemeId |
| LexemeCandidate | Предложение до применения к каталогу | lexemeCandidate |
| ExerciseAttempt | Одна попытка упражнения | exerciseAttempt |
| Review | Оценка воспроизведения для SRS | reviewGrade |
| LearningSession | Последовательность учебных действий | learningSession |
| AnalysisRun | Продуктовый запуск анализа | analysisRunId |
| AiExecution | Техническое выполнение AI | aiExecutionId |

Не подменять `lexemeId` значением `contentLexemeId`; не называть attempt словом review, если это не оценка SRS. Новые публичные термины пояснять здесь при review.

## PHP

- Классы, интерфейсы, enums: PascalCase; namespaces модулей: `Content`, `Learning`, `Srs`, `Ai`, `User`.
- Переменные и методы: camelCase. Сокращения: `Ai`, `Srs`, `Http`, `Id`, например `aiExecutionId`.
- Сценарий: глагол + предмет, например `ApplyLexemeCandidates`, `SubmitReviewAnswer`.
- Событие: произошедший факт, например `TranscriptAccepted`, `ReviewCompleted`.
- Методы отвечают за конкретное действие; один Action не становится общим каталогом CRUD.
- Константы: UPPER_SNAKE_CASE. Имена hooks фреймворка (`__construct`, `boot`, `casts`, `newFactory`, `handle`) сохраняются.
- Модели в единственном числе. Коллекции во множественном: `lexemeCandidates`, `dueReviewCards`.
- Boolean: `isPublished`, `hasTranscript`, `canStartReview`; избегать отрицания в имени при наличии понятного положительного варианта.

## TypeScript и Vue

- Типы и компоненты: PascalCase; Vue-файл совпадает с именем компонента: `ContentCard.vue`.
- Переменные/функции: camelCase; domains — kebab-case.
- Файлы предметного слоя: `content.api.ts`, `content.types.ts`, `content.queryKeys.ts`, `content.queries.ts`, `content.mutations.ts`.
- Queries: `useContentListQuery`; mutations: `useSubmitReviewMutation`; composables: `useLearningSession`.
- Компонент общего UI: `UiButton`; предметный: `ContentCard`; маршрутный: `ContentDetailsPage`.
- Типы DTO отражают роль: `ContentResponse`, `SubmitReviewInput`; избегать универсального `Data` без предмета.

## SQL и API

Существующие имена таблиц/колонок и wire JSON сохраняются при переносе файлов. SQL/wire snake_case преобразуется в frontend camelCase явным mapper:

```text
wire.content_lexeme_id --> model.contentLexemeId
wire.lexeme_id         --> model.lexemeId
```

Проверка mapper подтверждает сохранение типов, nullability и значений обоих идентификаторов. Переименование поля API требует отдельного описания совместимости; одно naming соглашение не разрешает менять контракт.

## Review

Формат проверяется автоматически, смысл — code review. Локальные `result`, `index` допустимы в коротком очевидном контексте. На публичной границе предпочитать `analysisResult`, `contentLexemeId`, `reviewGrade`.

Не вводить бессодержательные `BaseService`, `CommonManager`, `Helpers`; общий класс должен иметь общую ответственность. Исключения линтера задаются для конкретного framework symbol/path с причиной, а не отключают модуль целиком.

Проверки запускаются из `src/`: `composer architecture`,
`npm run architecture:frontend`, `npm run architecture:naming`. Baseline содержит
только точные существующие нарушения; новая запись считается ошибкой. Удаление
исправленной baseline-записи выполняется в том же slice, который устраняет связь.
