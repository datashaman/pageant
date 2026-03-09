<?php

namespace App\Services;

use App\Models\Repo;
use App\Models\RepoIndex;
use Illuminate\Support\Facades\Process;

class RepoIndexer
{
    /**
     * Target token budget for the structural map (~1K tokens).
     * Roughly 4 chars per token.
     */
    protected const TARGET_CHARS = 4000;

    /**
     * File extensions to index with their parser type.
     *
     * @var array<string, string>
     */
    protected const PARSABLE_EXTENSIONS = [
        'php' => 'php',
        'js' => 'javascript',
        'ts' => 'javascript',
        'jsx' => 'javascript',
        'tsx' => 'javascript',
        'py' => 'python',
    ];

    /**
     * Directories to always skip when walking the file tree.
     *
     * @var array<int, string>
     */
    protected const SKIP_DIRECTORIES = [
        'vendor',
        'node_modules',
        '.git',
        'storage',
        'bootstrap/cache',
        '.idea',
        '.vscode',
        'dist',
        'build',
        'public/build',
    ];

    /**
     * Generate or retrieve a cached structural map for a repo at a given path.
     */
    public function index(Repo $repo, string $repoPath): RepoIndex
    {
        $commitHash = $this->resolveCommitHash($repoPath);

        $existing = RepoIndex::query()
            ->where('repo_id', $repo->id)
            ->where('commit_hash', $commitHash)
            ->first();

        if ($existing) {
            return $existing;
        }

        $structuralMap = $this->buildStructuralMap($repoPath);

        return RepoIndex::create([
            'repo_id' => $repo->id,
            'commit_hash' => $commitHash,
            'structural_map' => $structuralMap,
            'token_count' => $this->estimateTokenCount($structuralMap),
        ]);
    }

    /**
     * Build a structural map from the repository files.
     */
    public function buildStructuralMap(string $repoPath): string
    {
        $files = $this->collectFiles($repoPath);
        $sections = [];

        foreach ($files as $relativePath) {
            $fullPath = $repoPath.'/'.ltrim($relativePath, '/');

            if (! is_file($fullPath) || ! is_readable($fullPath)) {
                continue;
            }

            $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
            $parserType = self::PARSABLE_EXTENSIONS[$extension] ?? null;

            if (! $parserType) {
                continue;
            }

            $content = file_get_contents($fullPath);

            if ($content === false || $content === '') {
                continue;
            }

            $structures = match ($parserType) {
                'php' => $this->parsePhp($content),
                'javascript' => $this->parseJavaScript($content),
                'python' => $this->parsePython($content),
                default => [],
            };

            if (! empty($structures)) {
                $sections[$relativePath] = $structures;
            }
        }

        return $this->renderMap($sections);
    }

    /**
     * Resolve the current HEAD commit hash for a repo path.
     */
    protected function resolveCommitHash(string $repoPath): string
    {
        $result = Process::path($repoPath)
            ->run('git rev-parse HEAD');

        if (! $result->successful()) {
            return hash('sha1', (string) time());
        }

        return trim($result->output());
    }

