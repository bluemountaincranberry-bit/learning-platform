<?php

namespace Database\Seeders;

use App\Modules\Content\Application\CanonicalLexemeSyncService;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Learning\Domain\Models\LearningProgress;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = [
            'access admin panel',
            'moderate content',
            'manage content',
            'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);

        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $editor->syncPermissions(['access admin panel', 'manage content']);

        $moderator = Role::firstOrCreate(['name' => 'moderator', 'guard_name' => 'web']);
        $moderator->syncPermissions(['access admin panel', 'moderate content']);

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $userRole->syncPermissions([]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $user->assignRole('user');

        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
        $adminUser->assignRole('admin');

        // WithoutModelEvents (class-level, above) suppresses ContentLexeme::booted()'s
        // auto-sync-to-canonical-lexeme hook, so each occurrence created below is
        // synced explicitly instead — otherwise lexeme_id would stay null.
        $introLexemes = Content::query()->create([
            'type' => 'youtube',
            'title' => 'Intro Lesson A1',
            'language' => 'en',
            'level' => 'A1',
            'origin' => 'curated',
            'status' => 'ready',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'created_by' => $adminUser->id,
        ])->lexemes()->createMany([
            ['type' => ContentLexeme::TYPE_WORD, 'text' => 'hello', 'sort_order' => 1],
            ['type' => ContentLexeme::TYPE_WORD, 'text' => 'world', 'sort_order' => 2],
            ['type' => ContentLexeme::TYPE_WORD, 'text' => 'learn', 'sort_order' => 3],
        ]);
        $introLexemes->each(fn (ContentLexeme $lexeme) => app(CanonicalLexemeSyncService::class)->sync($lexeme));

        $story = Content::query()->create([
            'type' => 'book',
            'title' => 'Short Story Practice',
            'language' => 'en',
            'level' => 'B1',
            'origin' => 'curated',
            'status' => 'ready',
            'created_by' => $adminUser->id,
        ]);
        $story->lexemes()->createMany([
            ['type' => ContentLexeme::TYPE_WORD, 'text' => 'story', 'sort_order' => 1],
            ['type' => ContentLexeme::TYPE_WORD, 'text' => 'practice', 'sort_order' => 2],
        ])->each(fn (ContentLexeme $lexeme) => app(CanonicalLexemeSyncService::class)->sync($lexeme));

        $demoContent = Content::query()->where('title', 'Intro Lesson A1')->first();
        if ($demoContent !== null) {
            $demoLexemes = $demoContent->lexemes()->orderBy('sort_order')->get();

            if ($demoLexemes->isNotEmpty()) {
                UserLexemeProgress::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'lexeme_id' => $demoLexemes[0]->lexeme_id,
                    ],
                    ['content_lexeme_id' => $demoLexemes[0]->id, 'learned_at' => now()]
                );

                if ($demoLexemes->has(1)) {
                    UserLexemeProgress::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'lexeme_id' => $demoLexemes[1]->lexeme_id,
                        ],
                        ['content_lexeme_id' => $demoLexemes[1]->id, 'learned_at' => now()]
                    );
                }
            }

            LearningProgress::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'content_id' => $demoContent->id,
                ],
                [
                    'total_answers' => 12,
                    'known_answers' => 9,
                    'unknown_answers' => 3,
                    'accuracy' => 75.0,
                ]
            );
        }

        $this->call(GrammarCatalogSeeder::class);
        $this->call(LearningFlowProfileSeeder::class);
    }
}
