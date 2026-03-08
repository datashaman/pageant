<?php

namespace App\Models;

use App\Concerns\BelongsToUserOrganization;
use App\Concerns\HasSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Repo extends Model
{
    /** @use HasFactory<\Database\Factories\RepoFactory> */
    use BelongsToUserOrganization, HasFactory, HasSource, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'source',
        'source_reference',
        'source_url',
    ];

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

    /**
     * Infer the project ID when the repo belongs to exactly one project.
     */
    public function inferProjectId(): ?string
    {
        $projects = $this->projects()->limit(2)->pluck('projects.id');

        if ($projects->count() === 1) {
            return $projects->first();
        }

        return null;
    }
}
