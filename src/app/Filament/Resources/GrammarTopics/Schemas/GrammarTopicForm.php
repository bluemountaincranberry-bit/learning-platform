<?php

namespace App\Filament\Resources\GrammarTopics\Schemas;

use App\Modules\Content\Domain\Models\GrammarTopic;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class GrammarTopicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
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
                Select::make('status')
                    ->options(array_combine(GrammarTopic::STATUSES, GrammarTopic::STATUSES))
                    ->default(GrammarTopic::STATUS_DRAFT)
                    ->required()
                    ->native(false),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
