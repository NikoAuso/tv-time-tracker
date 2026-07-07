@props(['action', 'accept' => '', 'label' => '', 'hint' => null])

{{--
    Dropzone che invia il file come base64 (data-URL) via wire:call al metodo
    $action, invece dell'upload-file classico: il PHP embarcato di NativePHP
    limita upload_max_filesize a 2MB, mentre i dati POST sono vincolati dal ben
    più ampio post_max_size. Il metodo riceve (string $name, string $data).
--}}
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
                await $wire.{{ $action }}(file.name, b64);
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
        <input type="file" @if ($accept) accept="{{ $accept }}" @endif class="sr-only"
            x-on:change="send($event.target.files[0]); $event.target.value = ''" />

        <div x-show="! reading" class="flex flex-col items-center gap-2">
            <flux:icon.arrow-up-tray class="size-8 text-zinc-400" />
            <flux:text class="font-medium">{{ $label }}</flux:text>
            @if ($hint)
                <flux:text size="sm" class="text-zinc-500">{{ $hint }}</flux:text>
            @endif
        </div>
        <div x-show="reading" class="flex items-center gap-2 text-sm text-accent">
            <flux:icon.arrow-path class="size-5 animate-spin" />
            <span class="break-all" x-text="name"></span>
        </div>
    </label>

    <div wire:loading wire:target="{{ $action }}" class="flex items-center gap-2 text-sm text-zinc-500">
        <flux:icon.arrow-path class="size-4 animate-spin" />
        {{ __('Elaborazione in corso… può richiedere qualche minuto.') }}
    </div>
</div>
