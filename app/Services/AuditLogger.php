<?php

namespace App\Services;

use App\Models\ExecutionAuditLog;

class AuditLogger
{
    public function __construct(
        protected ?string $organizationId = null,
        protected ?string $workItemId = null,
        protected ?string $agentId = null,
    ) {}

    public function logCommand(string $command, int $exitCode): void
    {
        ExecutionAuditLog::create([
            'organization_id' => $this->organizationId,
            'work_item_id' => $this->workItemId,
            'agent_id' => $this->agentId,
            'type' => 'command',
            'detail' => $command,
            'exit_code' => $exitCode,
        ]);
    }

    public function logFileWrite(string $path): void
    {
        ExecutionAuditLog::create([
            'organization_id' => $this->organizationId,
            'work_item_id' => $this->workItemId,
            'agent_id' => $this->agentId,
            'type' => 'file_write',
            'detail' => $path,
        ]);
    }

    public function logFileEdit(string $path): void
    {
        ExecutionAuditLog::create([
            'organization_id' => $this->organizationId,
            'work_item_id' => $this->workItemId,
            'agent_id' => $this->agentId,
            'type' => 'file_edit',
            'detail' => $path,
        ]);
    }
}
