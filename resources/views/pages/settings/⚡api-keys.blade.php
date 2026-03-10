<?php

use App\Models\UserApiKey;
use App\Services\ApiKeyValidator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API Keys')] class extends Component {
    public string $anthropicKey = '';
    public string $openaiKey = '';
    public string $geminiKey = '';

    /** @var array<string, string> */
    public array $statuses = [];

    public function mount(): void
    {
        $this->loadStatuses();
    }

    protected function loadStatuses(): void
    {
        $keys = Auth::user()->apiKeys()->get()->keyBy('provider');

        foreach (['anthropic', 'openai', 'gemini'] as $provider) {
            if ($key = $keys->get($provider)) {
                $this->statuses[$provider] = $key->is_valid ? 'valid' : 'invalid';
            }
        }
    }

    #[Computed]
    public function storedKeys(): array
    {
        return Auth::user()->apiKeys()
            ->get()
            ->keyBy('provider')
            ->map(fn (UserApiKey $key) => [
                'masked' => str_repeat('*', 20).substr($key->api_key, -4),
                'is_valid' => $key->is_valid,
                'validated_at' => $key->validated_at?->diffForHumans(),
            ])
            ->all();
    }

    public function saveKey(string $provider): void
    {
        $property = match ($provider) {
            'anthropic' => 'anthropicKey',
            'openai' => 'openaiKey',
            'gemini' => 'geminiKey',
            default => null,
        };

        if (! $property || empty($this->$property)) {
            return;
        }

        $apiKey = trim($this->$property);
        $validator = app(ApiKeyValidator::class);
        $isValid = $validator->validate($provider, $apiKey);

        Auth::user()->apiKeys()->updateOrCreate(
            ['provider' => $provider],
            [
                'api_key' => $apiKey,
                'is_valid' => $isValid,
                'validated_at' => $isValid ? now() : null,
            ],
        );

        $this->$property = '';
        $this->statuses[$provider] = $isValid ? 'valid' : 'invalid';
        unset($this->storedKeys);

        $this->dispatch('api-key-saved');
    }

    public function deleteKey(string $provider): void
    {
        Auth::user()->apiKeys()->where('provider', $provider)->delete();

        unset($this->statuses[$provider]);
        unset($this->storedKeys);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('API Keys')" :subheading="__('Manage your AI provider API keys')">
        <div class="my-6 space-y-8">
            @foreach ([
                'anthropic' => ['label' => 'Anthropic', 'placeholder' => 'sk-ant-...', 'property' => 'anthropicKey'],
                'openai' => ['label' => 'OpenAI', 'placeholder' => 'sk-...', 'property' => 'openaiKey'],
                'gemini' => ['label' => 'Gemini', 'placeholder' => 'AI...', 'property' => 'geminiKey'],
            ] as $provider => $config)
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <flux:heading size="sm">{{ $config['label'] }}</flux:heading>
                        @if (isset($this->storedKeys[$provider]))
                            @if ($this->statuses[$provider] === 'valid')
                                <flux:badge size="sm" color="green">{{ __('Configured') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">{{ __('Invalid') }}</flux:badge>
                            @endif
                        @else
                            <flux:badge size="sm" variant="outline">{{ __('Not configured') }}</flux:badge>
                        @endif
                    </div>

                    @if (isset($this->storedKeys[$provider]))
                        <div class="flex items-center gap-3">
                            <flux:text class="font-mono text-sm">{{ $this->storedKeys[$provider]['masked'] }}</flux:text>
                            @if ($this->storedKeys[$provider]['validated_at'])
                                <flux:text class="text-xs text-zinc-500">{{ __('Validated') }} {{ $this->storedKeys[$provider]['validated_at'] }}</flux:text>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-end gap-2">
                        <div class="grow">
                            <flux:input
                                wire:model="{{ $config['property'] }}"
                                type="password"
                                :placeholder="isset($this->storedKeys[$provider]) ? __('Enter new key to replace') : __('Paste your API key')"
                            />
                        </div>
                        <flux:button variant="primary" wire:click="saveKey('{{ $provider }}')">
                            {{ isset($this->storedKeys[$provider]) ? __('Update') : __('Save') }}
                        </flux:button>
                        @if (isset($this->storedKeys[$provider]))
                            <flux:button variant="danger" wire:click="deleteKey('{{ $provider }}')" wire:confirm="{{ __('Are you sure you want to delete this API key?') }}">
                                {{ __('Delete') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <x-action-message on="api-key-saved">
            {{ __('Saved.') }}
        </x-action-message>
    </x-pages::settings.layout>
</section>
