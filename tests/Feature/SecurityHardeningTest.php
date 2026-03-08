<?php

use App\Exceptions\CommandDeniedException;
use App\Models\ExecutionAuditLog;
use App\Models\Organization;
use App\Models\WorkItem;
use App\Services\AuditLogger;
use App\Services\CommandPolicy;
use App\Services\LocalExecutionDriver;

describe('CommandPolicy', function () {
    it('allows all commands when no patterns are set', function () {
        $policy = new CommandPolicy;

        expect($policy->isAllowed('ls -la'))->toBeTrue();
        expect($policy->isAllowed('rm -rf /'))->toBeTrue();
        expect($policy->isAllowed('curl https://example.com'))->toBeTrue();
    });

    it('allows only commands matching the allowlist', function () {
        $policy = new CommandPolicy(
            allowedPatterns: ['php artisan *', 'npm run *', 'composer *'],
        );

        expect($policy->isAllowed('php artisan migrate'))->toBeTrue();
        expect($policy->isAllowed('npm run build'))->toBeTrue();
        expect($policy->isAllowed('composer install'))->toBeTrue();
        expect($policy->isAllowed('rm -rf /'))->toBeFalse();
        expect($policy->isAllowed('curl https://evil.com'))->toBeFalse();
    });

    it('denies commands matching the denylist', function () {
        $policy = new CommandPolicy(
            deniedPatterns: ['rm -rf *', 'curl *', 'wget *'],
        );

        expect($policy->isAllowed('ls -la'))->toBeTrue();
        expect($policy->isAllowed('php artisan migrate'))->toBeTrue();
        expect($policy->isAllowed('rm -rf /'))->toBeFalse();
        expect($policy->isAllowed('curl https://evil.com'))->toBeFalse();
        expect($policy->isAllowed('wget https://evil.com'))->toBeFalse();
    });

    it('prioritizes allowlist over denylist when both are set', function () {
        $policy = new CommandPolicy(
            allowedPatterns: ['php artisan *'],
            deniedPatterns: ['rm -rf *'],
        );

        expect($policy->isAllowed('php artisan migrate'))->toBeTrue();
        expect($policy->isAllowed('rm -rf /'))->toBeFalse();
        expect($policy->isAllowed('ls -la'))->toBeFalse();
    });

    it('throws CommandDeniedException on validate for denied commands', function () {
        $policy = new CommandPolicy(
            deniedPatterns: ['rm *'],
        );

        $policy->validate('rm file.txt');
    })->throws(CommandDeniedException::class, 'Command not permitted');

    it('does not throw on validate for allowed commands', function () {
        $policy = new CommandPolicy(
            deniedPatterns: ['rm *'],
        );

        $policy->validate('ls -la');

        expect(true)->toBeTrue();
    });

    it('matches base command against patterns', function () {
        $policy = new CommandPolicy(
            deniedPatterns: ['curl'],
        );

        expect($policy->isAllowed('curl https://example.com'))->toBeFalse();
    });
});

describe('AuditLogger', function () {
    it('logs command execution', function () {
        $organization = Organization::factory()->create();

        $logger = new AuditLogger(
            organizationId: $organization->id,
        );

        $logger->logCommand('echo hello', 0);

        $this->assertDatabaseHas('execution_audit_logs', [
            'organization_id' => $organization->id,
            'type' => 'command',
            'detail' => 'echo hello',
            'exit_code' => 0,
        ]);
    });

    it('logs file write operations', function () {
        $organization = Organization::factory()->create();

        $logger = new AuditLogger(
            organizationId: $organization->id,
        );

        $logger->logFileWrite('src/app.php');

        $this->assertDatabaseHas('execution_audit_logs', [
            'organization_id' => $organization->id,
            'type' => 'file_write',
            'detail' => 'src/app.php',
            'exit_code' => null,
        ]);
    });

    it('logs file edit operations', function () {
        $organization = Organization::factory()->create();

        $logger = new AuditLogger(
            organizationId: $organization->id,
        );

        $logger->logFileEdit('src/app.php');

        $this->assertDatabaseHas('execution_audit_logs', [
            'organization_id' => $organization->id,
            'type' => 'file_edit',
            'detail' => 'src/app.php',
            'exit_code' => null,
        ]);
    });

    it('logs with work item and agent context', function () {
        $organization = Organization::factory()->create();
        $workItem = WorkItem::factory()->create(['organization_id' => $organization->id]);

        $logger = new AuditLogger(
            organizationId: $organization->id,
            workItemId: $workItem->id,
            agentId: 'agent-123',
        );

        $logger->logCommand('php artisan test', 0);

        $this->assertDatabaseHas('execution_audit_logs', [
            'organization_id' => $organization->id,
            'work_item_id' => $workItem->id,
            'agent_id' => 'agent-123',
            'type' => 'command',
        ]);
    });
});

