<?php

use App\Services\UserData;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Importa / Esporta dati')] class extends Component {
    use WithFileUploads;

    public $archive;

    public $jsonFile;

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

        // Token prima dell'import: così il matching dei film risolve il tmdb_id
        // inline (titolo+anno) e converge con l'import dell'estensione.
        $token = (string) Auth::user()->tmdb_token;
        if (filled($token)) {
            config(['services.tmdb.token' => $token]);
        }

        Artisan::call('import:tvtime', ['path' => $dir, '--user' => (int) Auth::id()]);

        $this->cleanup($dir);
        $this->reset('archive');

        if (filled($token)) {
            Artisan::call('shows:sync');
            Artisan::call('movies:sync');
            Flux::toast(variant: 'success', text: __('Importazione e sincronizzazione TMDB completate.'));
        } else {
            Flux::toast(variant: 'success', text: __('Importazione completata. Imposta un token TMDB per poster e trame.'));
        }
    }

    /**
     * Riceve lo zip dell'estensione come data-URL base64 (via wire:call), non
     * come upload di file: il PHP embarcato di NativePHP ha upload_max_filesize
     * a 2 MB e lo zip lo supera, mentre i dati POST sono limitati dal ben più
     * ampio post_max_size.
     */
    public function importExtension(string $name, string $data): void
    {
        if (! str_ends_with(strtolower($name), '.zip')) {
            $this->addError('extArchive', __('Serve un archivio .zip.'));

            return;
        }

        $bytes = base64_decode((string) preg_replace('/^data:[^,]*,/', '', $data), true);
        if ($bytes === false) {
            $this->addError('extArchive', __('File non valido.'));

            return;
        }

        $tmpZip = storage_path('app/tvtime-ext-'.Str::uuid().'.zip');
        file_put_contents($tmpZip, $bytes);

        $zip = new \ZipArchive;
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            $this->addError('extArchive', __('Impossibile aprire l\'archivio ZIP.'));

            return;
        }

        $dir = storage_path('app/tvtime-ext-'.Str::uuid());
        mkdir($dir, 0755, true);

        $hasJson = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = basename((string) $zip->getNameIndex($i));
            if (str_ends_with($entry, '.json') && (str_contains($entry, 'series') || str_contains($entry, 'movies'))) {
                file_put_contents($dir.'/'.$entry, $zip->getFromIndex($i));
                $hasJson = true;
            }
        }
        $zip->close();
        @unlink($tmpZip);

        if (! $hasJson) {
            $this->cleanup($dir);
            $this->addError('extArchive', __('Archivio non valido: mancano i file JSON dell\'estensione (series/movies).'));

            return;
        }

        config(['services.tmdb.token' => (string) Auth::user()->tmdb_token]);
        Artisan::call('import:tvtime-json', ['path' => $dir, '--user' => (int) Auth::id()]);
        Artisan::call('shows:sync');

        $this->cleanup($dir);

        Flux::toast(variant: 'success', text: __('Import dall\'estensione completato.'));
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

                <form wire:submit="import" class="flex flex-col gap-3">
                    <x-dropzone model="archive" accept=".zip"
                        :label="__('Trascina il file .zip qui o clicca per sceglierlo')"
                        :hint="__('Export GDPR di TV Time')"
                        :selected="$archive?->getClientOriginalName()" />
                    <flux:error name="archive" />
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray"
                        wire:target="import" wire:loading.attr="disabled" class="self-start">
                        {{ __('Importa') }}
                    </flux:button>
                </form>

                <div wire:loading wire:target="import" class="flex items-center gap-2 text-sm text-zinc-500">
                    <flux:icon.arrow-path class="size-4 animate-spin" />
                    {{ __('Importazione e sincronizzazione in corso… può richiedere qualche minuto.') }}
                </div>
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

                <div class="flex flex-col gap-3"
                    x-data="{
                        dragging: false,
                        name: null,
                        reading: false,
                        async send(file) {
                            if (! file) return;
                            this.name = file.name;
                            this.reading = true;
                            try {
                                const b64 = await new Promise((res, rej) => {
                                    const r = new FileReader();
                                    r.onload = () => res(r.result);
                                    r.onerror = rej;
                                    r.readAsDataURL(file);
                                });
                                await $wire.importExtension(file.name, b64);
                            } finally {
                                this.reading = false;
                                this.name = null;
                            }
                        },
                    }">
                    <label
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="dragging = false; send($event.dataTransfer.files[0])"
                        :class="dragging ? 'border-accent bg-accent/10' : 'border-zinc-300 hover:border-zinc-400 dark:border-zinc-600 dark:hover:border-zinc-500'"
                        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-8 text-center transition">
                        <input type="file" accept=".zip" class="sr-only"
                            x-on:change="send($event.target.files[0]); $event.target.value = ''" />
                        <div x-show="! reading" class="flex flex-col items-center gap-2">
                            <flux:icon.arrow-up-tray class="size-8 text-zinc-400" />
                            <flux:text class="font-medium">{{ __('Trascina lo .zip dell\'estensione o clicca') }}</flux:text>
                        </div>
                        <div x-show="reading" class="flex items-center gap-2 text-sm text-accent">
                            <flux:icon.arrow-path class="size-5 animate-spin" />
                            <span x-text="name"></span>
                        </div>
                    </label>
                    <flux:error name="extArchive" />
                    <div wire:loading wire:target="importExtension" class="flex items-center gap-2 text-sm text-zinc-500">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Import e sincronizzazione in corso… può richiedere qualche minuto.') }}
                    </div>
                </div>
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

                <form wire:submit="importJson" class="flex flex-col gap-3">
                    <x-dropzone model="jsonFile" accept=".json"
                        :label="__('Trascina il backup .json qui o clicca')"
                        :selected="$jsonFile?->getClientOriginalName()" />
                    <flux:error name="jsonFile" />
                    <flux:button type="submit" variant="outline" icon="arrow-up-tray" class="self-start">
                        {{ __('Importa backup') }}
                    </flux:button>
                </form>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
