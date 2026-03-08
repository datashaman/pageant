<?php

namespace App\Contracts;

interface ExecutionDriver
{
    public function exec(string $command, ?int $timeout = null): ExecutionResult;

    public function readFile(string $path, ?int $offset = null, ?int $limit = null): string;

    public function writeFile(string $path, string $content): void;

    public function editFile(string $path, string $oldString, string $newString, bool $replaceAll = false): void;

    /**
     * @return array<int, string>
     */
    public function glob(string $pattern): array;

    /**
     * @return array<int, string>
     */
    public function grep(string $pattern, ?string $path = null, array $options = []): array;

    /**
     * @return array<int, array{name: string, type: string, size: int}>
     */
    public function listDirectory(string $path = '.'): array;

    public function getBasePath(): string;

    public function cleanup(): void;
}
