<?php

use App\Services\UserData;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Importa dati')] class extends Component {
    use WithFileUploads;

    public $archive;

    public $jsonFile;

    public string $tmdbToken = '';

    public function exportJson()
    {
        $json = (string) json_encode(
            UserData::export((int) Auth::id()),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return response()->streamDownload(
            fn () => print ($json),
            'tv-time-tracker-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function importJson(): void
    {
        $this->validate(['jsonFile' => ['required', 'file', 'max:51200']]);

        $data = json_decode((string) file_get_contents($this->jsonFile->getRealPath()), true);

        if (! is_array($data) || ($data['app'] ?? null) !== UserData::APP) {
            $this->addError('jsonFile', __('File non valido: non è un backup di TvTimeTracker.'));

            return;
        }

        UserData::import((int) Auth::id(), $data);

        $this->reset('jsonFile');

        Flux::toast(variant: 'success', text: __('Dati importati.'));
    }

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
            Flux::toast(variant: 'success', text: __('Importazione e sincronizzazione TMDB completate.'));
        } else {
            Flux::toast(variant: 'success', text: __('Importazione completata. Imposta un token TMDB per poster e trame.'));
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
    <x-pages::settings.layout :heading="__('Importa dati')" :subheading="__('Importa i tuoi dati dall\'export di TV Time')">
        <div class="my-6 flex w-full max-w-md flex-col gap-8">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <flux:heading size="sm">{{ __('Token TMDB') }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('Serve per scaricare poster e trame.') }}
                    </flux:text>
                    <flux:modal.trigger name="tmdb-guide">
                        <flux:link class="cursor-pointer text-sm">{{ __('Come ottenere il token →') }}</flux:link>
                    </flux:modal.trigger>
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
                {{ __('Importazione e sincronizzazione in corso… può richiedere qualche minuto.') }}
            </div>

            <flux:separator />

            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <flux:heading size="sm">{{ __('Backup TvTimeTracker') }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('Esporta tutti i tuoi dati in JSON, o importa un backup creato da questa app (unione, mai duplicati).') }}
                    </flux:text>
                </div>

                <flux:button wire:click="exportJson" variant="primary" icon="arrow-down-tray" class="self-start">
                    {{ __('Esporta dati (JSON)') }}
                </flux:button>

                <form wire:submit="importJson" class="flex flex-col gap-3">
                    <flux:input type="file" wire:model="jsonFile" accept=".json" :label="__('Backup .json')" />
                    <flux:error name="jsonFile" />
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray" class="self-start">
                        {{ __('Importa backup') }}
                    </flux:button>
                </form>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