describe('Path validation', function () {
    beforeEach(function () {
        $rawTempDir = sys_get_temp_dir().'/security-test-'.uniqid();
        mkdir($rawTempDir, 0755, true);
        $this->tempDir = realpath($rawTempDir);
        $this->driver = new LocalExecutionDriver($rawTempDir);
    });

    afterEach(function () {
        if (is_dir($this->tempDir)) {
            $this->driver->cleanup();
        }
    });

    it('rejects absolute paths', function () {
        $this->driver->readFile('/etc/passwd');
    })->throws(InvalidArgumentException::class, 'Absolute paths are not allowed');

    it('rejects path traversal with dot-dot segments', function () {
        $this->driver->readFile('../../etc/passwd');
    })->throws(InvalidArgumentException::class, 'Path traversal is not allowed');

    it('rejects path traversal on write operations', function () {
        $this->driver->writeFile('../../etc/evil.txt', 'hacked');
    })->throws(InvalidArgumentException::class, 'Path traversal is not allowed');

    it('rejects deeply nested traversal attempts', function () {
        $this->driver->readFile('foo/bar/../../../etc/passwd');
    })->throws(InvalidArgumentException::class, 'Path traversal is not allowed');

    it('rejects traversal in edit operations', function () {
        $this->driver->editFile('../outside.txt', 'old', 'new');
    })->throws(InvalidArgumentException::class, 'Path traversal is not allowed');

    it('detects symlink escapes on read', function () {
        $outsideDir = sys_get_temp_dir().'/outside-'.uniqid();
        mkdir($outsideDir, 0755, true);
        file_put_contents($outsideDir.'/secret.txt', 'secret data');

        symlink($outsideDir, $this->tempDir.'/escape');

        try {
            $this->driver->readFile('escape/secret.txt');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } finally {
            @unlink($outsideDir.'/secret.txt');
            @rmdir($outsideDir);
        }
    })->throws(InvalidArgumentException::class, 'Path traversal detected');
});

describe('audit:cleanup command', function () {
    it('deletes old audit log entries', function () {
        $old = ExecutionAuditLog::factory()->command()->create([
            'created_at' => now()->subDays(31),
        ]);

        $recent = ExecutionAuditLog::factory()->command()->create([
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('audit:cleanup')
            ->expectsOutputToContain('Deleted 1 audit log entries')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('execution_audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('execution_audit_logs', ['id' => $recent->id]);
    });

    it('supports custom retention days', function () {
        ExecutionAuditLog::factory()->command()->create([
            'created_at' => now()->subDays(8),
        ]);

        ExecutionAuditLog::factory()->command()->create([
            'created_at' => now()->subDays(3),
        ]);

        $this->artisan('audit:cleanup', ['--days' => 7])
            ->expectsOutputToContain('Deleted 1 audit log entries')
            ->assertExitCode(0);
    });

    it('supports dry-run mode', function () {
        $old = ExecutionAuditLog::factory()->command()->create([
            'created_at' => now()->subDays(31),
        ]);

        $this->artisan('audit:cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 1 audit log entries')
            ->assertExitCode(0);

        $this->assertDatabaseHas('execution_audit_logs', ['id' => $old->id]);
    });

    it('reports when no entries to clean', function () {
        $this->artisan('audit:cleanup')
            ->expectsOutputToContain('No audit log entries older than')
            ->assertExitCode(0);
    });
});

describe('ExecutionAuditLog model', function () {
    it('can be created via factory', function () {
        $log = ExecutionAuditLog::factory()->command('ls -la', 0)->create();

        expect($log->type)->toBe('command');
        expect($log->detail)->toBe('ls -la');
        expect($log->exit_code)->toBe(0);
    });

    it('supports file_write factory state', function () {
        $log = ExecutionAuditLog::factory()->fileWrite('output.txt')->create();

        expect($log->type)->toBe('file_write');
        expect($log->detail)->toBe('output.txt');
        expect($log->exit_code)->toBeNull();
    });
});

describe('Organization command policy columns', function () {
    it('stores and retrieves command allowlist as array', function () {
        $org = Organization::factory()->create([
            'command_allowlist' => ['php artisan *', 'npm run *'],
            'command_denylist' => ['rm -rf *'],
        ]);

        $org->refresh();

        expect($org->command_allowlist)->toBe(['php artisan *', 'npm run *']);
        expect($org->command_denylist)->toBe(['rm -rf *']);
    });

    it('defaults command policy columns to null', function () {
        $org = Organization::factory()->create();

        expect($org->command_allowlist)->toBeNull();
        expect($org->command_denylist)->toBeNull();
    });
});
