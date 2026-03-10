<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExecutionAuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\ExecutionAuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'agent_id',
        'type',
        'detail',
        'exit_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
        ];
    }
}
