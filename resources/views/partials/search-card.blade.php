<div class="flex flex-col gap-2">
    <div class="relative">
        @if ($item['href'])
            <span class="absolute right-1.5 top-1.5 z-10 rounded-full bg-green-600 p-1.5 text-white" aria-label="{{ __('In libreria') }}">
                <flux:icon.check class="size-4" />
            </span>
        @else
            <button type="button" wire:click="add({{ $item['tmdb_id'] }}, '{{ $type }}')"
                class="absolute right-1.5 top-1.5 z-10 rounded-full bg-accent-content p-1.5 text-accent-foreground shadow" aria-label="{{ __('Aggiungi') }}">
                <flux:icon.plus class="size-4" />
            </button>
        @endif

        <button type="button" wire:click="open({{ $item['tmdb_id'] }}, '{{ $type }}')" class="block w-full" aria-label="{{ $item['title'] }}">
            @include('partials.poster', ['poster' => $item['poster'], 'title' => $item['title'], 'ratio' => 'aspect-[2/3]', 'size' => 'w342'])
        </button>

        <div wire:loading wire:target="open({{ $item['tmdb_id'] }}, '{{ $type }}')"
            class="absolute inset-0 z-20 flex items-center justify-center rounded-xl bg-black/50">
            <flux:icon.arrow-path class="size-6 animate-spin text-white" />
        </div>
    </div>

    <button type="button" wire:click="open({{ $item['tmdb_id'] }}, '{{ $type }}')" class="block w-full min-w-0 text-start">
        <flux:heading size="sm" class="truncate">{{ $item['title'] }}</flux:heading>
    </button>
    @if (! empty($item['year']))
        <flux:text size="sm" class="-mt-1 text-zinc-500">{{ $item['year'] }}</flux:text>
    @endif
</div>
