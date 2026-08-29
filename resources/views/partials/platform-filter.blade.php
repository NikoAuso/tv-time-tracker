{{-- @param array<string, string|null> $platforms  name => logo_path. Usa la prop $platformFilter (array) del componente. --}}
<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" icon="funnel" :variant="! empty($platformFilter) ? 'primary' : 'outline'" aria-label="{{ __('Filtra per piattaforma') }}" />

    <flux:menu>
        <flux:menu.checkbox.group wire:model.live="platformFilter">
            @foreach ($platforms as $name => $logo)
                <flux:menu.checkbox value="{{ $name }}">
                    <span class="flex items-center gap-2">
                        @if ($logo)
                            <img src="https://image.tmdb.org/t/p/w92{{ $logo }}" alt="" class="size-5 rounded object-cover" />
                        @endif
                        {{ $name }}
                    </span>
                </flux:menu.checkbox>
            @endforeach
        </flux:menu.checkbox.group>
    </flux:menu>
</flux:dropdown>
