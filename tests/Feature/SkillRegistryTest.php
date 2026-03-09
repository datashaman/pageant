<?php

use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Services\SkillRegistryService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
});

describe('SkillRegistryService', function () {
    it('searches the MCP registry', function () {
        Http::fake([
            'registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'name' => 'io.github.user/test-server',
                        'description' => 'A test MCP server',
                        'repository' => ['url' => 'https://github.com/user/test-server'],
                        'version' => '1.0.0',
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
            'registry.modelcontextprotocol.io/*' => Http::response([], 500),
        ]);

        $service = new SkillRegistryService;
        $results = $service->searchMcpRegistry('test');

        expect($results)->toBeEmpty();
    });

    it('searches Smithery when API key is configured', function () {
        Http::fake([
            'api.smithery.ai/*' => Http::response([
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
            'registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'name' => 'mcp/server',
                        'description' => 'MCP server',
                        'repository' => ['url' => 'https://github.com/mcp/server'],
                        'version' => '1.0.0',
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
            'registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'name' => 'io.github.user/filesystem',
                        'description' => 'Filesystem access server',
                        'repository' => ['url' => 'https://github.com/user/filesystem'],
                        'version' => '1.0.0',
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
            'registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'name' => 'io.github.user/filesystem',
                        'description' => 'Filesystem access server',
                        'repository' => ['url' => 'https://github.com/user/filesystem'],
                        'version' => '1.0.0',
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
            'registry.modelcontextprotocol.io/*' => Http::response([
                'servers' => [
                    [
                        'name' => 'io.github.user/filesystem',
                        'description' => 'Filesystem access server',
                        'repository' => ['url' => 'https://github.com/user/filesystem'],
                        'version' => '1.0.0',
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
