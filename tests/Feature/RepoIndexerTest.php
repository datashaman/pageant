<?php

use App\Models\Repo;
use App\Models\RepoIndex;
use App\Services\RepoIndexer;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->indexer = new RepoIndexer;
    $this->tempDir = sys_get_temp_dir().'/repo-indexer-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->tempDir);
    }
});

describe('PHP parsing', function () {
    it('extracts class names and method signatures from PHP files', function () {
        $phpContent = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public function name(): string
    {
        return $this->name;
    }

    protected function isAdmin(string $role): bool
    {
        return $role === 'admin';
    }

    private static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }
}
PHP;

        file_put_contents($this->tempDir.'/User.php', $phpContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)
            ->toContain('class User extends Model')
            ->toContain('public name(): string')
            ->toContain('protected isAdmin(string $role): bool')
            ->toContain('private static findByEmail(string $email): ?self');
    });

    it('extracts interfaces and traits', function () {
        $phpContent = <<<'PHP'
<?php

namespace App\Contracts;

interface Cacheable
{
    public function cacheKey(): string;
    public function cacheTtl(): int;
}
PHP;

        file_put_contents($this->tempDir.'/Cacheable.php', $phpContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)
            ->toContain('interface Cacheable')
            ->toContain('public cacheKey(): string');
    });

    it('extracts enums', function () {
        $phpContent = <<<'PHP'
<?php

namespace App\Enums;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
PHP;

        file_put_contents($this->tempDir.'/Status.php', $phpContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)->toContain('enum Status');
    });

    it('extracts namespace', function () {
        $phpContent = <<<'PHP'
<?php

namespace App\Services;

class MyService
{
    public function execute(): void {}
}
PHP;

        file_put_contents($this->tempDir.'/MyService.php', $phpContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)->toContain('namespace App\Services');
    });

    it('extracts implements clause', function () {
        $phpContent = <<<'PHP'
<?php

class Worker implements Runnable, Serializable
{
    public function run(): void {}
}
PHP;

        file_put_contents($this->tempDir.'/Worker.php', $phpContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)->toContain('class Worker implements Runnable, Serializable');
    });

    it('strips default parameter values', function () {
        $phpContent = <<<'PHP'
<?php

class Config
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }
}
PHP;

        file_put_contents($this->tempDir.'/Config.php', $phpContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)->toContain('public get(string $key, mixed $default): mixed');
    });
});

describe('JavaScript parsing', function () {
    it('extracts class declarations', function () {
        $jsContent = <<<'JS'
export class UserManager extends BaseManager {
    constructor(db) {
        super(db);
    }
}

export function createUser(name, email) {
    return new User(name, email);
}
JS;

        file_put_contents($this->tempDir.'/manager.js', $jsContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)
            ->toContain('class UserManager extends BaseManager')
            ->toContain('fn createUser(name, email)');
    });

    it('extracts TypeScript interfaces', function () {
        $tsContent = <<<'TS'
export interface UserProps {
    name: string;
    email: string;
}

export type UserId = string | number;
TS;

        file_put_contents($this->tempDir.'/types.ts', $tsContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)
            ->toContain('type UserProps')
            ->toContain('type UserId');
    });
});

describe('Python parsing', function () {
    it('extracts class and function definitions', function () {
        $pyContent = <<<'PY'
class UserService(BaseService):
    def create_user(self, name: str, email: str) -> User:
        pass

    def delete_user(self, user_id: int) -> None:
        pass

def standalone_function(x: int, y: int) -> int:
    return x + y
PY;

        file_put_contents($this->tempDir.'/service.py', $pyContent);

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)
            ->toContain('class UserService(BaseService)')
            ->toContain('def create_user(self, name: str, email: str) -> User')
            ->toContain('def standalone_function(x: int, y: int) -> int');
    });
});

describe('file collection', function () {
    it('skips vendor and node_modules directories', function () {
        mkdir($this->tempDir.'/vendor/acme', 0755, true);
        mkdir($this->tempDir.'/node_modules/lodash', 0755, true);
        mkdir($this->tempDir.'/src', 0755, true);

        file_put_contents($this->tempDir.'/vendor/acme/Ignored.php', '<?php class Ignored {}');
        file_put_contents($this->tempDir.'/node_modules/lodash/index.js', 'export class Lodash {}');
        file_put_contents($this->tempDir.'/src/App.php', '<?php class App { public function run(): void {} }');

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)
            ->toContain('class App')
            ->not->toContain('Ignored')
            ->not->toContain('Lodash');
    });

    it('handles empty repositories gracefully', function () {
        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)->toBe('');
    });
});

