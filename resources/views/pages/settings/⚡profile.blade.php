<?php

use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profilo')] class extends Component {
    public string $name = '';

    public bool $editingName = false;

    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    public function saveName(): void
    {
        $validated = $this->validate(['name' => ['required', 'string', 'max:255']]);

        $user = Auth::user();
        $user->name = $validated['name'];
        $user->save();

        $this->editingName = false;

        Flux::toast(variant: 'success', text: __('Profilo aggiornato.'));
    }

    #[Computed]
    public function episodes(): int
    {
        return DB::table('watched_episodes')->where('user_id', Auth::id())->count();
    }

    #[Computed]
    public function movies(): int
    {
        return DB::table('user_movies')->where('user_id', Auth::id())->where('status', 'watched')->count();
    }

    #[Computed]
    public function hours(): int
    {
        $episodeMinutes = (int) DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->where('watched_episodes.user_id', Auth::id())
            ->sum('episodes.runtime');

        $movieMinutes = (int) DB::table('user_movies')
            ->join('movies', 'movies.id', '=', 'user_movies.movie_id')
            ->where('user_movies.user_id', Auth::id())
            ->where('user_movies.status', 'watched')
            ->sum('movies.runtime');

        return intdiv($episodeMinutes + $movieMinutes, 60);
    }

    #[Computed]
    public function memberSince(): ?string
    {
        $first = DB::table('watched_episodes')
            ->where('user_id', Auth::id())
            ->whereNotNull('watched_at')
            ->min('watched_at');

        return $first ? Carbon::parse($first)->locale('it')->isoFormat('MMMM YYYY') : null;
    }
}; ?>

<section class="w-full">
    @php $it = fn (int $n) => number_format($n, 0, ',', '.'); @endphp

    <div class="my-6 flex w-full max-w-md flex-col gap-8">
        {{-- Intestazione profilo --}}
        <div class="flex items-center gap-4">
            <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-accent-content text-accent-foreground">
                <x-app-logo-icon class="size-8 text-white dark:text-black" />
            </div>

            <div class="min-w-0 flex-1">
                @if ($editingName)
                    <form wire:submit="saveName" class="flex items-center gap-2">
                        <flux:input wire:model="name" class="flex-1" autofocus />
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Salva') }}</flux:button>
                    </form>
                    <flux:error name="name" />
                @else
                    <flux:heading size="lg" class="truncate">{{ $name }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ $this->memberSince ? __('Dal :date', ['date' => $this->memberSince]) : __('Profilo locale') }}
                    </flux:text>
                @endif
            </div>

            @unless ($editingName)
                <flux:button variant="ghost" size="sm" icon="pencil-square"
                    wire:click="$set('editingName', true)" aria-label="{{ __('Modifica nome') }}" />
            @endunless
        </div>

        {{-- Riepilogo --}}
        <div class="grid grid-cols-3 divide-x divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ([[__('Episodi'), $this->episodes], [__('Film'), $this->movies], [__('Ore'), $this->hours]] as [$label, $value])
                <div class="flex flex-col items-center gap-1 p-3">
                    <flux:heading class="tabular-nums">{{ $it($value) }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">{{ $label }}</flux:text>
                </div>
            @endforeach
        </div>

        {{-- Gestione --}}
        <div class="flex flex-col gap-2">
            <flux:text class="px-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('Gestione') }}</flux:text>

            <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                <a href="{{ route('pin.edit') }}" wire:navigate class="flex items-center gap-3 p-4 no-underline">
                    <flux:icon.lock-closed class="size-5 shrink-0 text-zinc-500" />
                    <flux:text class="flex-1 font-medium">{{ __('Blocco con PIN') }}</flux:text>
                    @if (Auth::user()->hasPin())
                        <flux:badge size="sm" color="green">{{ __('Attivo') }}</flux:badge>
                    @else
                        <flux:text size="sm" class="text-zinc-400">{{ __('Off') }}</flux:text>
                    @endif
                    <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-300" />
                </a>

                <a href="{{ route('import.edit') }}" wire:navigate class="flex items-center gap-3 p-4 no-underline">
                    <flux:icon.arrow-up-tray class="size-5 shrink-0 text-zinc-500" />
                    <div class="flex min-w-0 flex-1 flex-col">
                        <flux:text class="font-medium">{{ __('Importa dati') }}</flux:text>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Da export TV Time') }}</flux:text>
                    </div>
                    <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-300" />
                </a>

                <a href="{{ route('appearance.edit') }}" wire:navigate class="flex items-center gap-3 p-4 no-underline">
                    <flux:icon.swatch class="size-5 shrink-0 text-zinc-500" />
                    <div class="flex min-w-0 flex-1 flex-col">
                        <flux:text class="font-medium">{{ __('Aspetto') }}</flux:text>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Chiaro · Scuro · Sistema') }}</flux:text>
                    </div>
                    <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-300" />
                </a>
            </div>
        </div>
    </div>
</section>
