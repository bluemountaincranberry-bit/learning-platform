# Exercise attempts and speech feedback

`exercise_attempts` is the audit boundary between an exercise UI, learning progress, SRS, and external speech providers.

## Data model

An attempt belongs to a user and content item, and may point to one content lexeme and one transcript segment. It stores the exercise type (`dictation`, `shadowing`, or `speaking`), learner text, correctness, score, error type, hint/replay telemetry, provider JSON, and lifecycle status.

The transcript and lexeme foreign context is validated against the same content item. This prevents an answer from one video being attached to another video’s word or segment.

## Processing boundary

The application service owns orchestration:

1. create a processing attempt;
2. transcribe audio when supplied;
3. compare target and learner text;
4. assess pronunciation for speaking exercises;
5. persist the result;
6. update the corresponding confidence dimension and SRS review metadata.

Speech services are replaceable through `SpeechToTextProviderInterface` and `PronunciationAssessmentProviderInterface`. The default STT adapter uses OpenAI when configured; Azure Speech is selected for pronunciation when configured; deterministic stubs keep local development and tests independent of credentials.

## Privacy and scaling

Audio is processed from the upload temporary file and is not retained by default. If durable audio replay is added later, it must use private storage, an expiry timestamp, and an explicit retention policy.

Run `php artisan learning:cleanup-exercise-audio` hourly in production to remove files left by failed or abandoned jobs.

The endpoint creates a `pending` attempt and dispatches `ProcessExerciseAttemptJob`. The frontend polls the existing show endpoint until the attempt is completed or failed. Audio is deleted after processing; retries use the same attempt id and are idempotent once completed. The job uses three attempts with 15/60/180 second backoff.

Azure Speech pronunciation assessment is selected when `AZURE_SPEECH_ENABLED`, `AZURE_SPEECH_KEY`, and `AZURE_SPEECH_REGION` are configured. Local/testing environments use the deterministic demo provider; other environments use the stub provider until Azure is configured.

In local/testing environments, `DemoPronunciationAssessmentProvider` returns deterministic dimension scores so the UI, queue, confidence, and SRS flows can be exercised without Azure credentials. It is never selected in production.

Browser recordings are normalized to mono 16 kHz PCM WAV with ffmpeg before pronunciation assessment. The PHP image therefore includes ffmpeg; provider failures remain non-fatal and are recorded in `provider_result.pronunciation_error`.
