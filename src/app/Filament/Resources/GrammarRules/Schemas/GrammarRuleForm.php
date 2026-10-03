<?php

namespace App\Filament\Resources\GrammarRules\Schemas;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\GrammarRule;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class GrammarRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('topic_id')
                    ->label('Topic')
                    ->options(fn () => GrammarTopic::query()->orderBy('sort_order')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get): void {
                        if ($operation === 'create' && blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('language')
                    ->required()
                    ->maxLength(8)
                    ->default('en'),
                Select::make('level')
                    ->label('CEFR level')
                    ->options(array_combine(Content::CEFR_LEVELS, Content::CEFR_LEVELS))
                    ->native(false),
                Select::make('status')
                    ->options(array_combine(GrammarRule::STATUSES, GrammarRule::STATUSES))
                    ->default(GrammarRule::STATUS_DRAFT)
                    ->required()
                    ->native(false),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Textarea::make('summary')
                    ->rows(2)
                    ->columnSpanFull(),
                MarkdownEditor::make('body')
                    ->label('Explanation')
                    ->helperText('Use "AI: Draft/Improve" above to generate or revise this from the rule\'s title, topic and examples.')
                    ->columnSpanFull(),
            ]);
    }
}
