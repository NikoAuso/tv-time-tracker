<div class="flex flex-col gap-2">
    <div class="relative">
        @include('partials.poster', ['poster' => $item['poster'], 'title' => $item['title'], 'ratio' => 'aspect-[2/3]', 'size' => 'w342'])
        @if ($item['href'])
            <a href="{{ $item['href'] }}" wire:navigate
                class="absolute right-1.5 top-1.5 rounded-full bg-green-600 p-1.5 text-white" aria-label="{{ __('In libreria') }}">
                <flux:icon.check class="size-4" />
            </a>
        @else
            <button type="button" wire:click="add({{ $item['tmdb_id'] }}, '{{ $type }}')"
                class="absolute right-1.5 top-1.5 rounded-full bg-accent-content p-1.5 text-accent-foreground shadow" aria-label="{{ __('Aggiungi') }}">
                <flux:icon.plus class="size-4" />
            </button>
        @endif
    </div>
    <flux:heading size="sm" class="truncate">{{ $item['title'] }}</flux:heading>
    @if (! empty($item['year']))
        <flux:text size="sm" class="-mt-1 text-zinc-500">{{ $item['year'] }}</flux:text>
    @endif
</div>
