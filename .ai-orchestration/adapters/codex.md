# Codex Adapter

## Как применять

Codex может выполнять роли последовательно в главном потоке или запускать
sub-agents, если Вика явно просит multi-agent / parallel agents / sub-agents.

## Маппинг ролей

- `Orchestrator Agent` - главный текущий агент.
- `Research Agent` - главный агент или explorer sub-agent для read-only исследования.
- `Task Planner Agent` - главный агент, который создает markdown-задачи.
- `Solution Designer Agent` - обычно главный агент или explorer sub-agent.
- `Implementation Agent` - worker sub-agent, если есть четкий disjoint scope.
- `Test Agent` - отдельный агент только если проверка может идти параллельно.
- `Review Agent` - reviewer pass в главном агенте или отдельный sub-agent.
- `Documentation Agent` - обычно главный агент в конце задачи.

## Правила Codex

- Не запускать sub-agents без явной просьбы Вики.
- Для worker sub-agent всегда задавать область ответственности.
- Не давать двум worker-ам один и тот же write scope.
- Главный агент интегрирует результат и закрывает лишние agent threads.
- Sub-agent получает context packet, а не весь чат, если нет причины форкать контекст.
