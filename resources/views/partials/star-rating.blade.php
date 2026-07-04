@props(['rating' => null])

<div class="flex items-center gap-1">
    @for ($i = 1; $i <= 5; $i++)
        <button type="button" wire:click="rate({{ $i }})"
            aria-label="{{ $i }} {{ __('stelle') }}"
            class="text-2xl leading-none transition hover:text-amber-400 {{ (int) ($rating ?? 0) >= $i ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600' }}">
            &#9733;
        </button>
    @endfor

    @if ($rating)
        <button type="button" wire:click="rate(0)"
            class="ml-1 text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">{{ __('azzera') }}</button>
    @endif
</div>
