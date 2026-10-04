<div class="flex flex-col gap-2" wire:key="sc-{{ $type }}-{{ $item['tmdb_id'] }}">
    <div class="relative">
        @if ($item['href'])
            <button type="button" wire:click="remove({{ $item['tmdb_id'] }}, '{{ $type }}')"
                wire:confirm="{{ __('Rimuovere dai seguiti?') }}"
                class="group/rm absolute right-1.5 top-1.5 z-10 rounded-full bg-green-600 p-1.5 text-white shadow" aria-label="{{ __('Rimuovi dai seguiti') }}">
                <flux:icon.check class="size-4 group-hover/rm:hidden" />
                <flux:icon.x-mark class="hidden size-4 group-hover/rm:block" />
            </button>
        @else
            <button type="button" wire:click="add({{ $item['tmdb_id'] }}, '{{ $type }}')"
                class="absolute right-1.5 top-1.5 z-10 rounded-full bg-accent-content p-1.5 text-accent-foreground shadow" aria-label="{{ __('Aggiungi') }}">
                <flux:icon.plus class="size-4" />
            </button>
        @endif

        @if ($type === 'movies' && ! $item['watched'])
            <button type="button" wire:click="watched({{ $item['tmdb_id'] }})"
                class="absolute left-1.5 top-1.5 z-10 rounded-full bg-zinc-900/70 p-1.5 text-white shadow" aria-label="{{ __('Segna visto') }}">
                <flux:icon.eye class="size-4" />
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
