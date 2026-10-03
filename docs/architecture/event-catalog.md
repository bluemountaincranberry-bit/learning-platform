# Каталог бизнес-событий

Все события имеют версию payload. ORM-модели не являются частью внешнего
контракта; legacy события с модельными payload постепенно заменяются
идентификаторами.

| Событие | Владелец | Версия | Доставка | Payload |
| --- | --- | --- | --- | --- |
| `ContentSubmitted` | Content | 1 | critical, in-process + Kafka | `content_id` |
| `ContentProcessingRequested` | Content | 1 | critical, queue | `content_id` |
| `LexemeLearningStarted` | Content (current producer) | 1 | critical, in-process | `user_id`, `content_lexeme_id`, `item_key`, `content_id` |
| `LexemeLearningStopped` | Content (current producer) | 1 | critical, in-process | `user_id`, `content_lexeme_id`, `item_key` |
| `ExerciseCompleted` | Learning | 1 | noncritical, in-process + Kafka | `user_id`, `item`, `grade`, `is_mistake`, `language` |

Критические события должны быть записаны в рамках бизнес-транзакции и не
теряться при rollback/ошибке очереди. Kafka и аналитические listeners относятся
к secondary effects и не должны блокировать основной use case. Следующий этап
event backbone добавит outbox, retry и deduplication без изменения названий
событий.
