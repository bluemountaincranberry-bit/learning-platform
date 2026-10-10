<?php

namespace App\Console\Commands;

use App\Modules\Interview\Application\InterviewStarterSeeder;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;

class SeedInterviewStarterCommand extends Command
{
    protected $signature = 'interview:seed-starter {--user= : Seed one learner by user ID; defaults to all learners}';

    protected $description = 'Add the repeatable 15-question Interview starter bank without overwriting learner content';

    public function handle(InterviewStarterSeeder $seeder): int
    {
        $users = $this->option('user')
            ? User::query()->whereKey($this->option('user'))->get()
            : User::query()->get();

        foreach ($users as $user) {
            $seeder->seedForUser((int) $user->id);
            $this->info("Interview starter bank ready for {{$user->email}}");
        }

        if ($users->isEmpty()) {
            $this->warn('No learners found.');
        }

        return self::SUCCESS;
    }
}
