{{-- Badge delle piattaforme selezionate nel filtro; usa la prop $platformFilter del componente. --}}
@if (! empty($platformFilter))
    <div class="flex flex-wrap items-center gap-1.5">
        @foreach ($platformFilter as $name)
            <flux:badge size="sm" color="zinc" class="gap-1">
                {{ $name }}
                <button type="button" wire:click="removePlatform(@js($name))" aria-label="{{ __('Rimuovi filtro') }}"
                    class="-mr-0.5 rounded-full text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
                    <flux:icon.x-mark class="size-3.5" />
                </button>
            </flux:badge>
        @endforeach
    </div>
@endif
