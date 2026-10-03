import type { LexemeAssociationItem } from '../types/lexeme';

export type AssociationTone = 'primary' | 'danger' | 'neutral' | 'warning';

// Task 10.4/10.5: the full typed vocabulary LexemeAssociation::TYPES
// supports on the backend — kept in sync with that constant.
const ASSOCIATION_TYPE_LABELS: Record<string, string> = {
    synonym: 'Synonyms',
    near_synonym: 'Near-synonyms',
    antonym: 'Antonyms',
    homograph: 'Same spelling, different meaning',
    cognate: 'Cognates',
    word_family: 'Word family',
    phrasal_verb: 'Phrasal verbs',
    collocation: 'Collocations',
    grammatical: 'Grammatical forms',
    thematic: 'Related topic',
    related: 'Related',
};

const ASSOCIATION_TYPE_TONES: Record<string, AssociationTone> = {
    synonym: 'primary',
    near_synonym: 'primary',
    antonym: 'danger',
    homograph: 'danger',
    cognate: 'neutral',
    word_family: 'neutral',
    phrasal_verb: 'warning',
    collocation: 'warning',
    grammatical: 'neutral',
    thematic: 'neutral',
    related: 'neutral',
};

const ASSOCIATION_TYPE_ORDER = [
    'synonym',
    'near_synonym',
    'antonym',
    'homograph',
    'phrasal_verb',
    'collocation',
    'word_family',
    'cognate',
    'grammatical',
    'thematic',
    'related',
];

export interface AssociationGroup {
    type: string;
    label: string;
    tone: AssociationTone;
    items: string[];
}

/** Groups a lexeme's associations by type (synonym/antonym/related/collocation/...) for badge display. */
export function groupAssociationsByType(associations?: LexemeAssociationItem[]): AssociationGroup[] {
    if (!associations || associations.length === 0) return [];

    const byType = new Map<string, string[]>();
    for (const association of associations) {
        const list = byType.get(association.type) ?? [];
        list.push(association.lemma);
        byType.set(association.type, list);
    }

    const orderedTypes = [
        ...ASSOCIATION_TYPE_ORDER,
        ...[...byType.keys()].filter((type) => !ASSOCIATION_TYPE_ORDER.includes(type)),
    ];

    return orderedTypes
        .filter((type) => byType.has(type))
        .map((type) => ({
            type,
            label: ASSOCIATION_TYPE_LABELS[type] ?? type,
            tone: ASSOCIATION_TYPE_TONES[type] ?? 'neutral',
            items: byType.get(type) as string[],
        }));
}
