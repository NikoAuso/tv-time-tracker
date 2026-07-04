<?php

use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Import')] class extends Component {
    use WithFileUploads;

    public $archive;

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

    public function import(): void
    {
        $this->validate([
            'archive' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ]);

        $zip = new \ZipArchive;
        if ($zip->open($this->archive->getRealPath()) !== true) {
            $this->addError('archive', __('Impossibile aprire l\'archivio ZIP.'));

            return;
        }

        // Estraiamo solo i due CSV che ci servono, per nome: niente zip-slip e
        // nessun dato personale dell'export scritto su disco.
        $dir = storage_path('app/tvtime-import-'.Str::uuid());
        mkdir($dir, 0755, true);

        $hasRecords = false;
        foreach (['tracking-prod-records-v2.csv', 'tracking-prod-records.csv'] as $name) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (basename((string) $zip->getNameIndex($i)) === $name) {
                    file_put_contents($dir.'/'.$name, $zip->getFromIndex($i));
                    $hasRecords = $hasRecords || $name === 'tracking-prod-records-v2.csv';
                    break;
                }
            }
        }
        $zip->close();

        if (! $hasRecords) {
            $this->cleanup($dir);
            $this->addError('archive', __('Archivio non valido: manca tracking-prod-records-v2.csv.'));

            return;
        }

        Artisan::call('import:tvtime', ['path' => $dir, '--user' => (int) Auth::id()]);

        $this->cleanup($dir);
        $this->reset('archive');

        $token = Auth::user()->tmdb_token ?: (string) config('services.tmdb.token');

        if (filled($token)) {
            config(['services.tmdb.token' => $token]);
            Artisan::call('shows:sync');
            Artisan::call('movies:sync');
            Flux::toast(variant: 'success', text: __('Import e sincronizzazione TMDB completati.'));
        } else {
            Flux::toast(variant: 'success', text: __('Import completato. Imposta un token TMDB per poster e trame.'));
        }
    }

    private function cleanup(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Import')" :subheading="__('Importa i tuoi dati dall\'export di TV Time')">
        <div class="my-6 flex w-full max-w-md flex-col gap-8">
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading size="sm">{{ __('Token TMDB') }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('Serve per scaricare poster e trame. Prendi un Read Access Token (v4) su themoviedb.org.') }}
                    </flux:text>
                </div>

                @if (Auth::user()->hasTmdbToken())
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text>{{ __('Un token è attivo.') }}</flux:text>
                        <flux:button size="sm" variant="danger" wire:click="removeToken"
                            wire:confirm="{{ __('Rimuovere il token TMDB?') }}">{{ __('Rimuovi') }}</flux:button>
                    </div>
                @endif

                <form wire:submit="saveToken" class="flex flex-col gap-3">
                    <flux:input wire:model="tmdbToken" type="password"
                        :label="Auth::user()->hasTmdbToken() ? __('Nuovo token') : __('Token')" />
                    <flux:error name="tmdbToken" />
                    <flux:button type="submit" variant="primary" class="self-start">{{ __('Salva token') }}</flux:button>
                </form>
            </div>

            <flux:separator />

            <flux:text class="text-zinc-500">
                {{ __('Carica il file .zip dell\'export GDPR di TV Time. Verranno importati serie, episodi visti e film; con un token TMDB attivo poster e trame vengono sincronizzati subito dopo.') }}
            </flux:text>

            <form wire:submit="import" class="flex flex-col gap-4">
                <flux:input type="file" wire:model="archive" accept=".zip" :label="__('Archivio .zip')" />
                <flux:error name="archive" />
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" class="self-start">
                    {{ __('Importa') }}
                </flux:button>
            </form>

            <div wire:loading wire:target="import" class="flex items-center gap-2 text-sm text-zinc-500">
                <flux:icon.arrow-path class="size-4 animate-spin" />
                {{ __('Import e sincronizzazione in corso… può richiedere qualche minuto.') }}
            </div>
        </div>
    </x-pages::settings.layout>
</section>
