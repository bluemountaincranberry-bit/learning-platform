<?php

namespace App\Filament\Resources\Lexemes\RelationManagers;

use App\Modules\Content\Domain\Models\Lexeme;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssociationsRelationManager extends RelationManager
{
    protected static string $relationship = 'associations';

    protected static ?string $title = 'Related words';

    public const TYPES = [
        'synonym' => 'Synonym',
        'antonym' => 'Antonym',
        'related' => 'Related',
        'collocation' => 'Collocation',
    ];

    public function form(Schema $schema): Schema
    {
        $lexemeId = $this->getOwnerRecord()->getKey();

        return $schema->components([
            Select::make('related_lexeme_id')
                ->label('Related lexeme')
                ->options(
                    fn () => Lexeme::query()
                        ->where('id', '!=', $lexemeId)
                        ->orderBy('lemma')
                        ->limit(100)
                        ->pluck('lemma', 'id')
                )
                ->searchable()
                ->getSearchResultsUsing(
                    fn (string $search) => Lexeme::query()
                        ->where('id', '!=', $lexemeId)
                        ->where('lemma', 'like', "%{$search}%")
                        ->orderBy('lemma')
                        ->limit(50)
                        ->pluck('lemma', 'id')
                )
                ->required()
                ->distinct(),
            Select::make('type')
                ->options(self::TYPES)
                ->default('synonym')
                ->native(false),
            TextInput::make('note')->maxLength(255),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('related_lexeme.lemma')
            ->columns([
                TextColumn::make('relatedLexeme.lemma')->label('Related word')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('note')->wrap()->limit(60)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->options(self::TYPES),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
