<?php

namespace App\Filament\Resources\Contents\Schemas;

use App\Contracts\VideoTitleFetcherInterface;
use App\Exceptions\SubtitleExtractionException;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Contracts\SubtitleTextExtractorInterface;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(array_combine(Content::TYPES, Content::TYPES))
                    ->required()
                    ->live(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Auto-filled from the YouTube video once you paste a source URL below — edit freely if you want a different title.'),
                TextInput::make('source_url')
                    ->label(fn (Get $get): string => match ($get('type')) {
                        'youtube' => 'Source URL (required for YouTube)',
                        'song' => 'Link to song/lyrics (optional)',
                        'book' => 'Link (optional)',
                        'grammar' => 'Link (optional)',
                        'movie' => 'Link (optional, e.g. IMDb)',
                        default => 'Source URL',
                    })
                    ->url()
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => $get('type') === 'youtube')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if ($get('type') !== 'youtube' || blank($state) || filled($get('title'))) {
                            return;
                        }

                        $title = app(VideoTitleFetcherInterface::class)->fetch($state);

                        if ($title !== null) {
                            $set('title', $title);
                        }
                    }),
                FileUpload::make('source_file_path')
                    ->label('Subtitle file (.srt/.vtt)')
                    ->helperText('The dialogue transcript below is filled in automatically from this file — no video is shown, only the text.')
                    ->visible(fn (Get $get): bool => $get('type') === 'movie')
                    ->disk('local')
                    ->directory('content-subtitles')
                    ->extraAttributes(['accept' => '.srt,.vtt'])
                    ->rules(['extensions:srt,vtt'])
                    ->maxSize(2048)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        if ($get('type') !== 'movie' || blank($state)) {
                            return;
                        }

                        $path = is_object($state) && method_exists($state, 'getRealPath')
                            ? $state->getRealPath()
                            : Storage::disk('local')->path((string) $state);

                        try {
                            $text = app(SubtitleTextExtractorInterface::class)->extractFromPath($path);
                        } catch (SubtitleExtractionException $e) {
                            Notification::make()
                                ->title('Could not read subtitle file')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $set('source_text', $text);
                    }),
                Textarea::make('source_text')
                    ->label(fn (Get $get): string => match ($get('type')) {
                        'song' => 'Lyrics',
                        'book' => 'Excerpt',
                        'grammar' => 'Rule and examples',
                        'movie' => 'Dialogue transcript (auto-filled from subtitle file)',
                        default => 'Source text / transcript',
                    })
                    ->placeholder(fn (Get $get): string => $get('type') === 'grammar' ? 'Rule and examples (paste to tokenize)' : 'Paste text to tokenize into words')
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('language')
                    ->required()
                    ->maxLength(8)
                    ->default('en'),
                Select::make('level')
                    ->label('CEFR level')
                    ->options(array_combine(Content::CEFR_LEVELS, Content::CEFR_LEVELS))
                    ->nullable()
                    ->in(Content::CEFR_LEVELS),
                Select::make('origin')
                    ->options([
                        'curated' => 'curated',
                        'user-submitted' => 'user-submitted',
                        'ai-chat' => 'ai-chat',
                    ])
                    ->default('curated')
                    ->required(),
                Select::make('status')
                    ->options(array_combine(Content::STATUSES, Content::STATUSES))
                    ->in(Content::STATUSES)
                    ->default('draft')
                    ->live()
                    ->required()
                    ->hiddenOn('create')
                    ->helperText('New content starts as "draft" and moves through the pipeline automatically once saved.'),
                Placeholder::make('processing_failure_reason')
                    ->label('Processing failure')
                    ->content(fn ($record): string => (string) ($record->processing_failure_reason ?? ''))
                    ->visible(fn ($record): bool => (string) ($record->processing_failure_reason ?? '') !== '')
                    ->columnSpanFull(),
                Textarea::make('moderation_comment')
                    ->visible(fn (Get $get): bool => $get('status') === 'rejected')
                    ->required(fn (Get $get): bool => $get('status') === 'rejected')
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }
}
