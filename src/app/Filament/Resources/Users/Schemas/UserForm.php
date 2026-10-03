<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Contracts\Ai\AiAnalysisRunConfig;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->rule(Password::default())
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                TextInput::make('photo'),
                TextInput::make('phone')
                    ->tel(),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('1'),
                TextInput::make('timezone')
                    ->required()
                    ->default('UTC'),
                TextInput::make('ui_language')
                    ->required()
                    ->default('en')
                    ->maxLength(2),
                Select::make('translation_language')
                    ->label('Default translation language (AI analysis)')
                    ->helperText('Used as the default when this user runs AI content analysis, or as their native language on the learner side — distinct from "ui_language" above.')
                    ->options(AiAnalysisRunConfig::TRANSLATION_LANGUAGES)
                    ->native(false),
                CheckboxList::make('roles')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('guard_name', 'web')
                    )
                    ->columns(2)
                    ->descriptions([
                        'admin' => 'Full access to all features',
                        'editor' => 'Can manage content',
                        'moderator' => 'Can moderate user submissions',
                        'user' => 'Regular user access',
                    ])
                    ->required(),
            ]);
    }
}
