<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agent extends Model
{
    /** @use HasFactory<\Database\Factories\AgentFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'disallowed_tools' => 'array',
            'background' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    public function repos(): BelongsToMany
    {
        return $this->belongsToMany(Repo::class);
    }
}
