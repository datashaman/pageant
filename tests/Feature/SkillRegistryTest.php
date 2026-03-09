<?php

use App\Ai\ToolRegistry;
use App\Ai\Tools\ImportRegistrySkillTool;
use App\Ai\Tools\SearchRegistrySkillsTool;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Services\SkillRegistryService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
});

describe('SkillRegistryService', function () {
    it('searches the MCP registry', function () {
        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'server' => [
                            'name' => 'io.github.user/test-server',
                            'description' => 'A test MCP server',
                            'repository' => ['url' => 'https://github.com/user/test-server'],
                            'version' => '1.0.0',
                        ],
                    ],
                ],
                'metadata' => ['count' => 1],
            ]),
        ]);

        $service = new SkillRegistryService;
        $results = $service->searchMcpRegistry('test');

        expect($results)->toHaveCount(1)
            ->and($results->first()['registry'])->toBe('mcp-registry')
            ->and($results->first()['name'])->toBe('io.github.user/test-server')
            ->and($results->first()['description'])->toBe('A test MCP server')
            ->and($results->first()['repository_url'])->toBe('https://github.com/user/test-server');
    });

    it('returns empty collection on MCP registry failure', function () {
        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([], 500),
        ]);

        $service = new SkillRegistryService;
        $results = $service->searchMcpRegistry('test');

        expect($results)->toBeEmpty();
    });

    it('searches Smithery when API key is configured', function () {
        Http::fake([
            'https://api.smithery.ai/*' => Http::response([
                'servers' => [
                    [
                        'qualifiedName' => 'test/server',
                        'displayName' => 'Test Server',
                        'description' => 'A test Smithery server',
                        'homepage' => 'https://example.com',
                    ],
                ],
                'pagination' => ['totalCount' => 1],
            ]),
        ]);

        $service = new SkillRegistryService;
        $results = $service->searchSmithery('test', 20, 'fake-api-key');

        expect($results)->toHaveCount(1)
            ->and($results->first()['registry'])->toBe('smithery')
            ->and($results->first()['name'])->toBe('Test Server')
            ->and($results->first()['source_reference'])->toBe('test/server');
    });

    it('returns empty collection when Smithery has no API key', function () {
        $service = new SkillRegistryService;
        $results = $service->searchSmithery('test');

        expect($results)->toBeEmpty();
    });

    it('combines results from all registries', function () {
        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'server' => [
                            'name' => 'mcp/server',
                            'description' => 'MCP server',
                            'repository' => ['url' => 'https://github.com/mcp/server'],
                            'version' => '1.0.0',
                        ],
                    ],
                ],
                'metadata' => ['count' => 1],
            ]),
        ]);

        $service = new SkillRegistryService;
        $results = $service->search('test');

        expect($results)->toHaveCount(1)
            ->and($results->first()['registry'])->toBe('mcp-registry');
    });

    it('defaults to a page size of 10', function () {
        $servers = [];
        for ($i = 0; $i < 15; $i++) {
            $servers[] = [
                'server' => [
                    'name' => "mcp/server-{$i}",
                    'description' => "Server {$i}",
                    'repository' => ['url' => "https://github.com/mcp/server-{$i}"],
                    'version' => '1.0.0',
                ],
            ];
        }

        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => $servers,
                'metadata' => ['count' => 15],
            ]),
        ]);

        $service = new SkillRegistryService;
        $results = $service->search('test');

        expect($results)->toHaveCount(10);
    });

    it('sorts results alphabetically by name', function () {
        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'server' => [
                            'name' => 'mcp/zebra',
                            'description' => 'Zebra server',
                            'repository' => ['url' => 'https://github.com/mcp/zebra'],
                            'version' => '1.0.0',
                        ],
                    ],
                    [
                        'server' => [
                            'name' => 'mcp/alpha',
                            'description' => 'Alpha server',
                            'repository' => ['url' => 'https://github.com/mcp/alpha'],
                            'version' => '1.0.0',
                        ],
                    ],
                    [
                        'server' => [
                            'name' => 'mcp/middle',
                            'description' => 'Middle server',
                            'repository' => ['url' => 'https://github.com/mcp/middle'],
                            'version' => '1.0.0',
                        ],
                    ],
                ],
                'metadata' => ['count' => 3],
            ]),
        ]);

        $service = new SkillRegistryService;
        $results = $service->search('test');

        expect($results[0]['name'])->toBe('mcp/alpha')
            ->and($results[1]['name'])->toBe('mcp/middle')
            ->and($results[2]['name'])->toBe('mcp/zebra');
    });

    it('sorts results case-insensitively', function () {
        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'server' => [
                            'name' => 'Zebra-Server',
                            'description' => 'Zebra server',
                            'repository' => ['url' => 'https://github.com/mcp/zebra'],
                            'version' => '1.0.0',
                        ],
                    ],
                    [
                        'server' => [
                            'name' => 'alpha-server',
                            'description' => 'Alpha server',
                            'repository' => ['url' => 'https://github.com/mcp/alpha'],
                            'version' => '1.0.0',
                        ],
                    ],
                    [
                        'server' => [
                            'name' => 'Beta-Server',
                            'description' => 'Beta server',
                            'repository' => ['url' => 'https://github.com/mcp/beta'],
                            'version' => '1.0.0',
                        ],
                    ],
                ],
                'metadata' => ['count' => 3],
            ]),
        ]);

        $service = new SkillRegistryService;
        $results = $service->search('test');

        expect($results[0]['name'])->toBe('alpha-server')
            ->and($results[1]['name'])->toBe('Beta-Server')
            ->and($results[2]['name'])->toBe('Zebra-Server');
    });
});

