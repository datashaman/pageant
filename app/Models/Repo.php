<?php

namespace App\Models;

use App\Concerns\HasSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Repo extends Model
{
    /** @use HasFactory<\Database\Factories\RepoFactory> */
    use HasFactory, HasSource, HasUuids;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}
