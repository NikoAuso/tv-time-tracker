@if (! empty($genres))
    @php $max = max($genres) ?: 1; @endphp
    <div class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('Generi') }}</flux:heading>
        <div class="flex flex-col gap-2">
            @foreach ($genres as $name => $count)
                <div class="flex items-center gap-3">
                    <flux:text class="w-28 shrink-0 truncate">{{ $name }}</flux:text>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-accent" style="width: {{ round($count / $max * 100) }}%"></div>
                    </div>
                    <flux:text class="w-8 shrink-0 text-right tabular-nums text-zinc-500">{{ $count }}</flux:text>
                </div>
            @endforeach
        </div>
    </div>
@endif
