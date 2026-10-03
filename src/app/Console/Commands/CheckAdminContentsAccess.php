<?php

namespace App\Console\Commands;

use App\Filament\Resources\Contents\ContentResource;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class CheckAdminContentsAccess extends Command
{
    protected $signature = 'check:admin-contents-access {email=admin@example.com}';

    protected $description = 'Check if admin user can access contents page';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return self::FAILURE;
        }

        $this->info("Checking access for: {$user->name} ({$user->email})");
        $this->newLine();

        // Check roles
        $roles = $user->getRoleNames();
        $this->info("Roles: " . $roles->toJson());
        $this->info("Has admin role: " . ($user->hasRole('admin') ? 'YES ✓' : 'NO ✗'));

        // Check gates
        $this->newLine();
        $this->info("Gate permissions:");
        $this->line("  access-admin-panel: " . ($user->can('access-admin-panel') ? 'YES ✓' : 'NO ✗'));
        $this->line("  manage-content: " . ($user->can('manage-content') ? 'YES ✓' : 'NO ✗'));
        $this->line("  moderate-content: " . ($user->can('moderate-content') ? 'YES ✓' : 'NO ✗'));

        // Check ContentResource permissions
        $this->newLine();
        $this->info("ContentResource permissions:");
        
        // Set user as authenticated for Gate checks
        auth()->setUser($user);
        
        $canViewAny = ContentResource::canViewAny();
        $canCreate = ContentResource::canCreate();
        
        $this->line("  canViewAny: " . ($canViewAny ? 'YES ✓' : 'NO ✗'));
        $this->line("  canCreate: " . ($canCreate ? 'YES ✓' : 'NO ✗'));

        // Check if user can access panel
        $panel = app('filament')->getPanel('admin');
        $canAccessPanel = $user->canAccessPanel($panel);
        $this->line("  canAccessPanel: " . ($canAccessPanel ? 'YES ✓' : 'NO ✗'));

        $this->newLine();
        if ($canViewAny && $canAccessPanel) {
            $this->info("✓ User SHOULD be able to access /admin/contents");
            $this->line("  URL: http://localhost:8080/admin/contents");
        } else {
            $this->error("✗ User CANNOT access /admin/contents");
            $this->warn("  Fix: Ensure user has 'admin' role and permissions are set correctly");
        }

        return self::SUCCESS;
    }
}
