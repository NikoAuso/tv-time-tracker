<?php

use App\Models\UserList;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Services\WatchTime;
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

    public string $newListName = '';

    public bool $creatingList = false;

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

    public function createList(): void
    {
        $validated = $this->validate(
            ['newListName' => ['required', 'string', 'max:60']],
            ['newListName.required' => __('Dai un nome alla lista.')],
        );

        UserList::firstOrCreate([
            'user_id' => Auth::id(),
            'name' => trim($validated['newListName']),
        ]);

        $this->reset('newListName', 'creatingList');
        unset($this->lists);
    }

    /**
     * @return list<array{value: int, unit: string}>
     */
    #[Computed]
    public function seriesParts(): array
    {
        return WatchTime::humanParts((int) DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->where('watched_episodes.user_id', Auth::id())
            ->sum('episodes.runtime'));
    }

    /**
     * @return list<array{value: int, unit: string}>
     */
    #[Computed]
    public function movieParts(): array
    {
        return WatchTime::humanParts((int) DB::table('user_movies')
            ->join('movies', 'movies.id', '=', 'user_movies.movie_id')
            ->where('user_movies.user_id', Auth::id())
            ->where('user_movies.status', 'watched')
            ->sum('movies.runtime'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, UserList>
     */
    #[Computed]
    public function lists()
    {
        return UserList::where('user_id', Auth::id())
            ->withCount(['shows', 'movies'])
            ->latest('id')
            ->take(5)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, UserShow>
     */
    #[Computed]
    public function favoriteShows()
    {
        return UserShow::where('user_id', Auth::id())->where('is_favorite', true)
            ->with('show')->latest('id')->take(20)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, UserMovie>
     */
    #[Computed]
    public function favoriteMovies()
    {
        return UserMovie::where('user_id', Auth::id())->where('is_favorite', true)
            ->with('movie')->latest('id')->take(20)->get();
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
                        <flux:button type="submit" wire:target="saveName" wire:loading.attr="disabled" variant="primary" size="sm">{{ __('Salva') }}</flux:button>
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
                <flux:button variant="outline" size="sm" icon="pencil-square"
                    wire:click="$set('editingName', true)" aria-label="{{ __('Modifica nome') }}" />
            @endunless
        </div>

        {{-- Riepilogo tempo --}}
        <div class="grid grid-cols-2 divide-x divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ([[__('Tempo serie'), $this->seriesParts], [__('Tempo film'), $this->movieParts]] as [$label, $parts])
                <div class="flex flex-col items-center gap-3 p-4 text-center">
                    <flux:text size="sm" class="text-zinc-500">{{ $label }}</flux:text>
                    <div class="flex items-start justify-center gap-3">
                        @foreach ($parts as $part)
                            <div class="flex flex-col items-center">
                                <span class="text-xl font-bold leading-none tabular-nums">{{ $part['value'] }}</span>
                                <span class="mt-1 text-xs text-zinc-500">{{ $part['unit'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Preferiti --}}
        <div class="flex flex-col gap-4">
            <flux:text class="px-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('Preferiti') }}</flux:text>

            @if ($this->favoriteShows->isEmpty() && $this->favoriteMovies->isEmpty())
                <flux:text size="sm" class="px-1 text-zinc-500">{{ __('Segna serie e film col cuore per vederli qui.') }}</flux:text>
            @else
                @foreach ([
                    [__('Serie'), $this->favoriteShows, 'show', 'shows.show'],
                    [__('Film'), $this->favoriteMovies, 'movie', 'movies.show'],
                ] as [$label, $favorites, $rel, $routeName])
                    @if ($favorites->isNotEmpty())
                        <div class="flex flex-col gap-2">
                            <flux:text size="sm" class="px-1 text-zinc-500">{{ $label }}</flux:text>
                            <div class="flex gap-3 overflow-x-auto pb-1">
                                @foreach ($favorites as $fav)
                                    @php $item = $fav->{$rel}; @endphp
                                    <a href="{{ route($routeName, $item) }}" wire:navigate class="w-16 shrink-0">
                                        @include('partials.poster', [
                                            'poster' => $item->poster_path,
                                            'title' => $item->name ?? $item->title,
                                            'size' => 'w185',
                                        ])
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>

        {{-- Liste --}}
        <div class="flex flex-col gap-2">
            <flux:text class="px-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('Liste') }}</flux:text>

            @if ($this->lists->isNotEmpty())
                <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                    @foreach ($this->lists as $list)
                        <a href="{{ route('lists.show', $list) }}" wire:navigate class="flex items-center gap-3 p-4 no-underline">
                            <flux:icon.list-bullet class="size-5 shrink-0 text-zinc-500" />
                            <flux:text class="flex-1 truncate font-medium">{{ $list->name }}</flux:text>
                            <flux:text size="sm" class="shrink-0 text-zinc-500">
                                {{ $list->shows_count + $list->movies_count }} {{ __('elementi') }}
                            </flux:text>
                            <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-300" />
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($creatingList)
                <form wire:submit="createList" class="flex items-start gap-2">
                    <div class="flex flex-1 flex-col gap-1">
                        <flux:input wire:model="newListName" placeholder="{{ __('Nome della lista') }}" autofocus />
                        <flux:error name="newListName" />
                    </div>
                    <flux:button type="submit" variant="primary" icon="check" aria-label="{{ __('Crea') }}" />
                    <flux:button type="button" variant="outline" icon="x-mark"
                        wire:click="$set('creatingList', false)" aria-label="{{ __('Annulla') }}" />
                </form>
            @elseif ($this->lists->isEmpty())
                <flux:button variant="primary" class="w-full" icon="plus" wire:click="$set('creatingList', true)">
                    {{ __('Crea una lista') }}
                </flux:button>
            @else
                <flux:button variant="outline" size="sm" icon="plus" class="self-start" wire:click="$set('creatingList', true)">
                    {{ __('Nuova lista') }}
                </flux:button>
            @endif
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
