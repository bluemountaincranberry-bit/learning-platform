# Skills для улучшения Learning App

## Установлено

Личные skills находятся в `/home/user/.codex/skills/` и станут доступны для выбора со следующего сообщения:

| Skill | Источник | Роль |
|---|---|---|
| webapp-testing | anthropics/skills | Браузер, screenshots, browser logs |
| web-design-guidelines | vercel-labs/agent-skills | UI и доступность |
| impeccable | pbakaus/impeccable | Дизайн, critique, audit, adapt, distill |
| scientific-critical-thinking | K-Dense-AI/scientific-agent-skills | Качество научных доказательств |
| literature-review | K-Dense-AI/scientific-agent-skills | Формальные обзоры литературы |
| llm-evaluation | wshobson/agents | Оценка AI и регрессий |

Проектный `.agents/skills/learning-app-improvement-audit/SKILL.md` связывает проверки с ai-orchestration и OpenSpec. Личные skills не входят в clone проекта автоматически.

## Запуск

```text
Используй learning-app-improvement-audit. Пройди путь B1-ученика:
YouTube → слова → упражнения → повторение → прогресс → AI tutor.
Проверь mobile и desktop. Сохрани доказательства и приоритетный чеклист.
```

Затем выбрать finding и попросить `openspec-propose` подготовить change. Для согласованного change использовать `openspec-apply-change` и повторную проверку исходного сценария.

## Зависимости

- webapp-testing использует Python Playwright; отдельно в проекте уже есть Node Playwright в docker/playwright.
- Проверенное локальное окружение: `/home/user/.local/share/learning-audit/runtime-venv/bin/python` (playwright, requests, pyyaml). Запуск браузера: `p.chromium.launch(headless=True, executable_path='/usr/bin/google-chrome')`. Smoke test открытия страницы и клика прошёл. Загрузка bundled Chromium завершилась сетевыми таймаутами, поэтому используется существующий Chrome.
- Impeccable установлен вместе с launcher, который может загрузить engine при первом запуске. Автоматические hooks не устанавливались.
- scientific-critical-thinking позволяет анализировать доступные публикации без отдельного API.
- Полный upstream literature-review требует parallel-web с авторизацией и scientific-schematics для AI-схем; PDF требует Pandoc/LaTeX. Эти дополнительные сервисы не настроены. Обычный product research использует доступный web search и scientific-critical-thinking.
- llm-evaluation предоставляет методику; реальные tutor evals используют существующие fixtures и настроенного AI provider.
- AI-симуляция ученика даёт гипотезы об удобстве; эффективность обучения проверяется на данных реальных учащихся.

## Первый аудит

- [ ] Зафиксировать persona, тестовую среду и контент.
- [ ] Пройти один учебный сценарий на mobile и desktop.
- [ ] Сохранить научные источники и ограничения применимости.
- [ ] Оценить UI и AI tutor по явным критериям.
- [ ] Составить findings с evidence и acceptance criteria.
- [ ] Выбрать первый небольшой change для OpenSpec.

Установка skills не означает выполнение аудита приложения.

Проверено: YAML metadata всех семи skills, quick_validate проектного skill, webapp-testing helper --help и запуск Playwright с Chrome. Impeccable engine и внешние исследовательские сервисы ещё не запускались.
