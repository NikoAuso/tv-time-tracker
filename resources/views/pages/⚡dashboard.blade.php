<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Da guardare')] class extends Component {
    public string $view = 'list';

    public string $tab = 'series';

    /** @var list<string> */
    public array $platformFilter = [];

    /** @return \Illuminate\Support\Collection<int, int> */
    private function followedShowIds()
    {
        return UserShow::where('user_id', Auth::id())
            ->where('status', 'following')
            ->pluck('show_id');
    }

    public function removePlatform(string $name): void
    {
        $this->platformFilter = array_values(array_filter($this->platformFilter, fn (string $p): bool => $p !== $name));
    }

    private function matchesPlatform(Movie|Show $model): bool
    {
        if ($this->platformFilter === []) {
            return true;
        }

        foreach ($model->providers['flatrate'] ?? [] as $p) {
            if (in_array($p['name'], $this->platformFilter, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Piattaforme presenti tra serie seguite e film da vedere, per il filtro.
     *
     * @return array<string, string|null>  name => logo_path
     */
    #[Computed]
    public function platforms(): array
    {
        $userId = Auth::id();
        $out = [];

        $collect = function ($models) use (&$out): void {
            foreach ($models as $model) {
                foreach ($model->providers['flatrate'] ?? [] as $p) {
                    $out[$p['name']] ??= $p['logo_path'] ?? null;
                }
            }
        };

        $collect(Show::whereIn('id', $this->followedShowIds())->whereNotNull('providers')->get(['id', 'providers']));
        $collect(Movie::whereIn('id', UserMovie::where('user_id', $userId)->where('status', 'watchlist')->select('movie_id'))->whereNotNull('providers')->get(['id', 'providers']));

        uksort($out, fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return $out;
    }

    /**
     * Primo episodio non visto (già uscito) per ogni serie seguita.
     *
     * @return \Illuminate\Support\Collection<int, Episode>
     */
    #[Computed]
    public function upNext()
    {
        $userId = Auth::id();

        // NOT EXISTS invece di whereNotIn(watchedIds): evita di caricare e bindare
        // migliaia di id (oltre il limite di variabili di SQLite) a ogni render.
        return Episode::query()
            ->with('show')
            ->whereIn('show_id', $this->followedShowIds())
            ->where('season_number', '>=', 1)
            ->whereDoesntHave('watches', fn ($q) => $q->where('user_id', $userId))
            ->where(fn ($q) => $q->whereNull('air_date')->orWhereDate('air_date', '<=', now()))
            ->orderBy('show_id')
            ->orderBy('season_number')
            ->orderBy('episode_number')
            ->get()
            ->groupBy('show_id')
            ->map->first()
            ->filter(fn (Episode $e) => $this->matchesPlatform($e->show))
            ->sortBy(fn (Episode $e) => $e->show->name)
            ->values();
    }

    /**
     * Prossime uscite: episodi futuri delle serie seguite, raggruppati per data.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Episode>>
     */
    #[Computed]
    public function upcoming()
    {
        return Episode::query()
            ->with('show')
            ->whereIn('show_id', $this->followedShowIds())
            ->whereDate('air_date', '>=', today())
            ->orderBy('air_date')
            ->orderBy('show_id')
            ->orderBy('season_number')
            ->orderBy('episode_number')
            ->limit(200)
            ->get()
            ->filter(fn (Episode $e) => $this->matchesPlatform($e->show))
            ->groupBy(fn (Episode $e) => $e->air_date->toDateString());
    }

    /**
     * Film in watchlist ("Da vedere"), filtrati per piattaforma.
     *
     * @return \Illuminate\Support\Collection<int, UserMovie>
     */
    #[Computed]
    public function watchlistMovies()
    {
        return UserMovie::query()
            ->with('movie')
            ->where('user_id', Auth::id())
            ->where('status', 'watchlist')
            ->get()
            ->filter(fn (UserMovie $um) => $this->matchesPlatform($um->movie))
            ->sortBy(fn (UserMovie $um) => $um->movie->title, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Film in watchlist raggruppati per anno di uscita (vista "calendario").
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, UserMovie>>
     */
    #[Computed]
    public function moviesByYear()
    {
        return $this->watchlistMovies
            ->groupBy(fn (UserMovie $um) => $um->movie->release_date?->format('Y') ?: '—')
            ->sortKeysDesc();
    }

    public function markWatched(int $episodeId): void
    {
        WatchedEpisode::firstOrCreate(
            ['user_id' => Auth::id(), 'episode_id' => $episodeId],
            ['watched_at' => now()],
        );

        unset($this->upNext);
    }
}; ?>

<div class="flex flex-col gap-6">
    @php
        $hasContent = $tab === 'movies'
            ? ! $this->watchlistMovies->isEmpty()
            : (! $this->upNext->isEmpty() || ! $this->upcoming->isEmpty());
    @endphp

    <div class="flex flex-col gap-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-0.5">
                <flux:heading size="xl">
                    @if ($tab === 'movies')
                        {{ __('Film da vedere') }}
                    @else
                        {{ $view === 'calendar' ? __('Episodi in arrivo') : __('Serie da vedere') }}
                    @endif
                </flux:heading>
                <flux:text size="sm" class="text-zinc-500">
                    @if ($tab === 'movies')
                        {{ $this->watchlistMovies->count() }} {{ __('film da vedere') }}
                    @elseif ($view === 'calendar')
                        {{ $this->upcoming->collapse()->count() }} {{ __('in programma') }}
                    @else
                        {{ $this->upNext->count() }} {{ __('serie in corso') }}
                    @endif
                </flux:text>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                @if ($hasContent)
                    <flux:button size="sm" icon="list-bullet" wire:click="$set('view', 'list')"
                        :variant="$view === 'list' ? 'primary' : 'outline'" aria-label="{{ __('Lista') }}" />
                    <flux:button size="sm" icon="squares-2x2" wire:click="$set('view', 'grid')"
                        :variant="$view === 'grid' ? 'primary' : 'outline'" aria-label="{{ __('Griglia') }}" />
                    <flux:button size="sm" icon="calendar-days" wire:click="$set('view', 'calendar')"
                        :variant="$view === 'calendar' ? 'primary' : 'outline'" aria-label="{{ __('Calendario') }}" />
                @endif
                @if (! empty($this->platforms))
                    @include('partials.platform-filter', ['platforms' => $this->platforms])
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex gap-2">
                @foreach (['series' => 'Serie', 'movies' => 'Film'] as $key => $label)
                    <flux:button size="sm" wire:click="$set('tab', '{{ $key }}')"
                        :variant="$tab === $key ? 'primary' : 'outline'">{{ __($label) }}</flux:button>
                @endforeach
            </div>
        </div>

        @include('partials.platform-badges')
    </div>

    @if ($tab === 'movies')
        @if ($this->watchlistMovies->isEmpty())
            <div class="flex flex-col items-center gap-2 py-16 text-center">
                <flux:icon.film class="size-10 text-zinc-400" />
                <flux:heading size="lg">{{ __('Nessun film da vedere') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Aggiungi film dalla ricerca per ritrovarli qui.') }}</flux:text>
            </div>
        @elseif ($view === 'grid')
            <div class="grid grid-cols-3 gap-4 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($this->watchlistMovies as $um)
                    @php $item = ['type' => 'movie', 'title' => $um->movie->title, 'poster' => $um->movie->poster_path, 'meta' => $um->movie->release_date?->year ? (string) $um->movie->release_date->year : __('Film')]; @endphp
                    <flux:link :href="route('movies.show', $um->movie)" wire:navigate class="group flex flex-col gap-2 no-underline">
                        @include('partials.library-card', ['item' => $item])
                    </flux:link>
                @endforeach
            </div>
        @elseif ($view === 'calendar')
            <div class="flex flex-col gap-6">
                @foreach ($this->moviesByYear as $year => $movies)
                    <div class="flex flex-col gap-3">
                        <flux:heading size="sm" class="text-zinc-500">{{ $year === '—' ? __('Senza data') : $year }}</flux:heading>
                        <div class="flex flex-col gap-2">
                            @foreach ($movies as $um)
                                @include('partials.movie-row', ['movie' => $um->movie])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($this->watchlistMovies as $um)
                    @include('partials.movie-row', ['movie' => $um->movie])
                @endforeach
            </div>
        @endif
    @elseif ($view === 'calendar')
        @if ($this->upcoming->isEmpty())
            <div class="flex flex-col items-center gap-2 py-16 text-center">
                <flux:icon.calendar-days class="size-10 text-zinc-400" />
                <flux:heading size="lg">{{ __('Nessuna uscita in programma') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Non ci sono episodi futuri nelle serie che segui.') }}</flux:text>
            </div>
        @else
            <div class="flex flex-col gap-6">
                @foreach ($this->upcoming as $episodes)
                    <div class="flex flex-col gap-3">
                        <flux:heading size="sm" class="text-zinc-500">
                            {{ \Illuminate\Support\Str::ucfirst($episodes->first()->air_date->locale('it')->isoFormat('dddd D MMMM')) }}
                        </flux:heading>
                        <div class="flex flex-col gap-2">
                            @foreach ($episodes as $episode)
                                <a href="{{ route('episodes.show', $episode) }}" wire:navigate
                                    class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 no-underline transition hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
                                    @include('partials.poster', ['poster' => $episode->show->poster_path, 'title' => $episode->show->name, 'ratio' => 'h-24 w-16 shrink-0', 'size' => 'w185'])
                                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <flux:heading size="sm" class="truncate">{{ $episode->show->name }}</flux:heading>
                                        <flux:text size="sm" class="tabular-nums text-zinc-500">
                                            S{{ $episode->season_number }}E{{ $episode->episode_number }}{{ $episode->name ? ' · '.$episode->name : '' }}
                                        </flux:text>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @elseif ($this->upNext->isEmpty())
        <div class="flex flex-col items-center gap-2 py-16 text-center">
            <flux:icon.check-badge class="size-10 text-green-500" />
            <flux:heading size="lg">{{ __('Sei in pari!') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Nessun episodio in sospeso nelle serie che segui.') }}</flux:text>
        </div>
    @elseif ($view === 'grid')
        <div class="grid grid-cols-3 gap-4 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            @foreach ($this->upNext as $episode)
                <div class="flex flex-col gap-2">
                    <div class="relative">
                        <a href="{{ route('episodes.show', $episode) }}" wire:navigate class="block">
                            @include('partials.poster', ['poster' => $episode->show->poster_path, 'title' => $episode->show->name])
                            <span class="absolute left-1.5 top-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-white">
                                S{{ $episode->season_number }}E{{ $episode->episode_number }}
                            </span>
                        </a>
                        <div class="absolute right-1.5 top-1.5">
                            <flux:button wire:loading.remove wire:target="markWatched({{ $episode->id }})"
                                size="xs" variant="primary" icon="check"
                                wire:click="markWatched({{ $episode->id }})" aria-label="{{ __('Segna visto') }}" />
                            <flux:button wire:loading wire:target="markWatched({{ $episode->id }})"
                                size="xs" variant="primary" color="green" icon="check" aria-label="{{ __('Visto') }}" />
                        </div>
                    </div>
                    <div class="min-w-0">
                        <flux:text size="sm" class="truncate font-medium">{{ $episode->show->name }}</flux:text>
                        <flux:text size="sm" class="truncate text-zinc-500">{{ $episode->name ?: __('Episodio') }}</flux:text>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->upNext as $episode)
                <div class="flex gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
                    <a href="{{ route('episodes.show', $episode) }}" wire:navigate class="flex min-w-0 flex-1 gap-3">
                        @include('partials.poster', ['poster' => $episode->show->poster_path, 'title' => $episode->show->name, 'ratio' => 'h-24 w-16 shrink-0', 'size' => 'w185'])
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <flux:heading size="sm" class="truncate">{{ $episode->show->name }}</flux:heading>
                            <flux:text size="sm" class="tabular-nums text-zinc-500">
                                S{{ $episode->season_number }}E{{ $episode->episode_number }}
                            </flux:text>
                            <flux:text size="sm" class="line-clamp-2">{{ $episode->name ?: __('Episodio') }}</flux:text>
                        </div>
                    </a>
                    <div class="flex shrink-0 items-center">
                        <flux:button wire:loading.remove wire:target="markWatched({{ $episode->id }})"
                            size="sm" variant="primary" icon="check"
                            wire:click="markWatched({{ $episode->id }})" aria-label="{{ __('Segna visto') }}" />
                        <flux:button wire:loading wire:target="markWatched({{ $episode->id }})"
                            size="sm" variant="primary" color="green" icon="check" aria-label="{{ __('Visto') }}" />
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
