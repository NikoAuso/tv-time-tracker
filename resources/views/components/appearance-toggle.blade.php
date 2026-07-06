<div x-data="{ dark: document.documentElement.classList.contains('dark') }" {{ $attributes }}>
    <flux:button x-cloak x-show="dark"
        x-on:click="dark = false; $flux.appearance = 'light'"
        variant="outline" size="sm" icon="sun" :aria-label="__('Passa al tema chiaro')" />
    <flux:button x-cloak x-show="! dark"
        x-on:click="dark = true; $flux.appearance = 'dark'"
        variant="outline" size="sm" icon="moon" :aria-label="__('Passa al tema scuro')" />
</div>
