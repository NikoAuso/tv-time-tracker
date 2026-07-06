<div class="flex flex-col gap-3">
    <flux:heading size="lg">{{ __('Maratone') }}</flux:heading>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm" class="text-zinc-500">{{ __('Giornata record') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ number_format($stats['record'], 0, ',', '.') }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">
                {{ $unit }}{{ $stats['record_day'] ? ' · '.\Illuminate\Support\Carbon::parse($stats['record_day'])->translatedFormat('d M Y') : '' }}
            </flux:text>
        </div>
        <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm" class="text-zinc-500">{{ __('Media / giorno attivo') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ number_format($stats['avg_per_day'], 0, ',', '.') }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ $unit }}</flux:text>
        </div>
        <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm" class="text-zinc-500">{{ __('Giorni di visione') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ number_format($stats['active_days'], 0, ',', '.') }}</flux:heading>
        </div>
    </div>
    <flux:text size="sm" class="text-zinc-500">{{ __('La cronologia importata conta con la data di registrazione.') }}</flux:text>
</div>
