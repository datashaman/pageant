<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory, HasUuids;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function repos(): HasMany
    {
        return $this->hasMany(Repo::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }
}
