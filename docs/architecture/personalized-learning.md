# Персонализация и curriculum feedback loop

Персонализация использует explainable signals: CEFR fit, повторяемость,
known state, confidence gap, ошибки, due cards и `users.learning_goal`.
Это намеренно weighted baseline, а не ML policy: его можно проверить через
outcomes и заменить model/ranker без изменения learner API.

`/api/me/stats` теперь содержит `skill_accuracy` и retention checkpoints для
1/7/30 дней. Слабый skill добавляет activity recommendation (`dictation`,
`cloze`, `shadowing` или `recall`). Эти activity types пока являются
recommendation contract; speech scoring и отдельные полноценные activity
screens остаются следующими продуктово-техническими slices.

Retention считается только для review rows с `content_lexeme_id`; старые
reviews без контекста не превращаются в искусственные learning claims.
Baseline/post learning-gain и A/B-эксперименты пока не добавлены.
