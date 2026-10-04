<?php

use App\Filament\Resources\Contents\Pages\CreateContent;
use App\Modules\Content\Interfaces\Jobs\FetchTranscriptJob;
use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdminForCreateContent(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-create-content@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    test()->actingAs($admin, 'web');

    return $admin;
}

test('creating youtube content with a source url starts the pipeline automatically', function () {
    Queue::fake();
    actingAdminForCreateContent();

    Livewire::test(CreateContent::class)
        ->fillForm([
            'type' => 'youtube',
            'title' => 'My video',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language' => 'en',
            'origin' => 'curated',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $content = Content::query()->where('title', 'My video')->firstOrFail();

    expect($content->status)->toBe('pending');
    Queue::assertPushed(FetchTranscriptJob::class, fn (FetchTranscriptJob $job) => $job->contentId === $content->id);
});

test('VIK-16: creating youtube content for a video already in the catalog is a form error, not a second content', function () {
    Queue::fake();
    actingAdminForCreateContent();
    Content::query()->create([
        'type' => 'youtube', 'title' => 'Existing', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ?si=abc',
    ]);

    Livewire::test(CreateContent::class)
        ->fillForm([
            'type' => 'youtube',
            'title' => 'Same video',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=RD',
            'language' => 'en',
            'origin' => 'curated',
        ])
        ->call('create')
        ->assertHasFormErrors(['source_url']);

    expect(Content::query()->count())->toBe(1);
    Queue::assertNothingPushed();
});

test('creating content with source text tokenizes it automatically', function () {
    Queue::fake();
    actingAdminForCreateContent();

    Livewire::test(CreateContent::class)
        ->fillForm([
            'type' => 'song',
            'title' => 'My song',
            'source_text' => 'La la la.',
            'language' => 'en',
            'origin' => 'curated',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $content = Content::query()->where('title', 'My song')->firstOrFail();

    expect($content->status)->toBe('pending');
    Queue::assertPushed(ProcessContentJob::class, fn (ProcessContentJob $job) => $job->contentId === $content->id);
});

test('uploading a subtitle file for movie content auto-fills the transcript and starts the pipeline', function () {
    Queue::fake();
    actingAdminForCreateContent();

    $srt = "1\n00:00:01,000 --> 00:00:04,000\nHello there.\n\n2\n00:00:04,500 --> 00:00:07,000\nHow are you?\n";
    $file = UploadedFile::fake()->createWithContent('movie.srt', $srt);

    Livewire::test(CreateContent::class)
        ->fillForm([
            'type' => 'movie',
            'title' => 'My movie',
            'language' => 'en',
            'origin' => 'curated',
            'source_file_path' => $file,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $content = Content::query()->where('title', 'My movie')->firstOrFail();

    expect($content->source_text)->toBe("Hello there.\nHow are you?");
    expect($content->status)->toBe('pending');
    Queue::assertPushed(ProcessContentJob::class, fn (ProcessContentJob $job) => $job->contentId === $content->id);
});

test('creating movie content without a subtitle file is left as draft', function () {
    Queue::fake();
    actingAdminForCreateContent();

    Livewire::test(CreateContent::class)
        ->fillForm([
            'type' => 'movie',
            'title' => 'Movie without subtitles',
            'language' => 'en',
            'origin' => 'curated',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $content = Content::query()->where('title', 'Movie without subtitles')->firstOrFail();

    expect($content->status)->toBe('draft');
    Queue::assertNotPushed(ProcessContentJob::class);
    Queue::assertNotPushed(FetchTranscriptJob::class);
});

test('creating content with nothing to process is left as draft', function () {
    Queue::fake();
    actingAdminForCreateContent();

    Livewire::test(CreateContent::class)
        ->fillForm([
            'type' => 'grammar',
            'title' => 'Empty draft',
            'language' => 'en',
            'origin' => 'curated',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $content = Content::query()->where('title', 'Empty draft')->firstOrFail();

    expect($content->status)->toBe('draft');
    Queue::assertNotPushed(ProcessContentJob::class);
    Queue::assertNotPushed(FetchTranscriptJob::class);
});

test('status field is not part of the create form and always starts as draft', function () {
    Queue::fake();
    actingAdminForCreateContent();

    Livewire::test(CreateContent::class)
        ->fillForm([
            'type' => 'grammar',
            'title' => 'Ignores status',
            'language' => 'en',
            'origin' => 'curated',
            'status' => 'ready',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $content = Content::query()->where('title', 'Ignores status')->firstOrFail();

    expect($content->status)->toBe('draft');
});
