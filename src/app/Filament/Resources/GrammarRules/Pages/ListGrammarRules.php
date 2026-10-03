<?php

namespace App\Filament\Resources\GrammarRules\Pages;

use App\Filament\Resources\GrammarRules\GrammarRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrammarRules extends ListRecords
{
    protected static string $resource = GrammarRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
