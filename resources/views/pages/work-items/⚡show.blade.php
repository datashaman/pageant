<?php

use App\Jobs\ExecutePlan;
use App\Models\Plan;
use App\Models\WorkItem;
use App\Services\WorkItemOrchestrator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Work Item')] class extends Component {
    public WorkItem $workItem;

    public function mount(WorkItem $workItem): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($workItem->organization_id), 403);

        $this->workItem = $workItem->load(['organization', 'project']);
    }

    #[Computed]
    public function plans(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->workItem->plans()
            ->with('steps.agent', 'creator', 'approver')
            ->latest()
            ->get();
    }

    public function approvePlan(string $planId): void
    {
        $plan = Plan::where('organization_id', $this->workItem->organization_id)
            ->findOrFail($planId);

        if (! $plan->isPending()) {
            return;
        }

        $plan->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        ExecutePlan::dispatch($plan);

        unset($this->plans);
    }

    public function cancelPlan(string $planId): void
    {
        $plan = Plan::where('organization_id', $this->workItem->organization_id)
            ->findOrFail($planId);

        app(WorkItemOrchestrator::class)->cancel($plan);

        unset($this->plans);
    }

    public function confirmDelete(): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete');
    }

    public function delete(): void
    {
        $this->workItem->delete();

        $this->redirect(route('work-items.index'), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:button href="{{ route('work-items.index') }}" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                <flux:heading size="xl">{{ $workItem->title }}</flux:heading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('work-items.edit', $workItem) }}" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                <flux:button variant="danger" wire:click="confirmDelete">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>

        <div class="max-w-xl space-y-4">
            <div>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:text>{{ $workItem->title }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:text>{{ $workItem->description ?: '—' }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Organization') }}</flux:label>
                <flux:text>{{ $workItem->organization->name }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Project') }}</flux:label>
                @if ($workItem->project)
                    <flux:link href="{{ route('projects.show', $workItem->project) }}" wire:navigate>
                        {{ $workItem->project->name }}
                    </flux:link>
                @else
                    <flux:text>{{ __('—') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:label>{{ __('Source') }}</flux:label>
                <flux:text>{{ $workItem->source }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Board ID') }}</flux:label>
                <flux:text>{{ $workItem->board_id ?: '—' }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Source Reference') }}</flux:label>
                <flux:text>{{ $workItem->source_reference ?: '—' }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Source URL') }}</flux:label>
                @if ($workItem->source_url)
                    <flux:link href="{{ $workItem->source_url }}" target="_blank">
                        {{ $workItem->source_url }}
                    </flux:link>
                @else
                    <flux:text>{{ __('—') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:label>{{ __('Created') }}</flux:label>
                <flux:text>{{ $workItem->created_at->format('M j, Y g:i A') }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Updated') }}</flux:label>
                <flux:text>{{ $workItem->updated_at->format('M j, Y g:i A') }}</flux:text>
            </div>
        </div>

        {{-- Plans Section --}}
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Plans') }}</flux:heading>

            @forelse ($this->plans as $plan)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-4" wire:key="plan-{{ $plan->id }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:heading size="base">{{ __('Plan') }}</flux:heading>
                            @php
                                $badgeVariant = match($plan->status) {
                                    'pending' => 'warning',
                                    'approved' => 'info',
                                    'running' => 'info',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'cancelled' => 'default',
                                    default => 'default',
                                };
                            @endphp
                            <flux:badge :variant="$badgeVariant" size="sm">
                                {{ ucfirst($plan->status) }}
                            </flux:badge>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($plan->isPending())
                                <flux:button size="sm" variant="primary" wire:click="approvePlan('{{ $plan->id }}')">
                                    {{ __('Approve') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="cancelPlan('{{ $plan->id }}')">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @elseif ($plan->isRunning())
                                <flux:button size="sm" variant="danger" wire:click="cancelPlan('{{ $plan->id }}')">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    @if ($plan->summary)
                        <flux:text class="text-sm">{{ $plan->summary }}</flux:text>
                    @endif

                    <div class="text-xs text-zinc-500 dark:text-zinc-400 space-x-4">
                        <span>{{ __('Created :time', ['time' => $plan->created_at->diffForHumans()]) }}</span>
                        @if ($plan->approved_at)
                            <span>{{ __('Approved :time', ['time' => $plan->approved_at->diffForHumans()]) }}</span>
                        @endif
                        @if ($plan->completed_at)
                            <span>{{ __('Finished :time', ['time' => $plan->completed_at->diffForHumans()]) }}</span>
                        @endif
                    </div>

                    {{-- Steps --}}
                    @if ($plan->steps->isNotEmpty())
                        <div class="space-y-2">
                            @foreach ($plan->steps as $step)
                                <div class="flex items-start gap-3 rounded-md px-3 py-2 {{ $step->isRunning() ? 'bg-blue-50 dark:bg-blue-950/30' : ($step->isFailed() ? 'bg-red-50 dark:bg-red-950/30' : 'bg-zinc-50 dark:bg-zinc-800/50') }}" wire:key="step-{{ $step->id }}">
                                    <div class="mt-0.5 shrink-0">
                                        @if ($step->isCompleted())
                                            <div class="size-5 rounded-full bg-green-500 flex items-center justify-center">
                                                <svg class="size-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            </div>
                                        @elseif ($step->isFailed())
                                            <div class="size-5 rounded-full bg-red-500 flex items-center justify-center">
                                                <svg class="size-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                            </div>
                                        @elseif ($step->isRunning())
                                            <div class="size-5 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></div>
                                        @elseif ($step->status === 'skipped')
                                            <div class="size-5 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                                        @else
                                            <div class="size-5 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium">{{ $step->order }}.</span>
                                            <span class="text-sm">{{ $step->description }}</span>
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                            {{ __('Agent: :name', ['name' => $step->agent->name]) }}
                                        </div>
                                        @if ($step->result)
                                            <div class="text-xs text-zinc-600 dark:text-zinc-300 mt-1 italic">
                                                {{ Str::limit($step->result, 200) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <flux:text class="text-zinc-500">{{ __('No plans yet. Plans are created when agents analyze this work item.') }}</flux:text>
            @endforelse
        </div>

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Work Item') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":title"? This action cannot be undone.', ['title' => $workItem->title]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
