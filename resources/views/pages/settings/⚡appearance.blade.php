<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Aspetto')] class extends Component {
    //
}; ?>

<section class="w-full">
    <flux:heading class="sr-only">{{ __('Aspetto') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Aspetto')" :subheading="__('Scegli il tema dell\'app')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Chiaro') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Scuro') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('Sistema') }}</flux:radio>
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
