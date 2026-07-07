@props(['poster' => null, 'title' => '', 'ratio' => 'aspect-[2/3]', 'size' => 'w342'])

<div class="{{ $ratio }} flex items-center justify-center overflow-hidden rounded-lg bg-zinc-100 text-xl font-bold text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
    @if ($poster)
        <img src="https://image.tmdb.org/t/p/{{ $size }}{{ $poster }}" alt="{{ $title }}" loading="lazy" class="h-full w-full object-cover" />
    @else
        {{ \Illuminate\Support\Str::of($title)->trim()->substr(0, 1)->upper() }}
    @endif
</div>
