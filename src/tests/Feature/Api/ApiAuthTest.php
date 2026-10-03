<?php

use App\Modules\User\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

test('guest can register a new account and receive a usable token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'New Learner',
        'email' => 'new-learner@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $token = $response->json('token');

    expect(User::query()->where('email', 'new-learner@example.com')->exists())->toBeTrue();

    $user = User::query()->where('email', 'new-learner@example.com')->first();
    expect($user->hasRole('user'))->toBeTrue();

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.email', 'new-learner@example.com');
});

test('registration rejects a duplicate email and mismatched password confirmation', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Someone',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertJsonValidationErrors(['email']);

    $this->postJson('/api/auth/register', [
        'name' => 'Someone Else',
        'email' => 'fresh@example.com',
        'password' => 'password123',
        'password_confirmation' => 'not-matching',
    ])->assertJsonValidationErrors(['password']);
});

test('user can authenticate and fetch profile via sanctum api guard', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('user');

    $login = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('token');

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);
});

test('guest can request a password reset link for an existing account', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password gives the same response for an unregistered email, to avoid leaking which accounts exist', function () {
    Notification::fake();

    $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'If an account exists for that email, a reset link has been sent.');

    Notification::assertNothingSent();
});

test('user can reset their password with a valid token and log in with the new one', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);
    $token = Password::createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'old-password',
    ])->assertUnprocessable();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'new-password-123',
    ])->assertOk();
});

test('resetting password revokes existing tokens', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);
    $existingToken = $user->createToken('spa-token')->plainTextToken;
    $token = Password::createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    $this->withToken($existingToken)->getJson('/api/auth/me')->assertUnauthorized();
});

test('reset password rejects an invalid or expired token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertJsonValidationErrors(['email']);
});
