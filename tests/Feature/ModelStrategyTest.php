<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
});

it('displays Default for inherit model', function () {
    $agent = Agent::factory()->for($this->organization)->create(['model' => 'inherit']);

    expect($agent->model_display_name)->toBe('Default');
});

it('displays Cheapest for cheapest model', function () {
    $agent = Agent::factory()->for($this->organization)->create(['model' => 'cheapest']);

    expect($agent->model_display_name)->toBe('Cheapest');
});

it('displays Smartest for smartest model', function () {
    $agent = Agent::factory()->for($this->organization)->create(['model' => 'smartest']);

    expect($agent->model_display_name)->toBe('Smartest');
});

it('displays actual model name for specific model', function () {
    $agent = Agent::factory()->for($this->organization)->create(['model' => 'claude-sonnet-4-6']);

    expect($agent->model_display_name)->toBe('claude-sonnet-4-6');
});

it('can create agent with cheapest strategy', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->set('name', 'cheapest-agent')
        ->set('description', 'Uses cheapest model')
        ->set('provider', 'anthropic')
        ->set('model', 'cheapest')
        ->set('permission_mode', 'full')
        ->set('max_turns', 10)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $agent = Agent::where('name', 'cheapest-agent')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->model)->toBe('cheapest');
});

it('can create agent with smartest strategy', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->set('name', 'smartest-agent')
        ->set('description', 'Uses smartest model')
        ->set('provider', 'openai')
        ->set('model', 'smartest')
        ->set('permission_mode', 'full')
        ->set('max_turns', 10)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $agent = Agent::where('name', 'smartest-agent')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->model)->toBe('smartest');
});

it('can create agent with gemini provider', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->set('name', 'gemini-agent')
        ->set('description', 'Uses Gemini')
        ->set('provider', 'gemini')
        ->set('model', 'gemini-2.5-pro')
        ->set('permission_mode', 'full')
        ->set('max_turns', 10)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $agent = Agent::where('name', 'gemini-agent')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->provider)->toBe('gemini')
        ->and($agent->model)->toBe('gemini-2.5-pro');
});

it('shows strategy options in create form', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->assertSee('Cheapest Model')
        ->assertSee('Smartest Model');
});

it('shows gemini in provider dropdown on create form', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->assertSee('Gemini');
});

it('shows Cheapest label on index page', function () {
    Agent::factory()->for($this->organization)->create(['model' => 'cheapest']);

    $this->actingAs($this->user)
        ->get(route('agents.index'))
        ->assertSee('Cheapest');
});

it('shows Smartest label on index page', function () {
    Agent::factory()->for($this->organization)->create(['model' => 'smartest']);

    $this->actingAs($this->user)
        ->get(route('agents.index'))
        ->assertSee('Smartest');
});

it('returns null model for cheapest strategy in webhook agent', function () {
    $agent = Agent::factory()->for($this->organization)->create([
        'model' => 'cheapest',
        'provider' => 'anthropic',
    ]);

    $webhookAgent = new \App\Ai\Agents\GitHubWebhookAgent($agent, 'owner/repo');

    expect($webhookAgent->model())->toBeNull();
});

it('returns null model for smartest strategy in webhook agent', function () {
    $agent = Agent::factory()->for($this->organization)->create([
        'model' => 'smartest',
        'provider' => 'anthropic',
    ]);

    $webhookAgent = new \App\Ai\Agents\GitHubWebhookAgent($agent, 'owner/repo');

    expect($webhookAgent->model())->toBeNull();
});

it('returns specific model for non-strategy values in webhook agent', function () {
    $agent = Agent::factory()->for($this->organization)->create([
        'model' => 'claude-sonnet-4-6',
        'provider' => 'anthropic',
    ]);

    $webhookAgent = new \App\Ai\Agents\GitHubWebhookAgent($agent, 'owner/repo');

    expect($webhookAgent->model())->toBe('claude-sonnet-4-6');
});
