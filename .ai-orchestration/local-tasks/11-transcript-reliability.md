# Надёжность transcript learning loop

## Investigation

Timed transcript, word offsets, target/native captions и replay уже есть.
Остаются provider quality, pagination на клиенте, fallback status и
обозначение отсутствующих timestamps. Рекомендуемый путь — сделать состояние
явным и не блокировать старые plain-text contents.

## План

- [ ] Добавить transcript timing status и provider diagnostics.
- [ ] Подключить lazy pagination на frontend.
- [ ] Покрыть missing/invalid timestamp cases.
- [ ] Добавить документацию operational risks.

## Out of scope

- Полный subtitle editor.

## Проверка

- Provider/API tests и frontend build.
