# Claude Adapter

## Как применять

Если среда Claude не дает реальных sub-agents, роли выполняются последовательно
в одном диалоге как отдельные passes.

## Маппинг ролей

- `Orchestrator Agent` - активный ассистент.
- `Research Agent` - секция `Research`.
- `Task Planner Agent` - секция `Task Planning`.
- `Solution Designer Agent` - секция `Solution Design`.
- `Implementation Agent` - секция или отдельный coding pass.
- `Test Agent` - секция `Validation`.
- `Review Agent` - секция `Review`.
- `Documentation Agent` - секция `Documentation`.

## Правило

Даже без настоящих sub-agents сохранять границы ролей:

- design не пишет код;
- implementation не расширяет scope;
- test не меняет продуктовую логику;
- review не переписывает без причины.
