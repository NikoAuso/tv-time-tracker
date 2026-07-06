<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('PIN')] class extends Component {
    public string $pin = '';

    public string $pin_confirmation = '';

    public function updatePin(): void
    {
        $this->validate([
            'pin' => ['required', 'digits_between:4,8', 'same:pin_confirmation'],
        ], ['pin.same' => __('I due PIN non coincidono.')]);

        $user = Auth::user();
        $user->pin = $this->pin;
        $user->save();

        session(['pin_unlocked' => true]);
        $this->reset('pin', 'pin_confirmation');

        Flux::toast(variant: 'success', text: __('PIN aggiornato.'));
    }

    public function removePin(): void
    {
        $user = Auth::user();
        $user->pin = null;
        $user->save();

        session()->forget('pin_unlocked');

        Flux::toast(text: __('PIN rimosso.'));
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('PIN')" :subheading="__('Blocco locale dell\'app (opzionale)')">
        <div class="my-6 flex w-full max-w-sm flex-col gap-6">
            @if (Auth::user()->hasPin())
                <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text>{{ __('Un PIN è attivo.') }}</flux:text>
                    <flux:button size="sm" variant="danger" icon="trash" wire:click="removePin"
                        wire:confirm="{{ __('Rimuovere il PIN? L\'app non sarà più bloccata.') }}">{{ __('Rimuovi') }}</flux:button>
                </div>
            @endif

            <form wire:submit="updatePin" class="flex flex-col gap-4">
                <flux:input wire:model="pin" type="password" inputmode="numeric"
                    :label="Auth::user()->hasPin() ? __('Nuovo PIN') : __('PIN')" />
                <flux:input wire:model="pin_confirmation" type="password" inputmode="numeric"
                    :label="__('Conferma PIN')" />
                <flux:button type="submit" variant="primary" class="self-start">{{ __('Salva PIN') }}</flux:button>
            </form>
        </div>
    </x-pages::settings.layout>
</section>
