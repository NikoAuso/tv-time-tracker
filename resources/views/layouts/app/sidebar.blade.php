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
                <flux:sidebar.item icon="film" :href="route('library')" :current="request()->routeIs('library') || request()->routeIs('shows.*') || request()->routeIs('movies.*') || request()->routeIs('episodes.*')" wire:navigate>
                    {{ __('Libreria') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="magnifying-glass" :href="route('search')" :current="request()->routeIs('search')" wire:navigate>
                    {{ __('Cerca') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="chart-bar" :href="route('stats')" :current="request()->routeIs('stats')" wire:navigate>
                    {{ __('Statistiche') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="user" :href="route('profile.edit')" :current="request()->is('settings*')" wire:navigate>
                    {{ __('Profilo') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu :name="auth()->user()->name" />
        </flux:sidebar>

        {{-- Top bar mobile --}}
        <flux:header class="pt-[env(safe-area-inset-top)] lg:hidden">
            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:spacer />

            <div class="flex items-center gap-1">
                <x-appearance-toggle />

                @if (auth()->user()->hasPin())
                    <form method="POST" action="{{ route('lock') }}">
                        @csrf
                        <flux:button type="submit" variant="outline" size="sm" icon="lock-closed" aria-label="{{ __('Blocca') }}" />
                    </form>
                @endif
            </div>
        </flux:header>

        {{ $slot }}

        {{-- Bottom tab bar mobile --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 pb-[max(env(safe-area-inset-bottom),_1.25rem)] backdrop-blur lg:hidden dark:border-zinc-700 dark:bg-zinc-900/95">
            @php
                $tabs = [
                    ['dashboard', 'play', 'Da guardare', request()->routeIs('dashboard')],
                    ['library', 'film', 'Libreria', request()->routeIs('library') || request()->routeIs('shows.*') || request()->routeIs('movies.*') || request()->routeIs('episodes.*')],
                    ['search', 'magnifying-glass', 'Cerca', request()->routeIs('search')],
                    ['stats', 'chart-bar', 'Statistiche', request()->routeIs('stats')],
                    ['profile.edit', 'user', 'Profilo', request()->is('settings*')],
                ];
            @endphp
            <div class="grid h-14 grid-cols-5 items-center">
                @foreach ($tabs as [$route, $icon, $label, $active])
                    <a href="{{ route($route) }}" wire:navigate
                        class="flex w-full flex-col items-center justify-center gap-1 text-center text-[11px] no-underline {{ $active ? 'font-semibold text-accent' : 'text-zinc-400 dark:text-zinc-500' }}">
                        <x-dynamic-component :component="'flux::icon.'.$icon" :variant="$active ? 'solid' : 'outline'" class="size-6" />
                        <span>{{ __($label) }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
