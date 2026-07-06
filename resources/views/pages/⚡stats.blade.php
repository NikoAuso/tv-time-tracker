<?php

use App\Services\WatchTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Statistiche')] class extends Component {
    public string $tab = 'series';

    // ---- Serie ----

    #[Computed]
    public function episodesWatched(): int
    {
        return DB::table('watched_episodes')->where('user_id', Auth::id())->count();
    }

    #[Computed]
    public function seriesFollowed(): int
    {
        return DB::table('user_shows')->where('user_id', Auth::id())->where('status', 'following')->count();
    }

    /**
     * @return list<array{value: int, unit: string}>
     */
    #[Computed]
    public function seriesTimeParts(): array
    {
        return WatchTime::humanParts((int) DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->where('watched_episodes.user_id', Auth::id())
            ->sum('episodes.runtime'));
    }

    #[Computed]
    public function seriesFavorites(): int
    {
        return DB::table('user_shows')->where('user_id', Auth::id())->where('is_favorite', true)->count();
    }

    #[Computed]
    public function seriesWatchlist(): int
    {
        return DB::table('user_shows')->where('user_id', Auth::id())->where('status', 'watchlist')->count();
    }

    #[Computed]
    public function seriesAvgRating(): ?float
    {
        $avg = DB::table('user_shows')->where('user_id', Auth::id())->whereNotNull('rating')->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    #[Computed]
    public function perMonth(): array
    {
        $raw = DB::table('watched_episodes')
            ->where('user_id', Auth::id())
            ->whereNotNull('watched_at')
            ->selectRaw("strftime('%Y-%m', watched_at) as ym, count(*) as c")
            ->groupBy('ym')->orderBy('ym')->pluck('c', 'ym');

        if ($raw->isEmpty()) {
            return [];
        }

        $cursor = Carbon::createFromFormat('Y-m', $raw->keys()->first())->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $raw->keys()->last())->startOfMonth();

        $months = [];
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $months[] = ['label' => $cursor->translatedFormat('M y'), 'count' => (int) ($raw[$key] ?? 0)];
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{name: string, c: int}>
     */
    #[Computed]
    public function topShows()
    {
        return DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->join('shows', 'shows.id', '=', 'episodes.show_id')
            ->where('watched_episodes.user_id', Auth::id())
            ->groupBy('shows.id', 'shows.name')
            ->selectRaw('shows.name as name, count(*) as c')
            ->orderByDesc('c')->limit(8)->get();
    }

    public function monthsActive(): int
    {
        return count($this->perMonth);
    }

    // ---- Film ----

    #[Computed]
    public function moviesWatched(): int
    {
        return DB::table('user_movies')->where('user_id', Auth::id())->where('status', 'watched')->count();
    }

    /**
     * @return list<array{value: int, unit: string}>
     */
    #[Computed]
    public function moviesTimeParts(): array
    {
        return WatchTime::humanParts((int) DB::table('user_movies')
            ->join('movies', 'movies.id', '=', 'user_movies.movie_id')
            ->where('user_movies.user_id', Auth::id())
            ->where('user_movies.status', 'watched')
            ->sum('movies.runtime'));
    }

    #[Computed]
    public function moviesWatchlist(): int
    {
        return DB::table('user_movies')->where('user_id', Auth::id())->where('status', 'watchlist')->count();
    }

    #[Computed]
    public function moviesFavorites(): int
    {
        return DB::table('user_movies')->where('user_id', Auth::id())->where('is_favorite', true)->count();
    }

    #[Computed]
    public function moviesAvgRating(): ?float
    {
        $avg = DB::table('user_movies')->where('user_id', Auth::id())->whereNotNull('rating')->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    #[Computed]
    public function moviesAvgRuntime(): int
    {
        return (int) round((float) DB::table('user_movies')
            ->join('movies', 'movies.id', '=', 'user_movies.movie_id')
            ->where('user_movies.user_id', Auth::id())
            ->where('user_movies.status', 'watched')
            ->whereNotNull('movies.runtime')
            ->avg('movies.runtime'));
    }

    /**
     * Film visti per decennio di uscita.
     *
     * @return \Illuminate\Support\Collection<int, object{decade: int, c: int}>
     */
    #[Computed]
    public function moviesByDecade()
    {
        return DB::table('user_movies')
            ->join('movies', 'movies.id', '=', 'user_movies.movie_id')
            ->where('user_movies.user_id', Auth::id())
            ->where('user_movies.status', 'watched')
            ->whereNotNull('movies.release_date')
            ->selectRaw("(cast(strftime('%Y', movies.release_date) as int) / 10) * 10 as decade, count(*) as c")
            ->groupBy('decade')->orderBy('decade')->get();
    }
}; ?>

@php
    $it = fn (int $n) => number_format($n, 0, ',', '.');
    $rate = fn (?float $v) => $v !== null ? number_format($v, 1, ',', '.') : '—';
@endphp

<div class="flex flex-col gap-8">
    <flux:heading size="xl">{{ __('Statistiche') }}</flux:heading>

    <div class="flex gap-2">
        @foreach (['series' => __('Serie'), 'movies' => __('Film')] as $key => $label)
            <flux:button size="sm" wire:click="$set('tab', '{{ $key }}')"
                :variant="$tab === $key ? 'primary' : 'outline'">{{ $label }}</flux:button>
        @endforeach
    </div>

    @if ($tab === 'series')
        @php
            $months = $this->perMonth;
            $maxMonth = collect($months)->max('count') ?: 1;
            $avg = $this->monthsActive() > 0 ? intdiv($this->episodesWatched, $this->monthsActive()) : 0;
            $maxShow = $this->topShows->max('c') ?: 1;
        @endphp

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
            @include('partials.stat-time-card', ['parts' => $this->seriesTimeParts])
            @foreach ([
                ['Episodi visti', $it($this->episodesWatched)],
                ['Serie seguite', $it($this->seriesFollowed)],
                ['Da vedere', $it($this->seriesWatchlist)],
                ['Preferite', $it($this->seriesFavorites)],
                ['Voto medio', $rate($this->seriesAvgRating)],
            ] as [$label, $value])
                <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text size="sm" class="text-zinc-500">{{ __($label) }}</flux:text>
                    <flux:heading size="xl" class="tabular-nums">{{ $value }}</flux:heading>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Episodi registrati per mese') }}</flux:heading>
            <div class="overflow-x-auto">
                <div class="flex h-48 min-w-[520px] items-end gap-1">
                    @foreach ($months as $m)
                        <div class="flex flex-1 flex-col items-center gap-1" title="{{ $m['label'] }}: {{ $it($m['count']) }}">
                            <div class="w-full rounded-t bg-accent transition-all"
                                style="height: {{ max(2, (int) round($m['count'] / $maxMonth * 160)) }}px"></div>
                            <flux:text class="rotate-45 whitespace-nowrap text-[10px] text-zinc-400">{{ $m['label'] }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </div>
            <flux:text size="sm" class="text-zinc-500">
                {{ __('Basato sulla data di registrazione dell\'episodio: il picco iniziale è la cronologia importata al primo accesso.') }}
            </flux:text>
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Serie più viste') }}</flux:heading>
            <div class="flex flex-col gap-2">
                @foreach ($this->topShows as $i => $show)
                    <div class="flex items-center gap-3">
                        <flux:text class="w-6 text-right tabular-nums text-zinc-400">{{ $i + 1 }}</flux:text>
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex justify-between gap-2">
                                <flux:text class="truncate">{{ $show->name }}</flux:text>
                                <flux:text class="tabular-nums text-zinc-500">{{ $it((int) $show->c) }}</flux:text>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-accent" style="width: {{ round($show->c / $maxShow * 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        @php $maxDecade = $this->moviesByDecade->max('c') ?: 1; @endphp

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
            @include('partials.stat-time-card', ['parts' => $this->moviesTimeParts])
            @foreach ([
                ['Film visti', $it($this->moviesWatched)],
                ['Da vedere', $it($this->moviesWatchlist)],
                ['Preferiti', $it($this->moviesFavorites)],
                ['Voto medio', $rate($this->moviesAvgRating)],
                ['Durata media', $this->moviesAvgRuntime.' min'],
            ] as [$label, $value])
                <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text size="sm" class="text-zinc-500">{{ __($label) }}</flux:text>
                    <flux:heading size="xl" class="tabular-nums">{{ $value }}</flux:heading>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Film visti per decennio') }}</flux:heading>
            @if ($this->moviesByDecade->isEmpty())
                <flux:text class="text-zinc-500">{{ __('Ancora nessun film visto.') }}</flux:text>
            @else
                <div class="flex flex-col gap-2">
                    @foreach ($this->moviesByDecade as $row)
                        <div class="flex items-center gap-3">
                            <flux:text class="w-14 shrink-0 tabular-nums text-zinc-500">{{ __('Anni') }} {{ $row->decade }}</flux:text>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-accent" style="width: {{ round($row->c / $maxDecade * 100) }}%"></div>
                            </div>
                            <flux:text class="w-8 shrink-0 text-right tabular-nums text-zinc-500">{{ $it((int) $row->c) }}</flux:text>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
