/**
 * External translator deep-links for a word or phrase.
 *
 * Google and Yandex take ISO 639-1 codes and cover ~100 languages, so they
 * are always shown. DeepL needs a supported-pair allowlist, Reverso Context
 * needs English language names — both are hidden when the pair is unknown
 * rather than linking to an error page. All schemes were verified against
 * the live sites (word prefilled on load).
 */
export interface TranslatorLink {
    key: string;
    label: string;
    url: string;
}

/** DeepL translator supports these ISO 639-1 codes (subset incl. all app languages). */
const DEEPL_CODES = new Set([
    'ar', 'bg', 'cs', 'da', 'de', 'el', 'en', 'es', 'et', 'fi', 'fr', 'he',
    'hi', 'hu', 'id', 'it', 'ja', 'ko', 'lt', 'lv', 'ms', 'nb', 'nl', 'pl',
    'pt', 'ro', 'ru', 'sk', 'sl', 'sv', 'th', 'tr', 'uk', 'vi', 'zh',
]);

/** ISO 639-1 → Reverso Context language names (pairs it actually serves). */
const REVERSO_NAMES: Record<string, string> = {
    ar: 'arabic',
    de: 'german',
    en: 'english',
    es: 'spanish',
    fr: 'french',
    he: 'hebrew',
    it: 'italian',
    ja: 'japanese',
    nl: 'dutch',
    pl: 'polish',
    pt: 'portuguese',
    ro: 'romanian',
    ru: 'russian',
    tr: 'turkish',
    uk: 'ukrainian',
    zh: 'chinese',
};

export function translatorLinks(text: string, source: string | null, target: string): TranslatorLink[] {
    const trimmed = text.trim();
    if (trimmed === '') return [];
    const from = (source ?? '').toLowerCase();
    const to = target.toLowerCase();
    const encoded = encodeURIComponent(trimmed);

    const links: TranslatorLink[] = [
        {
            key: 'google',
            label: 'Google',
            url: `https://translate.google.com/?sl=${from || 'auto'}&tl=${to}&text=${encoded}&op=translate`,
        },
        {
            key: 'yandex',
            label: 'Yandex',
            url: `https://translate.yandex.com/?lang=${from || 'auto'}-${to}&text=${encoded}`,
        },
    ];

    if (from !== '' && DEEPL_CODES.has(from) && DEEPL_CODES.has(to)) {
        links.push({
            key: 'deepl',
            label: 'DeepL',
            url: `https://www.deepl.com/en/translator#${from}/${to}/${encoded}`,
        });
    }

    const reversoFrom = REVERSO_NAMES[from];
    const reversoTo = REVERSO_NAMES[to];
    if (reversoFrom && reversoTo && reversoFrom !== reversoTo) {
        links.push({
            key: 'reverso',
            label: 'Reverso',
            url: `https://context.reverso.net/translation/${reversoFrom}-${reversoTo}/${encoded}`,
        });
    }

    return links;
}
