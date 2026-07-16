<?php

use App\Services\UserData;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tutti gli import ricevono il file come base64 (via wire:call dal componente
 * x-dropzone) invece che come upload-file: il PHP embarcato di NativePHP limita
 * upload_max_filesize a 2MB, mentre i dati POST sono vincolati dal più ampio
 * post_max_size (e da payload.max_size di Livewire, alzato in config).
 */
new #[Title('Importa / Esporta dati')] class extends Component {
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

    public function import(string $name, string $data): void
    {
        if (! str_ends_with(strtolower($name), '.zip')) {
            $this->addError('archive', __('Serve un archivio .zip.'));

            return;
        }

        $bytes = $this->decodeUpload($data);
        if ($bytes === false) {
            $this->addError('archive', __('File non valido.'));

            return;
        }

        $dir = $this->extractZip($bytes, fn (string $e): bool => in_array($e, ['tracking-prod-records-v2.csv', 'tracking-prod-records.csv'], true));
        if ($dir === null || ! is_file($dir.'/tracking-prod-records-v2.csv')) {
            if ($dir !== null) {
                $this->cleanup($dir);
            }
            $this->addError('archive', __('Archivio non valido: manca tracking-prod-records-v2.csv.'));

            return;
        }

        // Token prima dell'import: così il matching dei film risolve il tmdb_id
        // inline (titolo+anno) e converge con l'import dell'estensione.
        $token = (string) Auth::user()->tmdb_token;
        if (filled($token)) {
            config(['services.tmdb.token' => $token]);
        }

        Artisan::call('import:tvtime', ['path' => $dir, '--user' => (int) Auth::id()]);
        $this->cleanup($dir);

        if (filled($token)) {
            Artisan::call('shows:sync');
            Artisan::call('movies:sync');
            Flux::toast(variant: 'success', text: __('Importazione e sincronizzazione TMDB completate.'));
        } else {
            Flux::toast(variant: 'success', text: __('Importazione completata. Imposta un token TMDB per poster e trame.'));
        }
    }

    public function importExtension(string $name, string $data): void
    {
        if (! str_ends_with(strtolower($name), '.zip')) {
            $this->addError('extArchive', __('Serve un archivio .zip.'));

            return;
        }

        $bytes = $this->decodeUpload($data);
        if ($bytes === false) {
            $this->addError('extArchive', __('File non valido.'));

            return;
        }

        $dir = $this->extractZip($bytes, fn (string $e): bool => str_ends_with($e, '.json') && (str_contains($e, 'series') || str_contains($e, 'movies')));
        if ($dir === null) {
            $this->addError('extArchive', __('Archivio non valido: mancano i file JSON dell\'estensione (series/movies).'));

            return;
        }

        config(['services.tmdb.token' => (string) Auth::user()->tmdb_token]);
        Artisan::call('import:tvtime-json', ['path' => $dir, '--user' => (int) Auth::id()]);
        Artisan::call('shows:sync');
        $this->cleanup($dir);

        Flux::toast(variant: 'success', text: __('Import dall\'estensione completato.'));
    }

    public function importJson(string $name, string $data): void
    {
        if (! str_ends_with(strtolower($name), '.json')) {
            $this->addError('jsonFile', __('Serve un file .json.'));

            return;
        }

        $bytes = $this->decodeUpload($data);
        if ($bytes === false) {
            $this->addError('jsonFile', __('File non valido.'));

            return;
        }

        $decoded = json_decode($bytes, true);
        if (! is_array($decoded) || ($decoded['app'] ?? null) !== UserData::APP) {
            $this->addError('jsonFile', __('File non valido: non è un backup di TvTimeTracker.'));

            return;
        }

        UserData::import((int) Auth::id(), $decoded);

        // Come per gli altri import: con token, sincronizza da TMDB (poster, trame
        // e soprattutto l'elenco episodi, che il backup non contiene).
        $token = (string) Auth::user()->tmdb_token;
        if (filled($token)) {
            config(['services.tmdb.token' => $token]);
            Artisan::call('shows:sync');
            Artisan::call('movies:sync');
            Flux::toast(text: __('Dati importati e sincronizzati con TMDB.'), variant: 'success');
        } else {
            Flux::toast(text: __('Dati importati. Imposta un token TMDB per poster, trame ed episodi.'), variant: 'success');
        }
    }

    public function wipeData(): void
    {
        UserData::wipe((int) Auth::id());

        Flux::toast(variant: 'success', text: __('Tutti i tuoi dati sono stati eliminati.'));
    }

    private function decodeUpload(string $data): string|false
    {
        return base64_decode((string) preg_replace('/^data:[^,]*,/', '', $data), true);
    }

    /**
     * Salva i bytes come zip temporaneo ed estrae in una cartella i soli file
     * per cui $keep(nome) è true. Ritorna la cartella, o null se lo zip è
     * illeggibile o nessun file corrisponde.
     */
    private function extractZip(string $bytes, callable $keep): ?string
    {
        $tmpZip = storage_path('app/tvt-'.Str::uuid().'.zip');
        file_put_contents($tmpZip, $bytes);

        $zip = new \ZipArchive;
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);

            return null;
        }

        $dir = storage_path('app/tvt-'.Str::uuid());
        mkdir($dir, 0755, true);

        $extracted = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = basename((string) $zip->getNameIndex($i));
            if ($keep($entry)) {
                file_put_contents($dir.'/'.$entry, $zip->getFromIndex($i));
                $extracted++;
            }
        }
        $zip->close();
        @unlink($tmpZip);

        if ($extracted === 0) {
            $this->cleanup($dir);

            return null;
        }

        return $dir;
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
    <x-pages::settings.layout :heading="__('Importa / Esporta dati')" :subheading="__('Importa da TV Time o gestisci i backup dell\'app')">
        <div class="my-6 flex w-full max-w-md flex-col gap-6">
            <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-start gap-3">
                    <flux:icon.inbox-arrow-down class="mt-0.5 size-5 shrink-0 text-zinc-500" />
                    <div class="flex flex-col gap-1">
                        <flux:heading size="sm">{{ __('Importa da TV Time') }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Dalla pagina GDPR di TV Time accedi e attendi che prepari il file (qualche minuto), poi carica qui lo .zip: importa serie, episodi visti e film. Con un token TMDB attivo, poster e trame si sincronizzano subito dopo.') }}
                        </flux:text>
                        <flux:link href="https://gdpr.tvtime.com/gdpr/self-service" target="_blank" class="text-sm font-medium">
                            {{ __('Scarica i tuoi dati da TV Time (GDPR) →') }}
                        </flux:link>
                    </div>
                </div>

                <x-dropzone action="import" accept=".zip"
                    :label="__('Trascina il file .zip qui o clicca per sceglierlo')"
                    :hint="__('Export GDPR di TV Time')" />
                <flux:error name="archive" />
            </div>

            <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-start gap-3">
                    <flux:icon.puzzle-piece class="mt-0.5 size-5 shrink-0 text-zinc-500" />
                    <div class="flex flex-col gap-1">
                        <flux:heading size="sm">{{ __('Importa da estensione TV Time Out') }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Installa l\'estensione «TV Time Out», aprila mentre sei connesso a TV Time nel browser ed esporta in formato ZIP (JSON). Poi carica qui lo .zip: match TMDB preciso tramite gli id esterni (imdb/tvdb).') }}
                        </flux:text>
                        <flux:link href="https://chromewebstore.google.com/detail/tv-time-out-by-refract/pmejpdpjbkjklfceogdkolmgclldogbi" target="_blank" class="text-sm font-medium">
                            {{ __('Installa «TV Time Out» (Chrome) →') }}
                        </flux:link>
                    </div>
                </div>

                <x-dropzone action="importExtension" accept=".zip"
                    :label="__('Trascina lo .zip dell\'estensione o clicca')" />
                <flux:error name="extArchive" />
            </div>

            <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-start gap-3">
                    <flux:icon.archive-box class="mt-0.5 size-5 shrink-0 text-zinc-500" />
                    <div class="flex flex-col gap-1">
                        <flux:heading size="sm">{{ __('Backup TvTimeTracker') }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Esporta tutti i tuoi dati in JSON, o importa un backup creato da questa app (unione, mai duplicati).') }}
                        </flux:text>
                    </div>
                </div>

                <flux:button wire:click="exportJson" variant="primary" icon="arrow-down-tray" class="self-start">
                    {{ __('Esporta dati (JSON)') }}
                </flux:button>

                <flux:separator variant="subtle" />

                <x-dropzone action="importJson" accept=".json"
                    :label="__('Trascina il backup .json o clicca')" />
                <flux:error name="jsonFile" />

                <flux:separator variant="subtle" />

                <flux:button wire:click="wipeData" variant="danger" icon="trash" class="self-start"
                    wire:confirm="{{ __('Eliminare TUTTI i tuoi dati (serie, film, episodi visti e liste)? L\'operazione è irreversibile.') }}">
                    {{ __('Elimina tutti i dati') }}
                </flux:button>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