describe('structural map rendering', function () {
    it('renders file paths as section headers', function () {
        mkdir($this->tempDir.'/app', 0755, true);
        file_put_contents($this->tempDir.'/app/Service.php', '<?php class Service { public function handle(): void {} }');

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect($map)
            ->toContain('# Repo Structure')
            ->toContain('## app/Service.php');
    });

    it('truncates when exceeding character budget', function () {
        mkdir($this->tempDir.'/src', 0755, true);

        for ($i = 0; $i < 100; $i++) {
            $methods = '';

            for ($j = 0; $j < 20; $j++) {
                $methods .= "    public function method{$j}ForClass{$i}(string \$arg): void {}\n";
            }

            file_put_contents(
                $this->tempDir."/src/BigClass{$i}.php",
                "<?php\nclass BigClass{$i} {\n{$methods}}"
            );
        }

        $map = $this->indexer->buildStructuralMap($this->tempDir);

        expect(strlen($map))->toBeLessThanOrEqual(4200)
            ->and($map)->toContain('truncated');
    });
});

describe('index caching', function () {
    it('creates a repo index record on first call', function () {
        Process::fake([
            'git rev-parse HEAD' => Process::result('abc123def456789012345678901234567890abcd'),
            'git ls-files *' => Process::result(''),
        ]);

        $repo = Repo::factory()->create();

        $index = $this->indexer->index($repo, $this->tempDir);

        expect($index)
            ->toBeInstanceOf(RepoIndex::class)
            ->and($index->repo_id)->toBe($repo->id)
            ->and($index->commit_hash)->toBe('abc123def456789012345678901234567890abcd');
    });

    it('returns cached index for the same commit hash', function () {
        $repo = Repo::factory()->create();

        $existingIndex = RepoIndex::factory()->create([
            'repo_id' => $repo->id,
            'commit_hash' => 'abc123def456789012345678901234567890abcd',
            'structural_map' => '# Cached Map',
        ]);

        Process::fake([
            'git rev-parse HEAD' => Process::result('abc123def456789012345678901234567890abcd'),
        ]);

        $index = $this->indexer->index($repo, $this->tempDir);

        expect($index->id)->toBe($existingIndex->id)
            ->and($index->structural_map)->toBe('# Cached Map');
    });

    it('creates a new index when commit hash changes', function () {
        $repo = Repo::factory()->create();

        RepoIndex::factory()->create([
            'repo_id' => $repo->id,
            'commit_hash' => 'old_commit_hash_padding_to_40_chars_____',
            'structural_map' => '# Old Map',
        ]);

        Process::fake([
            'git rev-parse HEAD' => Process::result('new_commit_hash_padding_to_40_chars_____'),
            'git ls-files *' => Process::result(''),
        ]);

        $index = $this->indexer->index($repo, $this->tempDir);

        expect($index->commit_hash)->toBe('new_commit_hash_padding_to_40_chars_____')
            ->and(RepoIndex::where('repo_id', $repo->id)->count())->toBe(2);
    });

    it('estimates token count correctly', function () {
        mkdir($this->tempDir.'/src', 0755, true);
        file_put_contents($this->tempDir.'/src/App.php', '<?php class App { public function run(): void {} }');

        Process::fake([
            'git rev-parse HEAD' => Process::result('abc123def456789012345678901234567890abcd'),
            'git ls-files *' => Process::result("src/App.php\n"),
        ]);

        $repo = Repo::factory()->create();
        $index = $this->indexer->index($repo, $this->tempDir);

        expect($index->token_count)->toBeGreaterThan(0);
    });
});

describe('GeneratePlan integration', function () {
    it('includes structural map in the prompt', function () {
        $job = new \App\Jobs\GeneratePlan(
            \App\Models\WorkItem::factory()->create([
                'title' => 'Test item',
                'description' => 'Test description',
                'source' => 'github',
                'source_reference' => 'acme/widgets#42',
            ]),
            'acme/widgets',
        );

        $reflection = new ReflectionMethod($job, 'buildPrompt');
        $structuralMap = "# Repo Structure\n\n## src/App.php\n- class App\n  - public run(): void";

        $prompt = $reflection->invoke($job, $structuralMap);

        expect($prompt)
            ->toContain('# Repo Structure')
            ->toContain('class App')
            ->toContain('## Work Item')
            ->toContain('Test item');
    });

    it('builds prompt without structural map when null', function () {
        $job = new \App\Jobs\GeneratePlan(
            \App\Models\WorkItem::factory()->create([
                'title' => 'Test item',
                'description' => 'Test description',
                'source' => 'github',
                'source_reference' => 'acme/widgets#42',
            ]),
            'acme/widgets',
        );

        $reflection = new ReflectionMethod($job, 'buildPrompt');
        $prompt = $reflection->invoke($job, null);

        expect($prompt)
            ->not->toContain('# Repo Structure')
            ->toContain('## Work Item')
            ->toContain('Test item');
    });
});
