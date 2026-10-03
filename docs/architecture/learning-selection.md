# Учебный отбор и персональный приоритет

`LexemeLearningSelector` возвращает для каждой content lexeme объяснимые
`learning_category`, `learning_score` и `learning_reasons`. Сейчас baseline
использует тип (phrase), частоту повторения в контенте, CEFR контента/слова,
уровень пользователя и known state. Это ranking signal, а не удаление данных.

Категории `noise` и `rare` намеренно мягкие: они помогают UI скрывать шум,
но не удаляют пользовательский материал. Позже frequency dataset или модель
может заменить selector, сохранив этот контракт.
