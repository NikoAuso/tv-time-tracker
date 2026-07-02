@props(['item'])

<div class="relative flex aspect-[2/3] items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 text-3xl font-bold text-zinc-400 transition group-hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500">
    @if ($item['poster'])
        <img src="https://image.tmdb.org/t/p/w342{{ $item['poster'] }}" alt="{{ $item['title'] }}"
            class="h-full w-full object-cover" />
    @else
        {{ Str::of($item['title'])->trim()->substr(0, 1)->upper() }}
    @endif

    <span class="absolute left-1.5 top-1.5 rounded bg-black/65 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-white">
        {{ $item['type'] === 'movie' ? __('Film') : __('Serie') }}
    </span>
</div>

<div>
    <flux:heading size="sm" class="truncate">{{ $item['title'] }}</flux:heading>
    <flux:text size="sm" class="text-zinc-500">{{ $item['meta'] }}</flux:text>
</div>
