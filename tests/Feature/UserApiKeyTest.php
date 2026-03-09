<?php

use App\Models\User;
use App\Models\UserApiKey;
use App\Services\ApiKeyValidator;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can create a user api key', function () {
    $key = UserApiKey::factory()->for($this->user)->create([
        'provider' => 'anthropic',
    ]);

    expect($key)->not->toBeNull()
        ->and($key->user_id)->toBe($this->user->id)
        ->and($key->provider)->toBe('anthropic');
});

it('encrypts the api key', function () {
    $key = UserApiKey::factory()->for($this->user)->create([
        'provider' => 'openai',
        'api_key' => 'sk-test-secret-key',
    ]);

    $raw = \DB::table('user_api_keys')->where('id', $key->id)->value('api_key');

    expect($raw)->not->toBe('sk-test-secret-key')
        ->and($key->fresh()->api_key)->toBe('sk-test-secret-key');
});

it('enforces unique constraint on user and provider', function () {
    UserApiKey::factory()->for($this->user)->create(['provider' => 'anthropic']);

    UserApiKey::factory()->for($this->user)->create(['provider' => 'anthropic']);
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

it('allows same provider for different users', function () {
    $otherUser = User::factory()->create();

    $key1 = UserApiKey::factory()->for($this->user)->create(['provider' => 'anthropic']);
    $key2 = UserApiKey::factory()->for($otherUser)->create(['provider' => 'anthropic']);

    expect($key1)->not->toBeNull()
        ->and($key2)->not->toBeNull();
});

it('scopes to valid keys', function () {
    UserApiKey::factory()->for($this->user)->create(['provider' => 'anthropic', 'is_valid' => false]);
    UserApiKey::factory()->for($this->user)->create(['provider' => 'openai', 'is_valid' => true, 'validated_at' => now()]);

    $validKeys = UserApiKey::query()->valid()->get();

    expect($validKeys)->toHaveCount(1)
        ->and($validKeys->first()->provider)->toBe('openai');
});

it('has user relationship', function () {
    $key = UserApiKey::factory()->for($this->user)->create();

    expect($key->user->id)->toBe($this->user->id);
});

it('user has apiKeys relationship', function () {
    UserApiKey::factory()->for($this->user)->create(['provider' => 'anthropic']);
    UserApiKey::factory()->for($this->user)->create(['provider' => 'openai']);

    expect($this->user->apiKeys)->toHaveCount(2);
});

it('can save key from settings page', function () {
    $this->mock(ApiKeyValidator::class, function ($mock) {
        $mock->shouldReceive('validate')
            ->with('anthropic', 'sk-ant-test-key')
            ->once()
            ->andReturn(true);
    });

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::settings.api-keys')
        ->set('anthropicKey', 'sk-ant-test-key')
        ->call('saveKey', 'anthropic');

    $key = $this->user->apiKeys()->where('provider', 'anthropic')->first();
    expect($key)->not->toBeNull()
        ->and($key->is_valid)->toBeTrue()
        ->and($key->validated_at)->not->toBeNull();
});

it('can delete key from settings page', function () {
    UserApiKey::factory()->for($this->user)->create(['provider' => 'openai']);

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::settings.api-keys')
        ->call('deleteKey', 'openai');

    expect($this->user->apiKeys()->where('provider', 'openai')->exists())->toBeFalse();
});

it('marks invalid key correctly', function () {
    $this->mock(ApiKeyValidator::class, function ($mock) {
        $mock->shouldReceive('validate')
            ->with('openai', 'bad-key')
            ->once()
            ->andReturn(false);
    });

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::settings.api-keys')
        ->set('openaiKey', 'bad-key')
        ->call('saveKey', 'openai');

    $key = $this->user->apiKeys()->where('provider', 'openai')->first();
    expect($key)->not->toBeNull()
        ->and($key->is_valid)->toBeFalse()
        ->and($key->validated_at)->toBeNull();
});

it('renders the api keys settings page', function () {
    $this->actingAs($this->user)
        ->get(route('api-keys.edit'))
        ->assertOk()
        ->assertSee('API Keys');
});
