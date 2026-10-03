<?php

namespace App\Filament\Resources\Lexemes\Schemas;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class LexemeForm
{
    /**
     * @deprecated Use Lexeme::PARTS_OF_SPEECH — moved there (task 9.7) so
     * SuggestLexemeLevelJob can validate an AI-suggested part_of_speech
     * against the same enum without depending on the Filament admin layer.
     * Kept as an alias so this class's own external references don't break.
     */
    public const PARTS_OF_SPEECH = Lexeme::PARTS_OF_SPEECH;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('lemma')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get): void {
                        if ($operation !== 'create') {
                            return;
                        }
                        if (blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                        if (blank($get('normalized_lemma'))) {
                            $set('normalized_lemma', Str::lower((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('normalized_lemma')
                    ->required()
                    ->helperText('Lowercased form used for de-duplication within a language. Auto-filled from the lemma when left blank.')
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('language', $get('language'))
                    ),
                TextInput::make('language')
                    ->required()
                    ->maxLength(8)
                    ->default('en'),
                Select::make('part_of_speech')
                    ->options(self::PARTS_OF_SPEECH)
                    ->native(false),
                Select::make('level')
                    ->label('CEFR level')
                    ->options(array_combine(Content::CEFR_LEVELS, Content::CEFR_LEVELS))
                    ->native(false),
                Select::make('status')
                    ->options(array_combine(Lexeme::STATUSES, Lexeme::STATUSES))
                    ->default(Lexeme::STATUS_DRAFT)
                    ->required()
                    ->native(false),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
