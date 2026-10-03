<?php

namespace App\Modules\Learning\Domain\Models;

use App\Modules\Learning\Application\LearningFlowConfigValidator;
use App\Support\HasRevisions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class LearningFlowProfile extends Model
{
    use HasFactory, HasRevisions;

    protected array $revisionable = ['name', 'description', 'status', 'version', 'config', 'published_at'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['config' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if ($profile->exists && $profile->getOriginal('status') === 'published' && $profile->isDirty()) {
                throw ValidationException::withMessages(['status' => 'Published flows are immutable. Clone the profile to create a new version.']);
            }
            $profile->config = app(LearningFlowConfigValidator::class)->validate((array) $profile->config);
        });
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LearningFlowAssignment::class);
    }
}
