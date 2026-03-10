<?php

use App\Models\Agent;
use App\Models\Skill;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Workspace')] class extends Component {
    public Workspace $workspace;

    public string $name = '';
    public string $description = '';
    public array $references = [];
    public array $selectedAgents = [];
    public array $selectedSkills = [];

    public function mount(Workspace $workspace): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($workspace->organization_id), 403);

        $this->workspace = $workspace->load(['references', 'agents', 'skills']);

        $this->name = $workspace->name;
        $this->description = $workspace->description ?? '';
        $this->references = $workspace->references->map(fn ($ref) => [
            'id' => $ref->id,
            'source' => $ref->source,
            'source_reference' => $ref->source_reference,
            'source_url' => $ref->source_url ?? '',
        ])->toArray();
        $this->selectedAgents = $workspace->agents->pluck('id')->toArray();
        $this->selectedSkills = $workspace->skills->pluck('id')->toArray();

        if (empty($this->references)) {
            $this->references = [['source' => 'github', 'source_reference' => '', 'source_url' => '']];
        }
    }

    #[Computed]
    public function agents(): Collection
    {
        return Agent::query()
            ->where('organization_id', $this->workspace->organization_id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function skills(): Collection
    {
        return Skill::query()
            ->where('organization_id', $this->workspace->organization_id)
            ->orderBy('name')
            ->get();
    }

    public function addReference(): void
    {
        $this->references[] = ['source' => 'github', 'source_reference' => '', 'source_url' => ''];
    }

    public function removeReference(int $index): void
    {
        unset($this->references[$index]);
        $this->references = array_values($this->references);
    }

    public function save(): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($this->workspace->organization_id), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'references' => ['array'],
            'references.*.source' => ['required', 'string', 'max:255'],
            'references.*.source_reference' => ['required', 'string', 'max:255'],
            'references.*.source_url' => ['nullable', 'string', 'url', 'max:255'],
            'selectedAgents' => ['array'],
            'selectedAgents.*' => ['uuid', Rule::exists('agents', 'id')->where('organization_id', $this->workspace->organization_id)],
            'selectedSkills' => ['array'],
            'selectedSkills.*' => ['uuid', Rule::exists('skills', 'id')->where('organization_id', $this->workspace->organization_id)],
        ]);

        $this->workspace->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $existingIds = $this->workspace->references()->pluck('id')->toArray();
        $keptIds = [];

        foreach ($this->references as $ref) {
            if (! empty($ref['source_reference'])) {
                if (! empty($ref['id']) && in_array($ref['id'], $existingIds)) {
                    $this->workspace->references()->where('id', $ref['id'])->update([
                        'source' => $ref['source'],
                        'source_reference' => $ref['source_reference'],
                        'source_url' => $ref['source_url'] ?: null,
                    ]);
                    $keptIds[] = $ref['id'];
                } else {
                    $newRef = $this->workspace->references()->create([
                        'source' => $ref['source'],
                        'source_reference' => $ref['source_reference'],
                        'source_url' => $ref['source_url'] ?: null,
                    ]);
                    $keptIds[] = $newRef->id;
                }
            }
        }

        $this->workspace->references()->whereNotIn('id', $keptIds)->delete();

        $this->workspace->agents()->sync($this->selectedAgents);
        $this->workspace->skills()->sync($this->selectedSkills);

        $this->redirect(route('workspaces.show', $this->workspace), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'workspaces.edit', 'workspace_id' => $workspace->id, 'workspace_name' => $workspace->name]) }}">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('workspaces.show', $workspace) }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Edit Workspace') }}</flux:heading>
        </div>

        <form wire:submit="save" class="max-w-xl space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

            <fieldset class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">{{ __('References') }}</flux:heading>
                    <flux:button size="sm" type="button" wire:click="addReference">
                        {{ __('Add Reference') }}
                    </flux:button>
                </div>

                @foreach ($references as $index => $ref)
                    <div class="flex items-end gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="ref-{{ $index }}">
                        <div class="flex-1 space-y-3">
                            <flux:select wire:model="references.{{ $index }}.source" :label="__('Source')">
                                <option value="github">{{ __('GitHub') }}</option>
                                <option value="gitlab">{{ __('GitLab') }}</option>
                                <option value="bitbucket">{{ __('Bitbucket') }}</option>
                            </flux:select>
                            <flux:input wire:model="references.{{ $index }}.source_reference" :label="__('Reference')" :description="__('e.g. owner/repo or owner/repo#42')" type="text" />
                            <flux:input wire:model="references.{{ $index }}.source_url" :label="__('URL')" type="text" />
                        </div>
                        @if (count($references) > 1)
                            <flux:button size="sm" variant="danger" type="button" wire:click="removeReference({{ $index }})">
                                {{ __('Remove') }}
                            </flux:button>
                        @endif
                    </div>
                @endforeach
            </fieldset>

            @if ($this->agents->isNotEmpty())
                <fieldset class="space-y-2">
                    <flux:heading size="sm">{{ __('Associated Agents') }}</flux:heading>
                    @foreach ($this->agents as $agent)
                        <flux:checkbox wire:model="selectedAgents" :label="$agent->name" :value="$agent->id" />
                    @endforeach
                </fieldset>
            @endif

            @if ($this->skills->isNotEmpty())
                <fieldset class="space-y-2">
                    <flux:heading size="sm">{{ __('Associated Skills') }}</flux:heading>
                    @foreach ($this->skills as $skill)
                        <flux:checkbox wire:model="selectedSkills" :label="$skill->name" :value="$skill->id" />
                    @endforeach
                </fieldset>
            @endif

            <x-form-actions :submit-label="__('Update')" :cancel-url="route('workspaces.show', $workspace)" />
        </form>
    </div>
</div>
