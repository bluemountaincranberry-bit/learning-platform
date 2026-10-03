<?php

namespace App\Filament\Resources\Contents\RelationManagers;

use App\Exceptions\AiClientException;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Ai\Application\AiExplainLexemeService;
use App\Support\AiConfig;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LexemesRelationManager extends RelationManager
{
    protected static string $relationship = 'lexemes';

    protected static ?string $title = 'Study words';

    protected static ?string $modelLabel = 'lexeme';

    protected static ?string $pluralModelLabel = 'lexemes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('text')->searchable()->sortable(),
                TextColumn::make('sort_order')->sortable()->toggleable(),
                TextColumn::make('frequency')->sortable()->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->paginated([10, 25, 50])
            ->actions([
                Action::make('suggestCefrLevel')
                    ->label('Suggest CEFR level')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->visible(fn (): bool => AiConfig::isEnabled())
                    ->action(function (ContentLexeme $record): void {
                        $service = app(AiExplainLexemeService::class);
                        try {
                            $level = $service->suggestLevel($record->text);
                            Notification::make()
                                ->title('Suggested CEFR level')
                                ->body("For \"{$record->text}\": **{$level}**. You can copy this to the content level if needed.")
                                ->success()
                                ->send();
                        } catch (AiClientException $e) {
                            Notification::make()
                                ->title('AI service unavailable')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
