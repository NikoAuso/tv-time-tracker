@props(['lists', 'activeIds' => []])

<flux:dropdown position="bottom" align="start">
    <flux:button icon="list-bullet" variant="outline" size="sm">{{ __('Liste') }}</flux:button>

    <flux:menu>
        @forelse ($lists as $list)
            <flux:menu.item wire:click="toggleList({{ $list->id }})"
                :icon="in_array($list->id, $activeIds, true) ? 'check' : null">
                {{ $list->name }}
            </flux:menu.item>
        @empty
            <flux:menu.item :href="route('lists')" wire:navigate icon="plus">{{ __('Crea una lista…') }}</flux:menu.item>
        @endforelse
    </flux:menu>
</flux:dropdown>
