<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Impostazioni') }}
            </flux:menu.item>
            @if (auth()->user()->hasPin())
                <form method="POST" action="{{ route('lock') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="lock-closed" class="w-full cursor-pointer">
                        {{ __('Blocca') }}
                    </flux:menu.item>
                </form>
            @endif
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
