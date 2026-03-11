<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceReference extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceReferenceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'source',
        'source_reference',
        'source_url',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Scope to find GitHub workspace references matching a repo (or repo#issue) pattern,
     * scoped to the current user's organization.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForGithubRepo(Builder $query, string $repo): Builder
    {
        return $query->where('source', 'github')
            ->whereHas('workspace', fn ($q) => $q->forCurrentOrganization())
            ->where(function ($q) use ($repo) {
                $q->where('source_reference', $repo)
                    ->orWhere('source_reference', 'LIKE', $repo.'#%');
            });
    }
}
