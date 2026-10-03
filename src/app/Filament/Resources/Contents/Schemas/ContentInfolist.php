<?php

namespace App\Filament\Resources\Contents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('title'),
                        TextEntry::make('type'),
                        TextEntry::make('language'),
                    ]),
                Section::make('Source')
                    ->schema([
                        TextEntry::make('source_url')
                            ->label('Source URL (YouTube)')
                            ->url(fn ($record): ?string => $record->source_url ?: null)
                            ->visible(fn ($record) => $record->type === 'youtube' && (string) $record->source_url !== ''),
                        TextEntry::make('source_text')
                            ->label('Transcript')
                            ->placeholder('No transcript')
                            ->columnSpanFull(),
                    ]),
                Section::make('Processing pipeline')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('processing_requested_at')
                            ->label('Pipeline requested')
                            ->dateTime()
                            ->placeholder('Not started'),
                        TextEntry::make('processing_completed_at')
                            ->label('Pipeline completed')
                            ->dateTime()
                            ->placeholder('Not completed'),
                        TextEntry::make('transcript_accepted_at')
                            ->label('Transcript accepted')
                            ->dateTime()
                            ->placeholder('Not accepted'),
                        TextEntry::make('transcriptAccepter.name')
                            ->label('Transcript accepted by')
                            ->placeholder('Not accepted'),
                        TextEntry::make('analysis_stale_at')
                            ->label('Analysis stale since')
                            ->dateTime()
                            ->placeholder('Not stale'),
                        TextEntry::make('processing_failure_reason')
                            ->label('Processing failure')
                            ->badge()
                            ->color('danger')
                            ->visible(fn ($record): bool => (string) ($record->processing_failure_reason ?? '') !== ''),
                        TextEntry::make('moderation_comment')
                            ->label('Moderation comment')
                            ->visible(fn ($record): bool => (string) ($record->moderation_comment ?? '') !== ''),
                    ]),
                Section::make('AI analysis')
                    ->columns(2)
                    ->visible(fn ($record): bool => $record->latestAnalysisRun !== null)
                    ->schema([
                        TextEntry::make('latestAnalysisRun.status')
                            ->label('AI analysis status')
                            ->badge()
                            ->placeholder('Not run yet'),
                        TextEntry::make('latestAnalysisRun.failure_reason')
                            ->label('AI analysis failure')
                            ->badge()
                            ->color('danger')
                            ->visible(fn ($record): bool => (string) ($record->latestAnalysisRun?->failure_reason ?? '') !== ''),
                    ]),
            ]);
    }
}
