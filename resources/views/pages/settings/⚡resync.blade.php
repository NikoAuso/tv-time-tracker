<?php

use App\Models\Movie;
use App\Models\Show;
use App\Services\WatchProviders;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Risincronizza da TMDB')] class extends Component
{
    /**
     * Risincronizza tutta la libreria con TMDB: metadati, episodi e piattaforme.
     */
    public function resync(): void
    {
        $token = (string) Auth::user()->tmdb_token;
        if (blank($token)) {
            Flux::toast(variant: 'danger', text: __('Serve un token TMDB per la sincronizzazione.'));

            return;
        }

        config(['services.tmdb.token' => $token]);

        Artisan::call('shows:sync', ['--all' => true]);
        Artisan::call('movies:sync', ['--all' => true]);

        $providers = app(WatchProviders::class);
        Show::whereNotNull('tmdb_id')->get()->each(fn (Show $show) => $providers->forShow($show, force: true));
        Movie::whereNotNull('tmdb_id')->get()->each(fn (Movie $movie) => $providers->forMovie($movie, force: true));

        Flux::toast(variant: 'success', text: __('Libreria risincronizzata da TMDB.'));
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Risincronizza da TMDB')" :subheading="__('Aggiorna i dati della libreria dalle informazioni di TMDB')">
        <div class="my-6 flex w-full max-w-md flex-col gap-6">
            <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-start gap-3">
                    <flux:icon.arrow-path class="mt-0.5 size-5 shrink-0 text-zinc-500" />
                    <div class="flex flex-col gap-1">
                        <flux:heading size="sm">{{ __('Risincronizza tutta la libreria') }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Riscarica da TMDB poster, trame, elenco episodi e piattaforme «Dove guardarlo» di tutta la libreria. Serve per aggiornare i dati e per popolare il filtro per piattaforma. Può richiedere qualche minuto.') }}
                        </flux:text>
                    </div>
                </div>

                <flux:button wire:click="resync" variant="primary" icon="arrow-path" class="self-start"
                    wire:target="resync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="resync">{{ __('Risincronizza ora') }}</span>
                    <span wire:loading wire:target="resync">{{ __('Sincronizzazione in corso…') }}</span>
                </flux:button>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
