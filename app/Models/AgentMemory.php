<?php

namespace App\Models;

use App\Concerns\BelongsToUserOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMemory extends Model
{
    /** @use HasFactory<\Database\Factories\AgentMemoryFactory> */
    use BelongsToUserOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'agent_id',
        'type',
        'content',
        'summary',
        'importance',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'importance' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
