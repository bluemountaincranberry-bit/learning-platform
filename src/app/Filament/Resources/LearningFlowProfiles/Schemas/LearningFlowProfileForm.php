<?php

namespace App\Filament\Resources\LearningFlowProfiles\Schemas;

use App\Modules\Learning\Application\LearningFlowDefaults;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LearningFlowProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])->default('draft')->required()->native(false),
            TextInput::make('version')->numeric()->default(1)->minValue(1)->required(),
            Textarea::make('description')->columnSpanFull(),
            Textarea::make('config')
                ->label('Flow configuration (JSON)')
                ->helperText('Use Preview/validation before publishing. The server rejects invalid activities, stages and limits.')
                ->rows(24)
                ->required()
                ->formatStateUsing(fn ($state): string => is_array($state) ? (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $state)
                ->dehydrateStateUsing(function (?string $state): array {
                    $decoded = json_decode((string) $state, true);
                    if (! is_array($decoded)) {
                        throw new \InvalidArgumentException('Flow configuration must be valid JSON.');
                    }

                    return $decoded;
                })
                ->default(fn (): string => (string) json_encode(LearningFlowDefaults::balanced(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                ->columnSpanFull(),
        ]);
    }
}
