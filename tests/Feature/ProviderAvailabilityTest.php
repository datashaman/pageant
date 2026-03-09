<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserApiKey;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
});

it('disables providers without server key or user BYOK key on create form', function () {
    config(['ai.providers.gemini.key' => null]);
    config(['ai.providers.openai.key' => null]);
    config(['ai.providers.anthropic.key' => 'test-key']);

    $component = Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create');

    $providers = $component->get('availableProviders');

    expect($providers['anthropic'])->toBeTrue()
        ->and($providers['openai'])->toBeFalse()
        ->and($providers['gemini'])->toBeFalse();
});

it('enables provider when user has a valid BYOK key', function () {
    config(['ai.providers.gemini.key' => null]);

    UserApiKey::factory()->valid()->create([
        'user_id' => $this->user->id,
        'provider' => 'gemini',
    ]);

    $component = Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create');

    $providers = $component->get('availableProviders');

    expect($providers['gemini'])->toBeTrue();
});

it('disables provider when user BYOK key is invalid', function () {
    config(['ai.providers.gemini.key' => null]);

    UserApiKey::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'gemini',
        'is_valid' => false,
    ]);

    $component = Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create');

    $providers = $component->get('availableProviders');

    expect($providers['gemini'])->toBeFalse();
});

it('disables providers without keys on edit form', function () {
    config(['ai.providers.gemini.key' => null]);
    config(['ai.providers.openai.key' => null]);
    config(['ai.providers.anthropic.key' => 'test-key']);

    $agent = Agent::factory()->for($this->organization)->create();

    $component = Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.edit', ['agent' => $agent]);

    $providers = $component->get('availableProviders');

    expect($providers['anthropic'])->toBeTrue()
        ->and($providers['openai'])->toBeFalse()
        ->and($providers['gemini'])->toBeFalse();
});

it('disables providers without keys on chat panel', function () {
    config(['ai.providers.gemini.key' => null]);
    config(['ai.providers.openai.key' => null]);
    config(['ai.providers.anthropic.key' => 'test-key']);

    $component = Livewire\Livewire::actingAs($this->user)
        ->test('chat-panel');

    $providers = $component->get('availableProviders');

    expect($providers['anthropic'])->toBeTrue()
        ->and($providers['openai'])->toBeFalse()
        ->and($providers['gemini'])->toBeFalse();
});
