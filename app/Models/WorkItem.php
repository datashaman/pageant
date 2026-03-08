<?php

namespace App\Models;

use App\Concerns\BelongsToUserOrganization;
use App\Concerns\HasSource;
use App\Events\WorkItemDeleted;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkItem extends Model
{
    /** @use HasFactory<\Database\Factories\WorkItemFactory> */
    use BelongsToUserOrganization, HasFactory, HasSource, HasUuids;

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'deleted' => WorkItemDeleted::class,
    ];

    protected $fillable = [
        'organization_id',
        'project_id',
        'title',
        'description',
        'board_id',
        'source',
        'source_reference',
        'source_url',
        'status',
        'conversation_id',
        'worktree_path',
        'worktree_branch',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function activePlan(): ?Plan
    {
        return $this->plans()
            ->whereIn('status', ['pending', 'approved', 'running', 'paused'])
            ->latest()
            ->first();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
