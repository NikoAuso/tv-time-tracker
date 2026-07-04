<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Sblocca')] class extends Component {
    public string $pin = '';

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function mount(): void
    {
        if (Auth::user()?->pin === null) {
            session(['pin_unlocked' => true]);
            $this->redirectRoute('dashboard', navigate: false);
        }
    }

    public function unlock(): void
    {
        $user = Auth::user();
        $key = 'pin-unlock:'.$user?->id;

        $this->resetErrorBag('pin');

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->addError('pin', __('Troppi tentativi. Riprova tra :seconds secondi.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));
            $this->reset('pin');

            return;
        }

        if ($user?->pin === null || ! Hash::check($this->pin, $user->pin)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->addError('pin', __('PIN errato.'));
            $this->reset('pin');

            return;
        }

        RateLimiter::clear($key);
        session(['pin_unlocked' => true]);
        $this->redirectRoute('dashboard', navigate: false);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col items-center gap-1 text-center">
        <flux:icon.lock-closed class="mb-2 size-8 text-zinc-400" />
        <flux:heading size="lg">{{ __('App bloccata') }}</flux:heading>
        <flux:subheading>{{ __('Inserisci il PIN per continuare') }}</flux:subheading>
    </div>

    <form wire:submit="unlock" class="flex flex-col gap-4">
        <flux:input wire:model="pin" type="password" inputmode="numeric" autofocus
            :label="__('PIN')" />
        <flux:error name="pin" />
        <flux:button type="submit" variant="primary" class="w-full">{{ __('Sblocca') }}</flux:button>
    </form>
</div>
