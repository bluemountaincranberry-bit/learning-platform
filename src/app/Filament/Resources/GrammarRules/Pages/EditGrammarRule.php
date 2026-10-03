<?php

namespace App\Filament\Resources\GrammarRules\Pages;

use App\Filament\Resources\GrammarRules\Actions\AiDraftGrammarContentAction;
use App\Filament\Resources\GrammarRules\Actions\GenerateGrammarExercisesAction;
use App\Filament\Resources\GrammarRules\GrammarRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrammarRule extends EditRecord
{
    protected static string $resource = GrammarRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AiDraftGrammarContentAction::make($this->record),
            GenerateGrammarExercisesAction::make($this->record),
            DeleteAction::make(),
        ];
    }
}
