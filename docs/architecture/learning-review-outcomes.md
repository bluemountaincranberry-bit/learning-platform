# Контекст ошибок и SRS

`srs_reviews` хранит nullable контекст: `content_lexeme_id`,
`transcript_segment_id`, `exercise_type`, `error_type`, `hint_used` и
`answer_metadata`. SRS interval calculation остаётся прежним; контекст
используется для аналитики, персональных повторений и связи ошибки с
конкретным фрагментом.

Self-check передаёт `unknown_meaning` и hint metadata, а обычный review может
передать тот же контракт через API. Сервер проверяет, что связанные элементы
принадлежат content карточки.

Adaptive trainer классифицирует ошибки по activity (`incorrect_recall`,
`unknown_meaning`, `incorrect_production`, `could_not_hear`) и передаёт их
в batch self-check или сразу в SRS review. Это оставляет interval algorithm
общим, но делает downstream analytics и будущий skill-specific scheduling
достоверными.

Trainer теперь также передаёт фактический `hint_used` для reveal/listen
flows. Старые API-клиенты без activity сохраняют legacy broad confidence
update и продолжают работать.
