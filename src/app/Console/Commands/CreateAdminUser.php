<?php

namespace App\Console\Commands;

use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin {email=admin@example.com} {--password=}';

    protected $description = 'Create or update admin user with admin role';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?: 'password';

        // Ensure admin role exists
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Find or create user
        $user = User::firstOrNew(['email' => $email]);

        if ($user->exists) {
            $this->info("Updating existing user: {$email}");
        } else {
            $this->info("Creating new user: {$email}");
            $user->name = 'Admin User';
        }

        $user->password = Hash::make($password);
        $user->email_verified_at = now();
        $user->save();

        // Ensure admin role is assigned
        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
            $this->info("✓ Assigned 'admin' role");
        } else {
            $this->info("✓ User already has 'admin' role");
        }

        // Ensure all required permissions exist, then sync them to admin role
        $permissions = [
            'access admin panel',
            'moderate content',
            'manage content',
            'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $adminRole->syncPermissions($permissions);

        $this->newLine();
        $this->info("✓ Admin user ready!");
        $this->line("  Email: {$user->email}");
        $this->line("  Password: {$password}");
        $this->line("  Admin URL: http://localhost:8080/admin");
        $this->newLine();
        $this->warn("⚠ If you still see 'forbidden', try:");
        $this->line("  1. Clear cache: php artisan cache:clear");
        $this->line("  2. Clear config: php artisan config:clear");
        $this->line("  3. Logout and login again");

        return self::SUCCESS;
    }
}
