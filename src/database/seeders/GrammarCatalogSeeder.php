<?php

namespace Database\Seeders;

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Database\Seeder;

class GrammarCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            [
                'slug' => 'articles-and-determiners',
                'name' => 'Articles and Determiners',
                'description' => 'Core article usage for beginner curated grammar lessons.',
                'sort_order' => 10,
                'rules' => [
                    [
                        'slug' => 'basic-articles-a-an-the',
                        'title' => 'Using a, an and the',
                        'level' => 'A1',
                        'status' => GrammarRule::STATUS_PUBLISHED,
                        'summary' => 'Choose between indefinite and definite articles in simple noun phrases.',
                        'body' => 'Use a or an for non-specific singular nouns and the when the noun is specific or already known from context.',
                        'sort_order' => 10,
                        'examples' => [
                            ['example' => 'I saw a dog in the park.', 'translation' => null, 'is_primary' => true, 'sort_order' => 10],
                            ['example' => 'The dog was chasing a ball.', 'translation' => null, 'is_primary' => false, 'sort_order' => 20],
                        ],
                        'lexemes' => [
                            [
                                'slug' => 'a-article',
                                'lemma' => 'a',
                                'normalized_lemma' => 'a',
                                'part_of_speech' => 'article',
                                'level' => 'A1',
                                'status' => Lexeme::STATUS_PUBLISHED,
                                'notes' => 'Indefinite article before consonant sounds.',
                                'examples' => [
                                    ['example' => 'She bought a notebook.', 'translation' => null, 'is_primary' => true, 'sort_order' => 10],
                                ],
                            ],
                            [
                                'slug' => 'an-article',
                                'lemma' => 'an',
                                'normalized_lemma' => 'an',
                                'part_of_speech' => 'article',
                                'level' => 'A1',
                                'status' => Lexeme::STATUS_PUBLISHED,
                                'notes' => 'Indefinite article before vowel sounds.',
                                'examples' => [
                                    ['example' => 'He ate an apple.', 'translation' => null, 'is_primary' => true, 'sort_order' => 10],
                                ],
                            ],
                            [
                                'slug' => 'the-article',
                                'lemma' => 'the',
                                'normalized_lemma' => 'the',
                                'part_of_speech' => 'article',
                                'level' => 'A1',
                                'status' => Lexeme::STATUS_PUBLISHED,
                                'notes' => 'Definite article for specific nouns.',
                                'examples' => [
                                    ['example' => 'Please close the door.', 'translation' => null, 'is_primary' => true, 'sort_order' => 10],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'present-simple',
                'name' => 'Present Simple',
                'description' => 'High-frequency present simple rules for daily routines and facts.',
                'sort_order' => 20,
                'rules' => [
                    [
                        'slug' => 'present-simple-routines',
                        'title' => 'Present simple for routines and facts',
                        'level' => 'A1',
                        'status' => GrammarRule::STATUS_PUBLISHED,
                        'summary' => 'Use the present simple for habits, routines and general truths.',
                        'body' => 'The present simple describes repeated actions and facts. Third-person singular usually adds -s to the verb.',
                        'sort_order' => 10,
                        'examples' => [
                            ['example' => 'She usually walks to work.', 'translation' => null, 'is_primary' => true, 'sort_order' => 10],
                        ],
                        'lexemes' => [
                            [
                                'slug' => 'usually-adverb',
                                'lemma' => 'usually',
                                'normalized_lemma' => 'usually',
                                'part_of_speech' => 'adverb',
                                'level' => 'A1',
                                'status' => Lexeme::STATUS_PUBLISHED,
                                'notes' => 'Frequency adverb often used with routines.',
                                'examples' => [
                                    ['example' => 'I usually read before bed.', 'translation' => null, 'is_primary' => true, 'sort_order' => 10],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'modal-verbs',
                'name' => 'Modal Verbs',
                'description' => 'Starter modal verbs for permission, ability and suggestions.',
                'sort_order' => 30,
                'rules' => [
                    [
                        'slug' => 'can-for-ability',
                        'title' => 'Can for ability',
                        'level' => 'A1',
                        'status' => GrammarRule::STATUS_PUBLISHED,
                        'summary' => 'Use can to describe present ability.',
                        'body' => 'Can comes before the base verb and does not change for person.',
                        'sort_order' => 10,
                        'examples' => [
                            ['example' => 'She can swim very well.', 'translation' => null, 'is_primary' => true, 'sort_order' => 10],
                        ],
                        'lexemes' => [
                            [
                                'slug' => 'can-modal',
                                'lemma' => 'can',
                                'normalized_lemma' => 'can',
                                'part_of_speech' => 'modal',
                                'level' => 'A1',
                                'status' => Lexeme::STATUS_PUBLISHED,
                                'notes' => 'Modal verb for ability and permission.',
                                'examples' => [
                                    ['example' => 'Can you help me?', 'translation' => null, 'is_primary' => true, 'sort_order' => 10],
                                ],
                                'associations' => [
                                    ['related_slug' => 'usually-adverb', 'type' => 'related', 'note' => 'Often appears in beginner routine and ability lessons.', 'sort_order' => 10],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($topics as $topicData) {
            $rules = $topicData['rules'];
            unset($topicData['rules']);

            /** @var GrammarTopic $topic */
            $topic = GrammarTopic::query()->updateOrCreate(
                ['slug' => $topicData['slug']],
                array_merge($topicData, ['language' => 'en', 'status' => GrammarTopic::STATUS_ACTIVE])
            );

            foreach ($rules as $ruleData) {
                $ruleExamples = $ruleData['examples'] ?? [];
                $ruleLexemes = $ruleData['lexemes'] ?? [];
                unset($ruleData['examples'], $ruleData['lexemes']);

                /** @var GrammarRule $rule */
                $rule = GrammarRule::query()->updateOrCreate(
                    ['slug' => $ruleData['slug']],
                    array_merge($ruleData, ['topic_id' => $topic->id, 'language' => 'en'])
                );

                foreach ($ruleExamples as $exampleData) {
                    $rule->examples()->updateOrCreate(
                        ['example' => $exampleData['example']],
                        array_merge($exampleData, ['language' => 'en'])
                    );
                }

                foreach ($ruleLexemes as $index => $lexemeData) {
                    $lexemeExamples = $lexemeData['examples'] ?? [];
                    $lexemeAssociations = $lexemeData['associations'] ?? [];
                    unset($lexemeData['examples'], $lexemeData['associations']);

                    /** @var Lexeme $lexeme */
                    $lexeme = Lexeme::query()->updateOrCreate(
                        ['slug' => $lexemeData['slug']],
                        array_merge($lexemeData, ['language' => 'en'])
                    );

                    $rule->lexemes()->syncWithoutDetaching([
                        $lexeme->id => ['sort_order' => ($index + 1) * 10],
                    ]);

                    foreach ($lexemeExamples as $exampleData) {
                        $lexeme->examples()->updateOrCreate(
                            ['example' => $exampleData['example']],
                            array_merge($exampleData, ['language' => 'en'])
                        );
                    }

                    foreach ($lexemeAssociations as $associationData) {
                        $related = Lexeme::query()->where('slug', $associationData['related_slug'])->first();

                        if ($related === null) {
                            continue;
                        }

                        $lexeme->associations()->updateOrCreate(
                            [
                                'related_lexeme_id' => $related->id,
                                'type' => $associationData['type'],
                            ],
                            [
                                'note' => $associationData['note'] ?? null,
                                'sort_order' => $associationData['sort_order'] ?? 0,
                            ]
                        );
                    }
                }
            }
        }
    }
}
