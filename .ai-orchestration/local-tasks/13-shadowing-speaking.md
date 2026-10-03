# Shadowing и speaking feedback

## Цель

Записывать короткий голосовой ответ, получать STT/assessment result и
обновлять speaking confidence/SRS без хранения raw audio навсегда.

## План

- [x] Добавить provider contracts и OpenAI transcription adapter.
- [x] Добавить queued attempt processing с pending/processing/completed/failed, retry и polling.
- [x] Добавить ShadowingCard и microphone recorder.
- [x] Добавить pronunciation result contract, Azure adapter и confidence mapping.
- [x] Добавить privacy/limits/tests/docs.

## Проверка

- HTTP fake provider tests, attempt lifecycle tests и frontend build.
