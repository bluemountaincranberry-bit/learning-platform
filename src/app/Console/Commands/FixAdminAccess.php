<?php

namespace App\Console\Commands;

use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class FixAdminAccess extends Command
{
    protected $signature = 'fix:admin-access {email=admin@example.com}';

    protected $description = 'Ensure user has admin role and can access admin panel';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return self::FAILURE;
        }

        $this->info("Found user: {$user->name} ({$user->email})");

        // Ensure admin role exists
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->info("Admin role exists: " . ($adminRole ? 'YES' : 'NO'));

        // Check current roles
        $currentRoles = $user->getRoleNames();
        $this->info("Current roles: " . $currentRoles->toJson());

        // Assign admin role if not present
        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
            $this->info("✓ Assigned 'admin' role to user");
        } else {
            $this->info("✓ User already has 'admin' role");
        }

        // Verify permissions
        $permissions = $user->getAllPermissions()->pluck('name');
        $this->info("User permissions: " . $permissions->toJson());

        // Check if can access panel
        $panel = app('filament')->getPanel('admin');
        $canAccess = $user->canAccessPanel($panel);
        $this->info("Can access admin panel: " . ($canAccess ? 'YES ✓' : 'NO ✗'));

        if (!$canAccess) {
            $this->warn("User still cannot access panel. Checking gate...");
            $gateResult = $user->can('access-admin-panel');
            $this->info("Gate 'access-admin-panel' result: " . ($gateResult ? 'YES ✓' : 'NO ✗'));
        }

        $this->newLine();
        $this->info("Login credentials:");
        $this->line("  Email: {$user->email}");
        $this->line("  Password: password (default)");
        $this->line("  Admin URL: http://localhost:8080/admin");

        return self::SUCCESS;
    }
}
