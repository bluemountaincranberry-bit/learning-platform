<?php

namespace App\Filament\Resources\Contents\RelationManagers;

use App\Modules\Content\Domain\Models\GrammarRule;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Direct attach/detach of an already-published GrammarRule to a Content
 * record. Independent of the AI-extract -> accept-candidate -> apply flow
 * (GrammarCandidatesRelationManager / ApplyAiCandidatesAction) — both write
 * to the same content_rule_links pivot, so there's no conflict between them.
 */
class GrammarRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'grammarRules';

    protected static ?string $title = 'Grammar rules';

    /**
     * Filament hides Attach/Detach (like Create/Edit/Delete) by default on
     * ViewRecord pages. This resource's ViewContent page is already a fully
     * interactive dashboard (pipeline/transcript/AI actions all live there),
     * so keep this relation manager writable there too rather than forcing
     * admins over to the Edit page just to attach a grammar rule.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('note')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('topic.name')->label('Topic')->placeholder('—'),
                TextColumn::make('level')->label('CEFR')->badge()->placeholder('—'),
                TextColumn::make('pivot.note')->label('Note')->placeholder('—')->wrap(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('status', GrammarRule::STATUS_PUBLISHED))
                    ->recordSelectSearchColumns(['title'])
                    ->schema(fn (AttachAction $action): array => [
                        // recordSelectOptionsQuery() only limits the dropdown's
                        // options — it doesn't stop a tampered request from
                        // attaching a non-published id, so also enforce it as
                        // a hard validation rule on the submitted value.
                        $action->getRecordSelect()->rule('exists:grammar_rules,id,status,'.GrammarRule::STATUS_PUBLISHED),
                        Textarea::make('note')->rows(2),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
