@props(['model', 'accept' => '', 'label' => '', 'hint' => null, 'selected' => null])

<div x-data="{ dragging: false }"
    x-on:dragover.prevent="dragging = true"
    x-on:dragleave.prevent="dragging = false"
    x-on:drop.prevent="
        dragging = false;
        $refs.input.files = $event.dataTransfer.files;
        $refs.input.dispatchEvent(new Event('change', { bubbles: true }));
    ">
    <label
        :class="dragging
            ? 'border-accent bg-accent/10'
            : 'border-zinc-300 hover:border-zinc-400 dark:border-zinc-600 dark:hover:border-zinc-500'"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-8 text-center transition">
        <input type="file" wire:model="{{ $model }}" @if ($accept) accept="{{ $accept }}" @endif
            x-ref="input" class="sr-only" />

        <div wire:loading.remove wire:target="{{ $model }}" class="flex flex-col items-center gap-2">
            @if ($selected)
                <flux:icon.document-check class="size-8 text-green-600 dark:text-green-500" />
                <flux:text class="font-medium break-all">{{ $selected }}</flux:text>
                <flux:text size="sm" class="text-zinc-500">{{ __('Clicca o trascina per cambiare') }}</flux:text>
            @else
                <flux:icon.arrow-up-tray class="size-8 text-zinc-400" />
                <flux:text class="font-medium">{{ $label }}</flux:text>
                @if ($hint)
                    <flux:text size="sm" class="text-zinc-500">{{ $hint }}</flux:text>
                @endif
            @endif
        </div>

        <div wire:loading wire:target="{{ $model }}" class="flex items-center gap-2 text-sm text-accent">
            <flux:icon.arrow-path class="size-5 animate-spin" />
            {{ __('Caricamento…') }}
        </div>
    </label>
</div>
