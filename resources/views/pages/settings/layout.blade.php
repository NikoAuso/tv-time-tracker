<div class="flex w-full flex-col gap-2">
    <flux:button :href="route('profile.edit')" wire:navigate variant="outline" size="sm" icon="arrow-left" class="self-start">
        {{ __('Profilo') }}
    </flux:button>

    <div class="mt-2">
        <flux:heading size="xl">{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
