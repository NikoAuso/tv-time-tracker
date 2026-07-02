<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="max-lg:pb-24">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
