<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $currentOrganizationId = '';

    public function mount(): void
    {
        $this->currentOrganizationId = auth()->user()->currentOrganizationId() ?? '';
    }

    #[Computed]
    public function organizations(): Collection
    {
        return auth()->user()->organizations;
    }

    public function updatedCurrentOrganizationId(string $value): void
    {
        auth()->user()->update(['current_organization_id' => $value ?: null]);

        $this->redirect(request()->header('Referer', '/'), navigate: true);
    }
};
?>

<div>
    @if ($this->organizations->count() > 1)
        <div class="px-4 py-2">
            <flux:select wire:model.live="currentOrganizationId" size="sm">
                @foreach ($this->organizations as $organization)
                    <flux:select.option :value="$organization->id">{{ $organization->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    @endif
</div>
