@props([
    'id',
    'title',
    'itemName',
    'deleteMethod' => 'delete',
    'deleteId',
])

<flux:modal :name="$id">
    <div class="space-y-6">
        <flux:heading size="lg">{{ $title }}</flux:heading>
        <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $itemName]) }}</flux:text>
        <div class="flex justify-end gap-3">
            <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="{{ $deleteMethod }}('{{ $deleteId }}')">{{ __('Delete') }}</flux:button>
        </div>
    </div>
</flux:modal>