describe('Skills Registry UI', function () {
    it('shows the registry browse page', function () {
        $this->actingAs($this->user)
            ->get(route('skills.registry'))
            ->assertOk()
            ->assertSee('Browse Skill Registry');
    });

    it('shows browse registry button on skills index', function () {
        $this->actingAs($this->user)
            ->get(route('skills.index'))
            ->assertOk()
            ->assertSee('Browse Registry');
    });

    it('can search the registry', function () {
        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'server' => [
                            'name' => 'io.github.user/filesystem',
                            'description' => 'Filesystem access server',
                            'repository' => ['url' => 'https://github.com/user/filesystem'],
                            'version' => '1.0.0',
                        ],
                    ],
                ],
                'metadata' => ['count' => 1],
            ]),
        ]);

        Livewire\Livewire::actingAs($this->user)
            ->test('pages::skills.registry')
            ->set('search', 'filesystem')
            ->call('searchRegistry')
            ->assertHasNoErrors()
            ->assertSee('io.github.user/filesystem');
    });

    it('can import a skill from registry results', function () {
        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'server' => [
                            'name' => 'io.github.user/filesystem',
                            'description' => 'Filesystem access server',
                            'repository' => ['url' => 'https://github.com/user/filesystem'],
                            'version' => '1.0.0',
                        ],
                    ],
                ],
                'metadata' => ['count' => 1],
            ]),
        ]);

        Livewire\Livewire::actingAs($this->user)
            ->test('pages::skills.registry')
            ->set('search', 'filesystem')
            ->call('searchRegistry')
            ->call('importSkill', 0)
            ->assertSee('imported successfully');

        $skill = Skill::where('name', 'filesystem')->first();
        expect($skill)->not->toBeNull()
            ->and($skill->organization_id)->toBe($this->organization->id)
            ->and($skill->source)->toBe('mcp-registry')
            ->and($skill->source_reference)->toBe('io.github.user/filesystem');
    });

    it('prevents importing duplicate skill names', function () {
        Skill::factory()->for($this->organization)->create(['name' => 'filesystem']);

        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'server' => [
                            'name' => 'io.github.user/filesystem',
                            'description' => 'Filesystem access server',
                            'repository' => ['url' => 'https://github.com/user/filesystem'],
                            'version' => '1.0.0',
                        ],
                    ],
                ],
                'metadata' => ['count' => 1],
            ]),
        ]);

        Livewire\Livewire::actingAs($this->user)
            ->test('pages::skills.registry')
            ->set('search', 'filesystem')
            ->call('searchRegistry')
            ->call('importSkill', 0)
            ->assertSee('already exists');
    });

    it('validates search term is required', function () {
        Livewire\Livewire::actingAs($this->user)
            ->test('pages::skills.registry')
            ->set('search', '')
            ->call('searchRegistry')
            ->assertHasErrors(['search']);
    });

    it('requires authentication for registry page', function () {
        $this->get(route('skills.registry'))
            ->assertRedirect(route('login'));
    });
});

describe('AI Tools', function () {
    it('SearchRegistrySkillsTool searches public registries', function () {
        Http::fake([
            'https://registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'server' => [
                            'name' => 'io.github.user/slack',
                            'description' => 'Slack integration',
                            'repository' => ['url' => 'https://github.com/user/slack'],
                            'version' => '1.0.0',
                        ],
                    ],
                ],
                'metadata' => ['count' => 1],
            ]),
        ]);

        $tool = new SearchRegistrySkillsTool($this->user);
        $result = json_decode($tool->handle(new Request([
            'query' => 'slack',
            'registry' => 'mcp-registry',
        ])), true);

        expect($result['count'])->toBe(1)
            ->and($result['results'][0]['name'])->toBe('io.github.user/slack')
            ->and($result['results'][0]['registry'])->toBe('mcp-registry');
    });

    it('ImportRegistrySkillTool imports a skill', function () {
        $tool = new ImportRegistrySkillTool($this->user);
        $result = json_decode($tool->handle(new Request([
            'name' => 'slack-bot',
            'description' => 'Slack bot integration',
            'registry' => 'mcp-registry',
            'source_reference' => 'io.github.user/slack',
            'source_url' => 'https://github.com/user/slack',
        ])), true);

        expect($result['message'])->toContain('imported successfully')
            ->and($result['skill']['name'])->toBe('slack-bot')
            ->and($result['skill']['source'])->toBe('mcp-registry');

        $this->assertDatabaseHas('skills', [
            'name' => 'slack-bot',
            'organization_id' => $this->organization->id,
        ]);
    });

    it('ImportRegistrySkillTool prevents duplicate names', function () {
        Skill::factory()->for($this->organization)->create(['name' => 'slack-bot']);

        $tool = new ImportRegistrySkillTool($this->user);
        $result = json_decode($tool->handle(new Request([
            'name' => 'slack-bot',
            'registry' => 'mcp-registry',
            'source_reference' => 'io.github.user/slack',
        ])), true);

        expect($result['error'])->toContain('already exists');
    });
});

describe('ToolRegistry', function () {
    it('includes search_registry_skills in available tools', function () {
        $available = ToolRegistry::available();

        expect($available)->toHaveKey('search_registry_skills')
            ->and($available)->toHaveKey('import_registry_skill');
    });

    it('includes registry tools in pageant tool names', function () {
        $pageantTools = ToolRegistry::pageantToolNames();

        expect($pageantTools)->toContain('search_registry_skills')
            ->and($pageantTools)->toContain('import_registry_skill');
    });
});
