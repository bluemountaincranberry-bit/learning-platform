<?php

namespace App\Modules\User\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/** User aggregate root owned by the User module. */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $guarded = [];

    protected static function newFactory(): \Database\Factories\UserFactory
    {
        return \Database\Factories\UserFactory::new();
    }

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    /**
     * Keep the User aggregate on the application's web guard.
     */
    public function guardName(): string
    {
        return 'web';
    }

    /**
     * Persist the legacy morph alias until existing role/token rows have been
     * migrated. Spatie resolves the alias to this canonical class at runtime,
     * while the stored type remains compatible with the existing database.
     */
    public function getMorphClass(): string
    {
        return 'App\\Models\\User';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function learningPreferences(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserLearningPreference::class);
    }
}
