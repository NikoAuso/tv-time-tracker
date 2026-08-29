{{-- @param App\Models\Movie $movie --}}
<a href="{{ route('movies.show', $movie) }}" wire:navigate
    class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 no-underline transition hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
    @include('partials.poster', ['poster' => $movie->poster_path, 'title' => $movie->title, 'ratio' => 'h-24 w-16 shrink-0', 'size' => 'w185'])
    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
        <flux:heading size="sm" class="truncate">{{ $movie->title }}</flux:heading>
        <flux:text size="sm" class="tabular-nums text-zinc-500">
            @if ($movie->release_date){{ $movie->release_date->year }}@endif
            @if ($movie->runtime)
                @php $h = intdiv($movie->runtime, 60); $m = $movie->runtime % 60; @endphp
                {{ $movie->release_date ? ' · ' : '' }}{{ $h ? $h.'h'.($m ? ' '.$m.'min' : '') : $m.'min' }}
            @endif
        </flux:text>
    </div>
</a>