    /**
     * Collect indexable files from the repo, respecting .gitignore via git ls-files.
     *
     * @return array<int, string>
     */
    protected function collectFiles(string $repoPath): array
    {
        $extensions = array_keys(self::PARSABLE_EXTENSIONS);
        $patterns = array_map(fn (string $ext) => "'*.{$ext}'", $extensions);

        $result = Process::path($repoPath)
            ->run('git ls-files -- '.implode(' ', $patterns));

        if (! $result->successful()) {
            return $this->collectFilesManually($repoPath);
        }

        $files = array_filter(explode("\n", trim($result->output())));

        return array_values(array_filter($files, function (string $file) {
            foreach (self::SKIP_DIRECTORIES as $skip) {
                if (str_starts_with($file, $skip.'/')) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Fallback file collection when git ls-files is unavailable.
     *
     * @return array<int, string>
     */
    protected function collectFilesManually(string $repoPath): array
    {
        $files = [];
        $extensions = array_keys(self::PARSABLE_EXTENSIONS);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($repoPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($repoPath) + 1);

            foreach (self::SKIP_DIRECTORIES as $skip) {
                if (str_starts_with($relativePath, $skip.'/')) {
                    continue 2;
                }
            }

            $ext = $file->getExtension();

            if (in_array($ext, $extensions, true)) {
                $files[] = $relativePath;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Parse PHP source code using token_get_all to extract structural information.
     *
     * @return array<int, string>
     */
    protected function parsePhp(string $content): array
    {
        $structures = [];

        try {
            $tokens = token_get_all($content);
        } catch (\Throwable) {
            return [];
        }

        $tokenCount = count($tokens);
        $namespace = '';
        $currentClass = null;
        $depth = 0;
        $classDepth = 0;

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                    if ($currentClass !== null && $depth < $classDepth) {
                        $currentClass = null;
                    }
                }

                continue;
            }

            [$tokenId, $tokenValue] = $token;

            if ($tokenId === T_NAMESPACE) {
                $namespace = $this->collectNamespace($tokens, $i, $tokenCount);
            } elseif ($tokenId === T_CLASS || $tokenId === T_INTERFACE || $tokenId === T_TRAIT || $tokenId === T_ENUM) {
                $className = $this->collectIdentifier($tokens, $i, $tokenCount);

                if ($className === null) {
                    continue;
                }

                $typeLabel = match ($tokenId) {
                    T_CLASS => 'class',
                    T_INTERFACE => 'interface',
                    T_TRAIT => 'trait',
                    T_ENUM => 'enum',
                    default => 'class',
                };

                $extends = $this->collectExtends($tokens, $i, $tokenCount);
                $implements = $this->collectImplements($tokens, $i, $tokenCount);

                $line = "{$typeLabel} {$className}";

                if ($extends) {
                    $line .= " extends {$extends}";
                }

                if ($implements) {
                    $line .= " implements {$implements}";
                }

                $structures[] = $line;
                $currentClass = $className;
                $classDepth = $depth + 1;
            } elseif ($tokenId === T_FUNCTION && $currentClass !== null) {
                $signature = $this->collectMethodSignature($tokens, $i, $tokenCount);

                if ($signature !== null) {
                    $structures[] = "  {$signature}";
                }
            } elseif ($tokenId === T_FUNCTION && $currentClass === null && $depth === 0) {
                $signature = $this->collectMethodSignature($tokens, $i, $tokenCount);

                if ($signature !== null) {
                    $structures[] = "fn {$signature}";
                }
            }
        }

        if ($namespace !== '') {
            array_unshift($structures, "namespace {$namespace}");
        }

        return $structures;
    }

    /**
     * Collect a namespace from PHP tokens starting at the given position.
     */
    protected function collectNamespace(array $tokens, int &$i, int $count): string
    {
        $namespace = '';
        $i++;

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }
            } elseif (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                $namespace .= $token[1];
            }

            $i++;
        }

        return trim($namespace);
    }

    /**
     * Collect a class/interface/trait name identifier from tokens.
     */
    protected function collectIdentifier(array $tokens, int &$i, int $count): ?string
    {
        $i++;

        while ($i < $count) {
            $token = $tokens[$i];

            if (! is_string($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            if (! is_string($token) && $token[0] === T_WHITESPACE) {
                $i++;

                continue;
            }

            break;
        }

        return null;
    }

    /**
     * Collect the "extends" clause from a class declaration.
     */
    protected function collectExtends(array $tokens, int $i, int $count): ?string
    {
        while ($i < $count) {
            $token = $tokens[$i];

            if (is_string($token) && ($token === '{' || $token === ';')) {
                break;
            }

            if (! is_string($token) && $token[0] === T_EXTENDS) {
                return $this->collectTypeList($tokens, $i, $count, true);
            }

            $i++;
        }

        return null;
    }

    /**
     * Collect the "implements" clause from a class declaration.
     */
    protected function collectImplements(array $tokens, int $i, int $count): ?string
    {
        while ($i < $count) {
            $token = $tokens[$i];

            if (is_string($token) && ($token === '{' || $token === ';')) {
                break;
            }

            if (! is_string($token) && $token[0] === T_IMPLEMENTS) {
                return $this->collectTypeList($tokens, $i, $count);
            }

            $i++;
        }

        return null;
    }

    /**
     * Collect a comma-separated list of type names.
     */
    protected function collectTypeList(array $tokens, int &$i, int $count, bool $singleOnly = false): string
    {
        $names = [];
        $current = '';
        $i++;

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '{' || $token === ';') {
                    break;
                }

                if ($token === ',' && ! $singleOnly) {
                    if (trim($current) !== '') {
                        $names[] = trim($current);
                    }
                    $current = '';
                    $i++;

                    continue;
                }
            }

            if (! is_string($token)) {
                if ($token[0] === T_IMPLEMENTS && $singleOnly) {
                    break;
                }

                if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                    $current .= $token[1];
                }
            }

            $i++;
        }

