<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 max-lg:hidden">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="play" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Da guardare') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="film" :href="route('library')" :current="request()->routeIs('library') || request()->routeIs('shows.*') || request()->routeIs('episodes.*')" wire:navigate>
                    {{ __('Libreria') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="chart-bar" :href="route('stats')" :current="request()->routeIs('stats')" wire:navigate>
                    {{ __('Statistiche') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->is('settings*')" wire:navigate>
                    {{ __('Impostazioni') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu :name="auth()->user()->name" />
        </flux:sidebar>

        {{-- Top bar mobile --}}
        <flux:header class="lg:hidden">
            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:spacer />

            @if (auth()->user()->hasPin())
                <form method="POST" action="{{ route('lock') }}">
                    @csrf
                    <flux:button type="submit" variant="ghost" size="sm" icon="lock-closed" aria-label="{{ __('Blocca') }}" />
                </form>
            @endif
        </flux:header>

        {{ $slot }}

        {{-- Bottom tab bar mobile --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 flex border-t border-zinc-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden dark:border-zinc-700 dark:bg-zinc-900/95">
            @php
                $tabs = [
                    ['dashboard', 'play', 'Da guardare', request()->routeIs('dashboard')],
                    ['library', 'film', 'Libreria', request()->routeIs('library') || request()->routeIs('shows.*') || request()->routeIs('episodes.*')],
                    ['stats', 'chart-bar', 'Statistiche', request()->routeIs('stats')],
                    ['profile.edit', 'cog', 'Impostazioni', request()->is('settings*')],
                ];
            @endphp
            @foreach ($tabs as [$route, $icon, $label, $active])
                <flux:link :href="route($route)" wire:navigate
                    class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] no-underline {{ $active ? 'text-accent' : 'text-zinc-500 dark:text-zinc-400' }}">
                    <x-dynamic-component :component="'flux::icon.'.$icon" :variant="$active ? 'solid' : 'outline'" class="size-6" />
                    <span>{{ __($label) }}</span>
                </flux:link>
            @endforeach
        </nav>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
