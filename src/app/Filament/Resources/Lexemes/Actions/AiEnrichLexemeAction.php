<?php

namespace App\Filament\Resources\Lexemes\Actions;

use App\Modules\Ai\Application\LexemeEnrichmentService;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Support\AiConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;

/**
 * Proposes typed related words/examples/translation via AI (task 10.4 —
 * synonyms, antonyms, word family, phrasal verbs, collocations, etc., not
 * just synonyms), matching proposed lemmas against the existing catalog
 * (CandidateMatchingService, via LexemeEnrichmentService::propose) —
 * nothing is written yet. The modal pre-fills one row per proposal,
 * defaulted to accepted; the admin unchecks/edits what they don't want, and
 * only Submit (LexemeEnrichmentService::applyAccepted) writes anything.
 */
class AiEnrichLexemeAction
{
    public static function make(Lexeme $record): Action
    {
        return Action::make('aiEnrichLexeme')
            ->label('AI: Enrich')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->visible(fn (): bool => AiConfig::isEnabled())
            ->schema([
                Repeater::make('related')
                    ->schema([
                        TextInput::make('lemma')->required()->disabled()->dehydrated(),
                        TextInput::make('type')->disabled()->dehydrated(),
                        TextInput::make('gloss')->disabled()->dehydrated(),
                        Hidden::make('matched_lexeme_id'),
                        Toggle::make('accept')->label('Add this relation'),
                    ])
                    ->columns(4)
                    ->addable(false)
                    ->deletable(false)
                    ->itemLabel(fn (array $state): ?string => $state['lemma'] ?? null),
                Repeater::make('examples')
                    ->schema([
                        Textarea::make('example')->required()->rows(2)->columnSpanFull(),
                        Textarea::make('translation')->rows(2)->columnSpanFull(),
                        Toggle::make('accept')->label('Add this example'),
                    ])
                    ->addable(false)
                    ->deletable(false),
                Repeater::make('translations')
                    ->schema([
                        TextInput::make('language')->required()->maxLength(8),
                        TextInput::make('translation')->required(),
                        Toggle::make('accept')->label('Add this translation'),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->deletable(false),
            ])
            ->mountUsing(function (Schema $schema) use ($record): void {
                $proposal = app(LexemeEnrichmentService::class)->propose($record->id);

                $withAccept = fn (array $rows): array => array_map(fn (array $row): array => [...$row, 'accept' => true], $rows);

                $schema->fill([
                    'related' => $withAccept($proposal['related']),
                    'examples' => $withAccept($proposal['examples']),
                    'translations' => $withAccept($proposal['translations']),
                ]);
            })
            ->action(function (array $data) use ($record): void {
                $created = app(LexemeEnrichmentService::class)->applyAccepted($record->id, $data);

                Notification::make()
                    ->title(sprintf(
                        'Added %d related word(s), %d example(s), %d translation(s)',
                        $created['related'],
                        $created['examples'],
                        $created['translations']
                    ))
                    ->success()
                    ->send();
            });
    }
}
