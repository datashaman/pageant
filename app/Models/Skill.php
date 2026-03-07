<?php

namespace App\Models;

use App\Concerns\HasSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    /** @use HasFactory<\Database\Factories\SkillFactory> */
    use HasFactory, HasSource, HasUuids;

    protected function casts(): array
    {
        return [
            'allowed_tools' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class);
    }

    public function repos(): BelongsToMany
    {
        return $this->belongsToMany(Repo::class);
    }
}
