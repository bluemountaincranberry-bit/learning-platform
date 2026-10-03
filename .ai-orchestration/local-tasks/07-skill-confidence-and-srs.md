# Skill confidence и error-driven SRS

## Investigation

Confidence dimensions уже хранятся, но self-check записывает только три
измерения фиксированными значениями. Рекомендуемый путь — skill-aware event
recording: каждое упражнение передаёт outcome, сервис обновляет только
релевантный skill, а SRS сохраняет context/error metadata. Интервалы не
переписываем до появления достаточной статистики.

## План

- [x] Сделать confidence update outcome-driven.
- [x] Подключить production/listening/recognition/recall/speaking mapping.
- [x] Добавить error telemetry и hint tracking.
- [x] Добавить due/review signal в ranking.

## Out of scope

- Новый SM-2/FSRS алгоритм и speech-to-phoneme scoring.

## Проверка

- Self-check/SRS integration tests и confidence invariants.
