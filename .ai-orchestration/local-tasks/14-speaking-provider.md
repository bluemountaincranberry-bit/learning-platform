# Pronunciation provider boundary

## Решение

Speech-to-text и pronunciation assessment разделяются интерфейсами. OpenAI
transcription подключается первым, а Azure/другой pronunciation provider
может быть добавлен без изменения Learning API.

## Контракт

- `SpeechToTextProviderInterface` возвращает transcript/confidence.
- `PronunciationAssessmentProviderInterface` возвращает accuracy/fluency/
  completeness/prosody и word results.
- Stub implementations используются в тестах и локальной разработке; OpenAI STT подключается автоматически при наличии `OPENAI_API_KEY`, Azure pronunciation — при наличии `AZURE_SPEECH_ENABLED`, `AZURE_SPEECH_KEY` и `AZURE_SPEECH_REGION`.

## Out of scope

- Без Azure credentials используется stub; Azure REST pronunciation assessment поддерживает word/phoneme results для коротких аудио.
