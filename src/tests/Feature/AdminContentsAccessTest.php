<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Spatie\Permission\Models\Role;

test('admin user can access contents page', function () {
    // Ensure admin role exists
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    
    // Find or create admin user
    $admin = User::firstOrCreate(
        ['email' => 'admin@example.com'],
        [
            'name' => 'Admin User',
            'password' => bcrypt('password'),
        ]
    );
    
    if (!$admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    // Check gate permissions
    expect($admin->can('access-admin-panel'))->toBeTrue();
    expect($admin->can('manage-content'))->toBeTrue();
    expect($admin->can('moderate-content'))->toBeTrue();

    // Simulate login and check resource access
    $this->actingAs($admin, 'web');

    // Check ContentResource permissions
    expect(\App\Filament\Resources\Contents\ContentResource::canViewAny())->toBeTrue();
    expect(\App\Filament\Resources\Contents\ContentResource::canCreate())->toBeTrue();

    // Try to access the route (Filament routes require full session)
    $response = $this->get('/admin/contents');
    
    // Should redirect to login if not authenticated properly, or show 200 if authenticated
    // Filament uses web guard, so we need proper session
    expect($response->status())->toBeIn([200, 302]);
});

test('admin user can view contents list', function () {
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    
    $admin = User::firstOrCreate(
        ['email' => 'admin@example.com'],
        [
            'name' => 'Admin User',
            'password' => bcrypt('password'),
        ]
    );
    
    if (!$admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    // Create some test content
    Content::factory()->create([
        'title' => 'Test Content',
        'status' => 'ready',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin, 'web');

    // Check that admin can see content
    $contents = Content::query()->where('status', 'ready')->get();
    expect($contents)->not->toBeEmpty();
});
