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

        if (filled(config('services.tmdb.token'))) {
            Artisan::call('shows:sync');
            Artisan::call('movies:sync');
            Flux::toast(variant: 'success', text: __('Import e sincronizzazione TMDB completati.'));
        } else {
            Flux::toast(variant: 'success', text: __('Import completato. Aggiungi TMDB_TOKEN per poster e trame.'));
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
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Import')" :subheading="__('Importa i tuoi dati dall\'export di TV Time')">
        <div class="my-6 flex w-full max-w-md flex-col gap-6">
            <flux:text class="text-zinc-500">
                {{ __('Carica il file .zip dell\'export GDPR di TV Time. Verranno importati serie, episodi visti e film; per poster e trame lancia poi la sincronizzazione TMDB.') }}
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
