import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const dialog = await readFile(new URL('../../resources/js/spa/shared/ui/UiDialog.vue', import.meta.url), 'utf8');
const asyncState = await readFile(new URL('../../resources/js/spa/shared/ui/AsyncState.vue', import.meta.url), 'utf8');
const button = await readFile(new URL('../../resources/js/spa/shared/ui/UiButton.vue', import.meta.url), 'utf8');
const input = await readFile(new URL('../../resources/js/spa/shared/ui/UiInput.vue', import.meta.url), 'utf8');

assert.match(dialog, /role="dialog"/);
assert.match(dialog, /aria-modal="true"/);
assert.match(dialog, /useId/);
assert.match(dialog, /:aria-busy="busy"/);
assert.match(dialog, /event\.key === 'Escape'/);
assert.match(dialog, /event\.key !== 'Tab'/);
assert.match(dialog, /previouslyFocused\?\.focus\(\)/);
assert.match(dialog, /:disabled="busy"/);
assert.match(asyncState, /emit\('retry'\)/);
assert.match(button, /focus-visible:ring/);
assert.match(button, /disabled:pointer-events-none/);
assert.match(input, /update:modelValue/);
assert.match(input, /focus-visible:ring/);

console.log('Shared UI accessibility self-test passed.');
