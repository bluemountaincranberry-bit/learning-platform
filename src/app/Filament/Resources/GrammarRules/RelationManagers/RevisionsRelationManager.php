<?php

namespace App\Filament\Resources\GrammarRules\RelationManagers;

use App\Modules\Infrastructure\Domain\Models\EntityRevision;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('causer')
                    ->label('Who')
                    ->state(fn (EntityRevision $record): string => $record->causer?->name ?? 'System'),
                TextColumn::make('source')->badge(),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->wrap()
                    ->state(function (EntityRevision $record): string {
                        return collect($record->changes)
                            ->map(function (array $change, string $field): string {
                                $old = self::summarize($change['old'] ?? null);
                                $new = self::summarize($change['new'] ?? null);

                                return "{$field}: {$old} → {$new}";
                            })
                            ->implode("\n");
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    private static function summarize(mixed $value): string
    {
        if ($value === null) {
            return '(empty)';
        }

        $text = (string) $value;

        return mb_strlen($text) > 60 ? mb_substr($text, 0, 60).'…' : $text;
    }
}
