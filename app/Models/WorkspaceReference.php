<?php

namespace App\Models;

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
}
