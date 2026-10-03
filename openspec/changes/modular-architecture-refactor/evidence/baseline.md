# Baseline до архитектурных изменений

Дата: 2026-09-15. Commit: `d120ffd2b6324a4b02be2443d6ed12cf44bfbb04`.

Рабочее дерево уже содержало большой незавершённый rewrite. Снимок путей и
SHA-256 хранится в `initial-files.json`, исходный `git status --porcelain` — в
`initial-status.txt`. Это доказательство исходного состояния, а не резервная
копия. Во время подготовки baseline чужие изменения не откатывались.

## Проверки

| Проверка | Результат |
|---|---|
| `make npm ARGS="exec vue-tsc -- --noEmit"` | exit 0 |
| `make npm ARGS="run build"` | exit 0, Vite build завершён за 8.57s |
| `make test` без Elasticsearch | exit 2: 35 failed, 955 passed; причина — host `elasticsearch` недоступен |
| `make test` после запуска Elasticsearch | exit 2: 13 failed, 977 passed; оставшиеся ошибки — timeout Elasticsearch в AI/RAG тестах |
| focused characterization suite | exit 0: 45 passed, 130 assertions |

Полный Laravel baseline нельзя считать зелёным. Ошибки существовали до
архитектурных production-изменений этого change и относятся к интеграции с
Elasticsearch/стабильности тестового окружения. Они сохраняются как отдельный
baseline-дефект и не будут скрываться отметкой задачи как успешного полного
test run.

## Characterization

Зафиксированы текущие наблюдаемые свойства:

- повторное принятие одного transcript сохраняет текст и владельца;
- принятие transcript откатывается вместе с окружающей транзакцией;
- ошибка при применении AI candidate откатывает canonical lexeme,
  ContentLexeme и статус candidate;
- текущее `/api/srs/review` считает два одинаковых HTTP-запроса двумя review.

Последний пункт является установленным ограничением существующего API. Для
идемпотентности потребуется отдельное документированное изменение контракта с
operation id; characterization expectation тогда заменяется целевым тестом.
