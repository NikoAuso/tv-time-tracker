<div class="col-span-2 flex flex-col gap-2 rounded-xl border border-zinc-200 p-4 lg:col-span-3 xl:col-span-6 dark:border-zinc-700">
    <flux:text size="sm" class="text-zinc-500">{{ __('Tempo di visione') }}</flux:text>
    <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1">
        @foreach ($parts as $part)
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-bold tabular-nums">{{ $part['value'] }}</span>
                <span class="text-sm text-zinc-500">{{ $part['unit'] }}</span>
            </div>
        @endforeach
    </div>
</div>
