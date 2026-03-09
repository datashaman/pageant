<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanStep extends Model
{
    /** @use HasFactory<\Database\Factories\PlanStepFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'plan_id',
        'agent_id',
        'order',
        'status',
        'description',
        'depends_on',
        'started_at',
        'completed_at',
        'result',
        'progress_summary',
        'turns_used',
        'conversation_id',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'depends_on' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }
}
