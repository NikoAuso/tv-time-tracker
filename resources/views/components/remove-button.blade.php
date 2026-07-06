{{-- Azione di rimozione con testo: rosso outline. I pulsanti solo-icona restano danger pieni. --}}
<flux:button variant="outline"
    {{ $attributes->class('border-red-500/40! text-red-600! hover:bg-red-50! dark:border-red-500/30! dark:text-red-400! dark:hover:bg-red-950/40!') }}>
    {{ $slot }}
</flux:button>
