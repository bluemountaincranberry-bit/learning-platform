# Architecture

Технический high-level слой проекта.

Здесь лежит то, что полезно держать рядом с кодом:
- как мыслить о проекте;
- какие модули выделены;
- как они взаимодействуют.

## Файлы

| Файл | О чём |
|------|-------|
| [modules-and-events.md](modules-and-events.md) | Модули системы, их ответственность и способ взаимодействия |
| [content-ingestion-status-model.md](content-ingestion-status-model.md) | Целевая статусная модель и flow для content ingestion, включая user-submitted YouTube |
| [queue-and-observability.md](queue-and-observability.md) | Конвенции очередей/джобов и наблюдаемости |
| [../operations/health-and-readiness.md](../operations/health-and-readiness.md) | Health/readiness endpoint and operational checks |
| [ai-platform-vision.md](ai-platform-vision.md) | North Star: архитектура AI-платформы (TutorAgent, Tools/Agents, Workflow Engine, память, Kafka, RAG, observability) |
| [ai-platform-implementation-roadmap.md](ai-platform-implementation-roadmap.md) | Исполняемый backlog: epics → задачи → миграции, классы, тесты, порядок разработки |
| [agent-framework-roadmap.md](agent-framework-roadmap.md) | Механика `AgentLoop`/`GraphRunner`/трейсинга/fan-out на уровне классов, мини-шаги реализации |
| [adr/](adr/) | Architectural Decision Records — обоснования ключевых решений AI-платформы |
| [technical-due-diligence.md](../operations/technical-due-diligence.md) | Current investor-readiness evidence, quality gates and open risks |
| [module-ownership.md](module-ownership.md) | Persistence ownership, invariants and migration rules |

## Связанный документ

- [PROJECT_CONTEXT.md](../../PROJECT_CONTEXT.md) — общий контекст и принципы проекта
