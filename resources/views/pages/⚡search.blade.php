<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Services\Tmdb;
use App\Services\TmdbLibrary;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cerca')] class extends Component {
    public string $q = '';

    public string $type = 'series';

    public string $view = 'list';

    public string $browse = '';

    public function updatedQ(): void
    {
        $this->browse = '';
    }

    private function token(): string
    {
        return Auth::user()->tmdb_token ?: (string) config('services.tmdb.token');
    }

    public function hasToken(): bool
    {
        return filled($this->token());
    }

    /**
     * Risultati TMDB con flag "in libreria".
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function results(): array
    {
        $q = trim($this->q);
        if (mb_strlen($q) < 2 || ! $this->hasToken()) {
            return [];
        }

        $tmdb = new Tmdb($this->token());
        $movies = $this->type === 'movies';

        return $this->mapItems($movies ? $tmdb->searchMovies($q) : $tmdb->searchShows($q), $movies);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function trendingShows(): array
    {
        return $this->hasToken() ? $this->mapItems((new Tmdb($this->token()))->trendingShows(), false) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function trendingMovies(): array
    {
        return $this->hasToken() ? $this->mapItems((new Tmdb($this->token()))->trendingMovies(), true) : [];
    }

    /**
     * Normalizza i risultati TMDB e vi aggancia il flag "in libreria".
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function mapItems(array $raw, bool $movies): array
    {
        $inLibrary = $this->inLibrary($movies, collect($raw)->pluck('id')->all());

        return collect($raw)->map(function (array $r) use ($movies, $inLibrary) {
            $localId = $inLibrary[$r['id']] ?? null;
            $date = (string) ($movies ? ($r['release_date'] ?? '') : ($r['first_air_date'] ?? ''));

            return [
                'tmdb_id' => (int) $r['id'],
                'title' => $movies ? ($r['title'] ?? '') : ($r['name'] ?? ''),
                'year' => $date !== '' ? substr($date, 0, 4) : null,
                'poster' => $r['poster_path'] ?? null,
                'href' => $localId
                    ? ($movies ? route('movies.show', $localId) : route('shows.show', $localId))
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, int>  $tmdbIds
     * @return \Illuminate\Support\Collection<int, int>  tmdb_id => id locale
     */
    private function inLibrary(bool $movies, array $tmdbIds)
    {
        if ($movies) {
            $local = Movie::whereIn('tmdb_id', $tmdbIds)->pluck('id', 'tmdb_id');
            $owned = UserMovie::where('user_id', Auth::id())->whereIn('movie_id', $local->values())->pluck('movie_id')->all();
        } else {
            $local = Show::whereIn('tmdb_id', $tmdbIds)->pluck('id', 'tmdb_id');
            $owned = UserShow::where('user_id', Auth::id())->whereIn('show_id', $local->values())->pluck('show_id')->all();
        }

        return $local->filter(fn (int $id) => in_array($id, $owned, true));
    }

    public function add(int $tmdbId, ?string $type = null): void
    {
        if (! $this->hasToken()) {
            return;
        }

        $library = new TmdbLibrary(new Tmdb($this->token()));

        $added = ($type ?? $this->type) === 'movies'
            ? $library->addMovie($tmdbId, (int) Auth::id())
            : $library->addShow($tmdbId, (int) Auth::id());

        unset($this->results, $this->trendingShows, $this->trendingMovies);

        Flux::toast(
            variant: $added ? 'success' : 'danger',
            text: $added ? __('Aggiunto a «Da vedere».') : __('Impossibile aggiungere: riprova.'),
        );
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Cerca') }}</flux:heading>

    @unless ($this->hasToken())
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>{{ __('Serve un token TMDB per cercare.') }}</flux:text>
            <flux:link :href="route('import.edit')" wire:navigate class="text-sm">{{ __('Impostalo qui →') }}</flux:link>
        </div>
    @else
        <flux:input wire:model.live.debounce.400ms="q" icon="magnifying-glass" autofocus
            :placeholder="__('Cerca una serie o un film…')" />

        @php $searching = mb_strlen(trim($q)) >= 2; @endphp

        @if ($searching)
            <div class="flex items-center justify-between gap-4">
                <div class="flex gap-2">
                    @foreach (['series' => __('Serie'), 'movies' => __('Film')] as $key => $label)
                        <flux:button size="sm" wire:click="$set('type', '{{ $key }}')"
                            :variant="$type === $key ? 'primary' : 'ghost'">{{ $label }}</flux:button>
                    @endforeach
                </div>

                <div class="flex gap-1">
                    <flux:button size="sm" icon="list-bullet" wire:click="$set('view', 'list')"
                        :variant="$view === 'list' ? 'primary' : 'ghost'" aria-label="{{ __('Lista') }}" />
                    <flux:button size="sm" icon="squares-2x2" wire:click="$set('view', 'grid')"
                        :variant="$view === 'grid' ? 'primary' : 'ghost'" aria-label="{{ __('Griglia') }}" />
                </div>
            </div>

            @php $results = $this->results; @endphp

            @if (empty($results))
                <flux:text class="py-12 text-center text-zinc-500">{{ __('Nessun risultato.') }}</flux:text>
            @elseif ($view === 'grid')
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($results as $item)
                        @include('partials.search-card', ['item' => $item, 'type' => $type])
                    @endforeach
                </div>
            @else
                <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($results as $item)
                        <div class="flex items-center gap-3 py-3">
                            <div class="h-[84px] w-14 shrink-0">
                                @include('partials.poster', ['poster' => $item['poster'], 'title' => $item['title'], 'ratio' => 'h-full w-full', 'size' => 'w185'])
                            </div>
                            <div class="min-w-0 flex-1">
                                <flux:heading size="sm" class="truncate">{{ $item['title'] }}</flux:heading>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ $item['year'] }}{{ $item['year'] ? ' · ' : '' }}{{ $type === 'movies' ? __('Film') : __('Serie') }}
                                </flux:text>
                            </div>
                            @if ($item['href'])
                                <flux:button :href="$item['href']" wire:navigate size="sm" variant="ghost" icon="check">
                                    {{ __('In libreria') }}
                                </flux:button>
                            @else
                                <flux:button wire:click="add({{ $item['tmdb_id'] }}, '{{ $type }}')" size="sm" variant="primary" icon="plus">
                                    {{ __('Aggiungi') }}
                                </flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @elseif ($browse !== '')
            @php
                $browseItems = $browse === 'movies' ? $this->trendingMovies : $this->trendingShows;
                $browseTitle = $browse === 'movies' ? __('Film di tendenza') : __('Serie di tendenza');
            @endphp
            <div class="flex items-center gap-2">
                <flux:button wire:click="$set('browse', '')" variant="ghost" size="sm" icon="arrow-left">{{ __('Indietro') }}</flux:button>
                <flux:heading size="lg">{{ $browseTitle }}</flux:heading>
            </div>
            @if (empty($browseItems))
                <flux:text class="py-12 text-center text-zinc-500">{{ __('Nessun contenuto di tendenza.') }}</flux:text>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($browseItems as $item)
                        @include('partials.search-card', ['item' => $item, 'type' => $browse])
                    @endforeach
                </div>
            @endif
        @else
            @foreach ([
                ['title' => __('Serie di tendenza'), 'items' => $this->trendingShows, 'type' => 'series', 'cta' => __('Sfoglia tutte le serie')],
                ['title' => __('Film di tendenza'), 'items' => $this->trendingMovies, 'type' => 'movies', 'cta' => __('Sfoglia tutti i film')],
            ] as $section)
                @if (! empty($section['items']))
                    <div class="flex flex-col gap-3">
                        <flux:heading size="lg">{{ $section['title'] }}</flux:heading>
                        <div class="-mx-4 flex gap-3 overflow-x-auto px-4 pb-1">
                            @foreach ($section['items'] as $item)
                                <div class="w-28 shrink-0 sm:w-32">
                                    @include('partials.search-card', ['item' => $item, 'type' => $section['type']])
                                </div>
                            @endforeach
                        </div>
                        <flux:button wire:click="$set('browse', '{{ $section['type'] }}')" variant="primary"
                            icon:trailing="chevron-right" class="w-full justify-between">
                            {{ $section['cta'] }}
                        </flux:button>
                    </div>
                @endif
            @endforeach
        @endif
    @endunless
</div>
