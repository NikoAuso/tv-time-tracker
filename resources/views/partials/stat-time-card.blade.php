<div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
    <flux:text size="sm" class="text-zinc-500">{{ __('Tempo') }}</flux:text>
    <div class="flex flex-wrap items-baseline gap-x-1.5">
        @foreach ($parts as $part)
            <span class="text-lg font-bold tabular-nums">{{ $part['value'] }}</span>
            <span class="text-xs text-zinc-500">{{ $part['unit'] }}</span>
        @endforeach
    </div>
</div>
