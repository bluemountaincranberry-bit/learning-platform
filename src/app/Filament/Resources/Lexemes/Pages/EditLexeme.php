<?php

namespace App\Filament\Resources\Lexemes\Pages;

use App\Filament\Resources\Lexemes\Actions\AiEnrichLexemeAction;
use App\Filament\Resources\Lexemes\LexemeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLexeme extends EditRecord
{
    protected static string $resource = LexemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AiEnrichLexemeAction::make($this->record),
            DeleteAction::make(),
        ];
    }
}
