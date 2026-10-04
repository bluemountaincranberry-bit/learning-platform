# Local Tasks (closed)

Старый backlog в markdown, до перехода на Linear. Закрыт тикетом VIK-37
(2026-10-04): новые задачи сюда не пишутся, они живут в Linear
(`docs/agents/issue-tracker.md`). Удалённые файлы можно найти в git history
(`git log -- .ai-orchestration/local-tasks/`).

## Куда ушла каждая задача

| Задача | Итог | Где теперь |
|---|---|---|
| 02 automatic lexeme selection | Сделано | `docs/architecture/learning-selection.md` |
| 03 lexeme knowledge dimensions | Сделано | `docs/architecture/learning-knowledge-model.md` |
| 04 exercise errors to SRS | Сделано | `docs/architecture/learning-review-outcomes.md` |
| 05 close learning feedback loop | Сделано | `docs/architecture/learning-review-outcomes.md` |
| 06 personalized ranking | Сделано | `docs/architecture/learning-selection.md`, `personalized-learning.md` |
| 07 skill confidence and SRS | Сделано | `docs/architecture/learning-knowledge-model.md` |
| 08 learning analytics | Сделано, кроме learning gain per content | Остаток добавлен в VIK-34 (комментарий); `personalized-learning.md` |
| 09 adaptive curriculum | Сделано, кроме entrypoint на dashboard | Покрывают VIK-28 и VIK-30 |
| 10 learning activities | Сделано (transcript context есть в attempts/reviews) | `docs/architecture/exercise-attempts.md` |
| 11 transcript reliability | Не начато | Новый тикет VIK-57 |
| 12 dictation exercise | Сделано | `docs/architecture/exercise-attempts.md` |
| 13 shadowing speaking | Сделано | `docs/architecture/exercise-attempts.md`; дальше Speaking Coach (VIK-45, VIK-49…52) |
| 14 speaking provider | Сделано | `docs/architecture/exercise-attempts.md`; дальше VIK-49 |
| 15 adaptive learning flow profiles | Сделано; поэтапный план устарел | `docs/architecture/adaptive-learning-flow.md`, `adaptive-learning-flow-rules.md`; пресеты — VIK-30 |
| 2026-09-14-01 rewrite baseline | Сделано | `docs/architecture/rewrite-spec.md`, `module-ownership.md`, ADR-007/009 |
| 2026-09-14-02 backend foundation | Сделано | `docs/architecture/modules-and-events.md`, `module-ownership.md` |
| 2026-09-14-03 AI platform | Сделано | `docs/architecture/ai-platform-vision.md`, `ai-operations.md`, ADR-008 |
| 2026-09-14-04 domain rewrite | Сделано | `docs/architecture/module-ownership.md`, `modules-and-events.md` |
| 2026-09-14-05 frontend rewrite | Сделано | `docs/architecture/frontend-rewrite-spec.md` |
| 2026-09-14-06 investor readiness | Сделано; открытые риски | `docs/operations/technical-due-diligence.md`, `production-sign-off-checklist.md`; Pint и dev advisories — VIK-58; backups/RPO ждут выбора хостинга |
| 2026-09-15 frontend architecture research | Сделано | `docs/architecture/frontend-rewrite-research.md`, `frontend-rewrite-spec.md` |
