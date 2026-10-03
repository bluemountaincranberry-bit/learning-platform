# Testing Policy

Подход проекта: `integration-first`.

## Цель

Гарантировать, что ключевые потоки между модулями работают корректно в первую очередь.

## Приоритет тестов

1. **Integration tests**
   - Проверяют связку модулей, БД, очередей, событий и авторизации.
   - Примеры:
     - `Content submit -> ingestion job -> final state`
     - `Learning answer -> SRS recalculation -> due list`
     - `Admin action -> content visibility change`

2. **Feature tests**
   - Проверяют пользовательские и админские сценарии через HTTP/API.

3. **Unit tests**
   - Проверяют локальную доменную логику.
   - Особый фокус: вычисления, инварианты, interval logic, isolated services.

## Минимум на значимую задачу

Для каждой значимой задачи:
- минимум 1-2 integration tests на основной и отказоустойчивый сценарий;
- feature tests на основные user/admin маршруты;
- unit tests на сложные вычисления и инварианты.

## Запуск тестов

PHP и тесты выполняются внутри Docker-контейнера `app`.

| Действие | Команда |
|----------|---------|
| Запустить контейнеры | `make up` |
| Войти в контейнер | `make bash` |
| Artisan | `make artisan ARGS=\"migrate\"` |
| Все тесты | `make test` |
| Фильтр | `make test ARGS=\"--filter=ProcessContent\"` |
| Один файл | `make test ARGS=\"tests/Feature/ExampleTest.php\"` |

Перед коммитом и при закрытии задачи желательно выполнять релевантные проверки и держать критичные потоки зелёными.
