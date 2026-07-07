<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\UserList;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use App\Services\Tmdb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Show $show;

    public string $newSeason = '';

    public string $newEpisode = '';

    /** @var list<int> Stagioni aperte nell'accordion. */
    public array $openSeasons = [];

    public function mount(Show $show): void
    {
        $this->show = $show;

        if (($current = $this->currentSeason) !== null) {
            $this->openSeasons = [$current];
        }
    }

    public function toggleSeason(int $season): void
    {
        $this->openSeasons = in_array($season, $this->openSeasons, true)
            ? array_values(array_diff($this->openSeasons, [$season]))
            : [...$this->openSeasons, $season];
    }

    #[Computed]
    public function userShow(): ?UserShow
    {
        return UserShow::where('user_id', Auth::id())
            ->where('show_id', $this->show->id)
            ->first();
    }

    /**
     * Piattaforme streaming (flatrate) e link JustWatch, da TMDB e in cache.
     *
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    #[Computed]
    public function providers(): array
    {
        if (! $this->show->tmdb_id) {
            return ['link' => null, 'flatrate' => []];
        }

        return Cache::remember(
            "show:{$this->show->tmdb_id}:providers",
            now()->addHours(12),
            fn (): array => rescue(
                fn () => app(Tmdb::class)->showProviders($this->show->tmdb_id),
                ['link' => null, 'flatrate' => []],
                report: false,
            ),
        );
    }

    #[Computed]
    public function trailer(): ?string
    {
        if (! $this->show->tmdb_id) {
            return null;
        }

        return Cache::remember(
            "show:{$this->show->tmdb_id}:trailer",
            now()->addDay(),
            fn (): ?string => rescue(
                fn () => app(Tmdb::class)->showTrailer($this->show->tmdb_id),
                null,
                report: false,
            ),
        );
    }

    /**
     * Prima stagione con episodi ancora da vedere: è quella aperta di default.
     */
    #[Computed]
    public function currentSeason(): ?int
    {
        foreach ($this->seasons as $seasonNumber => $episodes) {
            if ($episodes->where('is_watched')->count() < $episodes->count()) {
                return (int) $seasonNumber;
            }
        }

        return null;
    }

    public function addWatchlist(): void
    {
        UserShow::updateOrCreate(
            ['user_id' => Auth::id(), 'show_id' => $this->show->id],
            ['status' => 'watchlist'],
        );

        unset($this->userShow);
    }

    public function remove(): void
    {
        UserShow::where('user_id', Auth::id())
            ->where('show_id', $this->show->id)
            ->delete();

        unset($this->userShow);
    }

    public function rate(int $stars): void
    {
        $entry = UserShow::firstOrCreate(
            ['user_id' => Auth::id(), 'show_id' => $this->show->id],
            ['status' => 'watchlist'],
        );
        $entry->update(['rating' => $stars >= 1 ? min($stars, 5) : null]);

        unset($this->userShow);
    }

    public function toggleFavorite(): void
    {
        $entry = UserShow::firstOrCreate(
            ['user_id' => Auth::id(), 'show_id' => $this->show->id],
            ['status' => 'watchlist'],
        );
        $entry->update(['is_favorite' => ! $entry->is_favorite]);

        unset($this->userShow);
    }

    /**
     * @return \Illuminate\Support\Collection<int, UserList>
     */
    #[Computed]
    public function userLists()
    {
        return UserList::where('user_id', Auth::id())->orderBy('name')->get();
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function listIds(): array
    {
        return $this->show->lists()->pluck('user_lists.id')->all();
    }

    public function toggleList(int $listId): void
    {
        if (! UserList::where('user_id', Auth::id())->whereKey($listId)->exists()) {
            return;
        }

        $this->show->lists()->toggle($listId);

        unset($this->listIds);
    }

    /**
     * Episodi raggruppati per stagione, con flag "visto".
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Episode>>
     */
    #[Computed]
    public function seasons()
    {
        $episodes = $this->show->episodes()
            ->orderBy('season_number')
            ->orderBy('episode_number')
            ->get();

        $watched = WatchedEpisode::where('user_id', Auth::id())
            ->whereIn('episode_id', $episodes->pluck('id'))
            ->pluck('episode_id')
            ->flip();

        // NB: closure void — un'arrow function ritornerebbe il bool e each()
        // interromperebbe il ciclo al primo episodio non visto.
        return $episodes
            ->each(function (Episode $e) use ($watched): void {
                $e->is_watched = $watched->has($e->id);
            })
            ->groupBy('season_number')
            // Gli "Speciali" (stagione 0) vanno in fondo, dopo le stagioni regolari.
            ->sortKeysUsing(fn ($a, $b): int => ((int) $a ?: PHP_INT_MAX) <=> ((int) $b ?: PHP_INT_MAX));
    }

    #[Computed]
    public function watchedCount(): int
    {
        return WatchedEpisode::where('user_id', Auth::id())
            ->whereIn('episode_id', $this->show->episodes()->select('id'))
            ->count();
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->show->episodes()->count();
    }

    public function toggle(int $episodeId): void
    {
        $watch = WatchedEpisode::where('user_id', Auth::id())
            ->where('episode_id', $episodeId)
            ->first();

        if ($watch) {
            $watch->delete();
        } else {
            WatchedEpisode::create([
                'user_id' => Auth::id(),
                'episode_id' => $episodeId,
                'watched_at' => now(),
            ]);
            $this->ensureFollowing();
        }

        $this->refreshLists();
    }

    public function markSeason(int $season): void
    {
        $this->markEpisodes(
            $this->show->episodes()->where('season_number', $season)->pluck('id')
        );
    }

    public function markAll(): void
    {
        $this->markEpisodes($this->show->episodes()->pluck('id'));
    }

    public function markUpTo(int $episodeId): void
    {
        $target = Episode::findOrFail($episodeId);

        $ids = $this->show->episodes()
            ->where(function ($q) use ($target) {
                $q->where('season_number', '<', $target->season_number)
                    ->orWhere(fn ($q2) => $q2->where('season_number', $target->season_number)
                        ->where('episode_number', '<=', $target->episode_number));
            })
            ->pluck('id');

        $this->markEpisodes($ids);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $ids
     */
    private function markEpisodes($ids): void
    {
        $existing = WatchedEpisode::where('user_id', Auth::id())
            ->whereIn('episode_id', $ids)
            ->pluck('episode_id');

        $now = now();
        $rows = $ids->diff($existing)->map(fn (int $id) => [
            'user_id' => Auth::id(),
            'episode_id' => $id,
            'watched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            WatchedEpisode::insert($rows);
            $this->ensureFollowing();
        }

        $this->refreshLists();
    }

    public function addEpisode(): void
    {
        $validated = $this->validate([
            'newSeason' => ['required', 'integer', 'min:0'],
            'newEpisode' => ['required', 'integer', 'min:0'],
        ]);

        $episode = Episode::firstOrCreate([
            'show_id' => $this->show->id,
            'season_number' => (int) $validated['newSeason'],
            'episode_number' => (int) $validated['newEpisode'],
        ]);

        WatchedEpisode::firstOrCreate(
            ['user_id' => Auth::id(), 'episode_id' => $episode->id],
            ['watched_at' => now()],
        );

        $this->ensureFollowing();
        $this->reset('newSeason', 'newEpisode');
        $this->refreshLists();
    }

    /**
     * Segnare un episodio come visto porta la serie in libreria come "In corso":
     * una serie "Da vedere" (watchlist) ha per definizione 0 episodi visti.
     */
    private function ensureFollowing(): void
    {
        $entry = UserShow::firstOrCreate(['user_id' => Auth::id(), 'show_id' => $this->show->id]);

        if ($entry->status === 'watchlist') {
            $entry->update(['status' => 'following']);
        }

        unset($this->userShow);
    }

    private function refreshLists(): void
    {
        unset($this->seasons, $this->watchedCount, $this->totalCount, $this->currentSeason);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-back-button />

    <div class="flex gap-4">
        @if ($show->poster_path)
            <img src="https://image.tmdb.org/t/p/w342{{ $show->poster_path }}" alt="{{ $show->name }}"
                class="h-44 w-28 shrink-0 rounded-xl object-cover shadow-lg ring-1 ring-black/10 dark:ring-white/10" />
        @endif
        <div class="flex flex-1 flex-col gap-2">
            <flux:heading size="xl">{{ $show->name }}</flux:heading>
            <flux:text class="tabular-nums text-zinc-600 dark:text-zinc-300">
                @if ($show->first_air_date) {{ $show->first_air_date->year }} @endif
                · {{ trans_choice('{1} :count stagione|[2,*] :count stagioni', $this->seasons->count(), ['count' => $this->seasons->count()]) }}
                · {{ $this->totalCount }} {{ __('episodi') }}
                @php
                    $statusLabel = match ($show->status) {
                        'Returning Series' => 'In corso',
                        'Ended' => 'Conclusa',
                        'Canceled' => 'Cancellata',
                        'In Production', 'Planned', 'Pilot' => 'In produzione',
                        default => null,
                    };
                @endphp
                @if ($statusLabel) · {{ __($statusLabel) }} @endif
            </flux:text>

            @if ($show->genres)
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($show->genres as $genre)
                        <flux:badge size="sm" color="zinc">{{ $genre }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-1.5">
        <flux:text class="text-zinc-500">
            {{ $this->watchedCount }}/{{ $this->totalCount }} {{ __('episodi visti') }}
        </flux:text>
        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div class="h-full rounded-full {{ $this->totalCount && $this->watchedCount === $this->totalCount ? 'bg-green-500' : 'bg-accent' }}"
                style="width: {{ $this->totalCount ? round($this->watchedCount / $this->totalCount * 100) : 0 }}%"></div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @if ($this->watchedCount === 0 && $this->userShow?->status !== 'watchlist')
            <flux:button wire:click="addWatchlist" size="sm" icon="bookmark" variant="outline">
                {{ __('Segna da vedere') }}
            </flux:button>
        @elseif ($this->userShow?->status === 'watchlist')
            <flux:badge color="zinc" icon="bookmark">{{ __('Da vedere') }}</flux:badge>
        @endif

        @if ($this->userShow && $this->watchedCount === 0)
            <x-remove-button wire:click="remove" size="sm" icon="trash"
                wire:confirm="{{ __('Rimuovere la serie dalla libreria?') }}">{{ __('Rimuovi') }}</x-remove-button>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <button type="button" wire:click="toggleFavorite" class="shrink-0" aria-label="{{ __('Preferito') }}">
            <flux:icon.heart class="size-6 {{ $this->userShow?->is_favorite ? 'fill-current text-red-500' : 'text-zinc-400' }}" />
        </button>
        @include('partials.star-rating', ['rating' => $this->userShow?->rating])
        @include('partials.add-to-list', ['lists' => $this->userLists, 'activeIds' => $this->listIds])
    </div>

    @if ($show->overview)
        <flux:separator />
        <div class="flex flex-col gap-2">
            <flux:heading size="lg">{{ __('Trama') }}</flux:heading>
            <flux:text class="leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $show->overview }}</flux:text>
        </div>
    @endif

    @if ($this->trailer || $this->providers['flatrate'])
        <flux:separator />
        <div class="flex flex-col gap-4">
            @if ($this->trailer)
                <flux:button :href="$this->trailer" target="_blank" size="sm" icon="play" variant="outline" class="self-start">
                    {{ __('Trailer') }}
                </flux:button>
            @endif

            @if ($this->providers['flatrate'])
                <div class="flex flex-col gap-2">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Dove guardarlo') }}</flux:text>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($this->providers['flatrate'] as $provider)
                            <a @if ($this->providers['link']) href="{{ $this->providers['link'] }}" target="_blank" @endif
                                title="{{ $provider['name'] }}" class="shrink-0">
                                @if ($provider['logo_path'])
                                    <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}"
                                        alt="{{ $provider['name'] }}" class="size-10 rounded-lg object-cover" />
                                @else
                                    <flux:badge color="zinc">{{ $provider['name'] }}</flux:badge>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <flux:separator />

    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('Episodi') }}</flux:heading>
            @if ($this->totalCount && $this->watchedCount < $this->totalCount)
                <flux:button size="sm" icon="check" variant="primary"
                    wire:click="markAll"
                    aria-label="{{ __('Segna tutta la serie come vista') }}">{{ __('Segna tutto') }}</flux:button>
            @endif
        </div>

        @forelse ($this->seasons as $seasonNumber => $episodes)
            @php $seen = $episodes->where('is_watched')->count(); @endphp
            @php $open = in_array((int) $seasonNumber, $this->openSeasons, true); @endphp
            <div wire:key="season-{{ $seasonNumber }}"
                class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-3 p-4">
                    <div class="flex flex-1 cursor-pointer items-center gap-3 select-none"
                        wire:click="toggleSeason({{ $seasonNumber }})">
                        <flux:icon.chevron-right class="size-5 shrink-0 text-zinc-400 transition {{ $open ? 'rotate-90' : '' }}" />
                        <span class="flex-1 font-medium">
                            {{ $seasonNumber == 0 ? __('Speciali') : __('Stagione').' '.$seasonNumber }}
                        </span>
                        <flux:badge size="sm" :color="$seen === $episodes->count() ? 'green' : 'zinc'">
                            {{ $seen }}/{{ $episodes->count() }}
                        </flux:badge>
                    </div>
                    @if ($seen < $episodes->count())
                        <flux:button size="sm" icon="check" variant="primary"
                            wire:click="markSeason({{ $seasonNumber }})"
                            aria-label="{{ __('Segna stagione come vista') }}">{{ __('Visto') }}</flux:button>
                    @endif
                </div>

                @if ($open)
                    <div class="divide-y divide-zinc-100 border-t border-zinc-100 px-4 dark:divide-zinc-800 dark:border-zinc-800">
                        @foreach ($episodes as $episode)
                        <div wire:key="ep-{{ $episode->id }}" class="flex items-center gap-3 py-2">
                            <flux:link :href="route('episodes.show', $episode)" wire:navigate
                                class="flex! min-w-0 flex-1 items-center gap-3 no-underline">
                                <div class="aspect-video w-24 shrink-0 overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                    @if ($episode->still_path)
                                        <img src="https://image.tmdb.org/t/p/w185{{ $episode->still_path }}" alt=""
                                            class="h-full w-full object-cover" />
                                    @else
                                        <div class="flex h-full items-center justify-center text-zinc-400">
                                            <flux:icon.film class="size-5" />
                                        </div>
                                    @endif
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col">
                                    <flux:text size="sm" class="tabular-nums text-zinc-400">
                                        S{{ $seasonNumber }} | E{{ $episode->episode_number }}
                                    </flux:text>
                                    <flux:text class="truncate {{ $episode->is_watched ? '' : 'font-medium' }}">
                                        {{ $episode->name ?: __('Episodio').' '.$episode->episode_number }}
                                    </flux:text>
                                </div>
                            </flux:link>

                            <button type="button" wire:click="toggle({{ $episode->id }})"
                                aria-label="{{ __('Segna visto') }}"
                                class="flex size-8 shrink-0 items-center justify-center rounded-full border transition
                                    {{ $episode->is_watched
                                        ? 'border-green-600 bg-green-600 text-white'
                                        : 'border-zinc-300 bg-white text-zinc-400 hover:border-green-500 hover:text-green-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-500' }}">
                                <flux:icon.check variant="micro" class="size-4" />
                            </button>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <flux:text class="py-8 text-center">{{ __('Nessun episodio disponibile.') }}</flux:text>
        @endforelse
    </div>

    <flux:separator />

    <form wire:submit="addEpisode" class="flex flex-wrap items-end gap-3">
        <flux:input wire:model="newSeason" type="number" min="0" :label="__('Stagione')" class="w-24" />
        <flux:input wire:model="newEpisode" type="number" min="0" :label="__('Episodio')" class="w-24" />
        <flux:button type="submit" variant="outline" icon="plus">{{ __('Aggiungi mancante') }}</flux:button>
    </form>
</div>
