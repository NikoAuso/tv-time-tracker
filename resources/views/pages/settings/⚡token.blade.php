<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Token TMDB')] class extends Component {
    public string $tmdbToken = '';

    public function saveToken(): void
    {
        $validated = $this->validate(['tmdbToken' => ['required', 'string', 'min:20']]);

        $user = Auth::user();
        $user->tmdb_token = trim($validated['tmdbToken']);
        $user->save();

        $this->reset('tmdbToken');

        Flux::toast(variant: 'success', text: __('Token TMDB salvato.'));
    }

    public function removeToken(): void
    {
        $user = Auth::user();
        $user->tmdb_token = null;
        $user->save();

        Flux::toast(text: __('Token TMDB rimosso.'));
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Token TMDB')" :subheading="__('Serve per scaricare poster, trame ed episodi')">
        <div class="my-6 flex w-full max-w-md flex-col gap-8">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('Ogni utente usa il proprio token TMDB personale.') }}
                    </flux:text>
                    <flux:modal.trigger name="tmdb-guide">
                        <flux:link class="cursor-pointer text-sm">{{ __('Come ottenere il token →') }}</flux:link>
                    </flux:modal.trigger>
                </div>

                @unless (Auth::user()->hasTmdbToken())
                    <div class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/50 dark:bg-amber-950/40">
                        <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <flux:text class="text-sm text-amber-900 dark:text-amber-200">
                            {{ __('Per usare l\'app devi inserire il tuo token TMDB. Senza, ricerca, poster e sincronizzazione non sono disponibili.') }}
                        </flux:text>
                    </div>
                @endunless

                @if (Auth::user()->hasTmdbToken())
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text>{{ __('Un token è attivo.') }}</flux:text>
                        <x-remove-button size="sm" icon="trash" wire:click="removeToken"
                            wire:confirm="{{ __('Rimuovere il token TMDB?') }}">{{ __('Rimuovi') }}</x-remove-button>
                    </div>
                @endif

                <form wire:submit="saveToken" class="flex flex-col gap-3">
                    <flux:input wire:model="tmdbToken" type="password"
                        :label="Auth::user()->hasTmdbToken() ? __('Nuovo token') : __('Token')" />
                    <flux:error name="tmdbToken" />
                    <flux:button type="submit" variant="primary" class="self-start">{{ __('Salva token') }}</flux:button>
                </form>
            </div>

            <flux:modal name="tmdb-guide" class="max-w-md">
                <div class="flex max-h-[80vh] flex-col gap-5 overflow-y-auto py-2">
                    <div>
                        <flux:heading size="lg">{{ __('Come ottenere il token TMDB') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-500">
                            {{ __('Serve un account gratuito su TheMovieDB. Bastano un paio di minuti.') }}
                        </flux:text>
                    </div>

                    <ol class="flex flex-col gap-4">
                        @foreach ([
                            __('Crea un account o accedi su themoviedb.org.'),
                            __('Apri Impostazioni → API, oppure vai su themoviedb.org/settings/api.'),
                            __('Dovrai creare una chiave di tipo "Developer": clicca su "+Creare" e compila con i dati che vedi in foto (sono fittizi e puoi mettere quello che vuoi). Compila anche le tue informazioni personali in fondo alla pagine e poi su "Subscribe".'),
                            __('Copia il token in "API Read Access Token"'),
                            __('Torna qui, incolla il token nel campo e premi «Salva token».'),
                        ] as $i => $step)
                            <li class="flex gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-accent-content text-xs font-semibold text-accent-foreground">{{ $i + 1 }}</span>
                                <div class="flex flex-1 flex-col gap-2">
                                    <flux:text size="sm">{{ $step }}</flux:text>
                                    @php $shot = public_path('img/tmdb/step-'.($i + 1).'.png'); @endphp
                                    @if (file_exists($shot))
                                        <img src="{{ asset('img/tmdb/step-'.($i + 1).'.png') }}" alt=""
                                            class="max-h-80 w-auto self-start rounded-lg border border-zinc-200 dark:border-zinc-700" />
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <flux:button href="https://www.themoviedb.org/settings/api" target="_blank"
                        variant="primary" icon="arrow-up-right" class="self-start">
                        {{ __('Apri TMDB') }}
                    </flux:button>
                </div>
            </flux:modal>
        </div>
    </x-pages::settings.layout>
</section>
