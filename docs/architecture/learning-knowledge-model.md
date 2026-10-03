# Модель знания лексемы

`user_lexeme_progress` продолжает хранить совместимый факт learned. Для
контекстного навыка добавлена `user_lexeme_confidences` с dimensions:
`recognition`, `recall`, `production`, `listening`, `speaking` (0–100).

Self-check обновляет recognition/recall/listening. Остальные dimensions пока
остаются для production/speaking упражнений и не выводятся из одного learned
флага.