        if (trim($current) !== '') {
            $names[] = trim($current);
        }

        return implode(', ', $names);
    }

    /**
     * Collect a method/function signature from tokens.
     */
    protected function collectMethodSignature(array $tokens, int &$i, int $count): ?string
    {
        $visibility = $this->lookBackForVisibility($tokens, $i);
        $static = $this->lookBackForStatic($tokens, $i);
        $name = null;
        $params = '';
        $returnType = '';
        $j = $i + 1;

        while ($j < $count) {
            $token = $tokens[$j];

            if (! is_string($token) && $token[0] === T_STRING) {
                $name = $token[1];

                break;
            }

            if (! is_string($token) && $token[0] === T_WHITESPACE) {
                $j++;

                continue;
            }

            break;
        }

        if ($name === null) {
            return null;
        }

        $j++;

        while ($j < $count) {
            $token = $tokens[$j];

            if (is_string($token) && $token === '(') {
                $params = $this->collectParenthesized($tokens, $j, $count);

                break;
            }

            $j++;
        }

        $j++;
        $returnType = $this->collectReturnType($tokens, $j, $count);

        $parts = [];

        if ($visibility) {
            $parts[] = $visibility;
        }

        if ($static) {
            $parts[] = 'static';
        }

        $signature = "{$name}({$params})";

        if ($returnType) {
            $signature .= ": {$returnType}";
        }

        $parts[] = $signature;

        return implode(' ', $parts);
    }

    /**
     * Look backwards from the current position for a visibility keyword.
     */
    protected function lookBackForVisibility(array $tokens, int $i): ?string
    {
        for ($j = $i - 1; $j >= max(0, $i - 4); $j--) {
            $token = $tokens[$j];

            if (is_string($token)) {
                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_STATIC, T_ABSTRACT, T_READONLY], true)) {
                continue;
            }

            if (in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                return $token[1];
            }

            break;
        }

        return null;
    }

    /**
     * Look backwards from the current position for the "static" keyword.
     */
    protected function lookBackForStatic(array $tokens, int $i): bool
    {
        for ($j = $i - 1; $j >= max(0, $i - 6); $j--) {
            $token = $tokens[$j];

            if (is_string($token)) {
                continue;
            }

            if ($token[0] === T_WHITESPACE) {
                continue;
            }

            if ($token[0] === T_STATIC) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect content inside parentheses, simplifying parameter signatures.
     */
    protected function collectParenthesized(array $tokens, int &$i, int $count): string
    {
        $depth = 0;
        $parts = [];
        $currentParam = '';

        for ($j = $i; $j < $count; $j++) {
            $token = $tokens[$j];
            $text = is_string($token) ? $token : $token[1];

            if ($text === '(') {
                $depth++;

                if ($depth === 1) {
                    continue;
                }
            }

            if ($text === ')') {
                $depth--;

                if ($depth === 0) {
                    $i = $j;

                    if (trim($currentParam) !== '') {
                        $parts[] = $this->simplifyParam(trim($currentParam));
                    }

                    break;
                }
            }

            if ($text === ',' && $depth === 1) {
                if (trim($currentParam) !== '') {
                    $parts[] = $this->simplifyParam(trim($currentParam));
                }
                $currentParam = '';

                continue;
            }

            if ($depth === 1) {
                $currentParam .= $text;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Simplify a parameter string by removing default values.
     */
    protected function simplifyParam(string $param): string
    {
        $param = preg_replace('/\s*=\s*.*$/', '', $param);
        $param = preg_replace('/\s+/', ' ', $param);

        return trim($param);
    }

    /**
     * Collect a return type declaration from tokens.
     */
    protected function collectReturnType(array $tokens, int &$i, int $count): string
    {
        $foundColon = false;
        $returnType = '';

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === ':' && ! $foundColon) {
                    $foundColon = true;
                    $i++;

                    continue;
                }

                if ($token === '{' || $token === ';') {
                    break;
                }

                if ($foundColon && $token === '?') {
                    $returnType .= '?';
                }

                if ($foundColon && $token === '|') {
                    $returnType .= '|';
                }

                if ($foundColon && $token === '&') {
                    $returnType .= '&';
                }
            } elseif (! is_string($token)) {
                if ($token[0] === T_WHITESPACE) {
                    $i++;

                    continue;
                }

                $returnTypeTokens = [
                    T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED,
                    T_ARRAY, T_CALLABLE, T_STATIC,
                ];

                if (defined('T_NULL')) {
                    $returnTypeTokens[] = T_NULL;
                }

                if (defined('T_TRUE')) {
                    $returnTypeTokens[] = T_TRUE;
                }

                if (defined('T_FALSE')) {
                    $returnTypeTokens[] = T_FALSE;
                }

                if ($foundColon && in_array($token[0], $returnTypeTokens, true)) {
                    $returnType .= $token[1];
                }
            }

            $i++;
        }

        return trim($returnType);
    }

    /**
     * Parse JavaScript/TypeScript source code using regex for structural extraction.
     *
     * @return array<int, string>
     */
    protected function parseJavaScript(string $content): array
    {
        $structures = [];

        if (preg_match_all('/^(?:export\s+)?(?:default\s+)?class\s+(\w+)(?:\s+extends\s+([\w.]+))?/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $line = "class {$match[1]}";

                if (! empty($match[2])) {
                    $line .= " extends {$match[2]}";
                }

                $structures[] = $line;
            }
        }

        if (preg_match_all('/(?:export\s+)?(?:async\s+)?function\s+(\w+)\s*\(([^)]*)\)/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params = $this->simplifyJsParams($match[2]);
                $structures[] = "fn {$match[1]}({$params})";
            }
        }

        if (preg_match_all('/(?:export\s+)?(?:const|let|var)\s+(\w+)\s*=\s*(?:async\s+)?\([^)]*\)\s*=>/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $structures[] = "fn {$match[1]}()";
            }
        }

        if (preg_match_all('/(?:export\s+)?(?:interface|type)\s+(\w+)/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $structures[] = "type {$match[1]}";
            }
        }

        return $structures;
    }

    /**
     * Simplify JavaScript parameter strings.
     */
    protected function simplifyJsParams(string $params): string
    {
        $params = preg_replace('/\s*=\s*[^,)]+/', '', $params);
        $params = preg_replace('/\s+/', ' ', $params);

        return trim($params);
    }

    /**
     * Parse Python source code using regex for structural extraction.
     *
     * @return array<int, string>
     */
    protected function parsePython(string $content): array
    {
        $structures = [];

        if (preg_match_all('/^class\s+(\w+)(?:\(([^)]*)\))?:/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $line = "class {$match[1]}";

                if (! empty($match[2])) {
                    $line .= "({$match[2]})";
                }

                $structures[] = $line;
            }
        }

        if (preg_match_all('/^(\s*)def\s+(\w+)\s*\(([^)]*)\)(?:\s*->\s*(\S+))?:/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $indent = strlen($match[1]) > 0 ? '  ' : '';
                $name = $match[2];
                $params = $this->simplifyPyParams($match[3]);
                $returnType = ! empty($match[4]) ? " -> {$match[4]}" : '';
                $structures[] = "{$indent}def {$name}({$params}){$returnType}";
            }
        }

        return $structures;
    }

    /**
     * Simplify Python parameter strings.
     */
    protected function simplifyPyParams(string $params): string
    {
        $params = preg_replace('/\s*=\s*[^,)]+/', '', $params);
        $params = preg_replace('/\s+/', ' ', $params);

        return trim($params);
    }

    /**
     * Render the collected structural sections into a compressed text map.
     *
     * @param  array<string, array<int, string>>  $sections
     */
    protected function renderMap(array $sections): string
    {
        if (empty($sections)) {
            return '';
        }

        $lines = ['# Repo Structure'];
        $totalChars = strlen($lines[0]);

        foreach ($sections as $path => $structures) {
            $header = "\n## {$path}";
            $sectionLines = array_map(fn (string $s) => "- {$s}", $structures);
            $sectionText = $header."\n".implode("\n", $sectionLines);

            if ($totalChars + strlen($sectionText) > self::TARGET_CHARS) {
                $lines[] = "\n## ... (truncated)";

                break;
            }

            $lines[] = $sectionText;
            $totalChars += strlen($sectionText);
        }

        return implode("\n", $lines);
    }

    /**
     * Estimate the token count for a given text (~4 chars per token).
     */
    protected function estimateTokenCount(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}
