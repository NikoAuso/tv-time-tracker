<?php

use App\Models\Movie;
use App\Models\UserList;
use App\Models\UserMovie;
use App\Services\Tmdb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Movie $movie;

    public function mount(Movie $movie): void
    {
        $this->movie = $movie;
    }

    #[Computed]
    public function entry(): ?UserMovie
    {
        return UserMovie::where('user_id', Auth::id())
            ->where('movie_id', $this->movie->id)
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
        if (! $this->movie->tmdb_id) {
            return ['link' => null, 'flatrate' => []];
        }

        return Cache::remember(
            "movie:{$this->movie->tmdb_id}:providers",
            now()->addHours(12),
            fn (): array => rescue(
                fn () => app(Tmdb::class)->movieProviders($this->movie->tmdb_id),
                ['link' => null, 'flatrate' => []],
                report: false,
            ),
        );
    }

    #[Computed]
    public function trailer(): ?string
    {
        if (! $this->movie->tmdb_id) {
            return null;
        }

        return Cache::remember(
            "movie:{$this->movie->tmdb_id}:trailer",
            now()->addDay(),
            fn (): ?string => rescue(
                fn () => app(Tmdb::class)->movieTrailer($this->movie->tmdb_id),
                null,
                report: false,
            ),
        );
    }

    #[Computed]
    public function backdrop(): ?string
    {
        if (! $this->movie->tmdb_id) {
            return null;
        }

        return Cache::remember(
            "movie:{$this->movie->tmdb_id}:backdrop",
            now()->addWeek(),
            fn (): ?string => rescue(
                fn () => data_get(app(Tmdb::class)->getMovie($this->movie->tmdb_id), 'backdrop_path'),
                null,
                report: false,
            ),
        );
    }

    public function markWatched(): void
    {
        UserMovie::updateOrCreate(
            ['user_id' => Auth::id(), 'movie_id' => $this->movie->id],
            ['status' => 'watched', 'watched_at' => now()],
        );

        unset($this->entry);
    }

    public function rewatch(): void
    {
        $entry = UserMovie::firstOrCreate(
            ['user_id' => Auth::id(), 'movie_id' => $this->movie->id],
            ['status' => 'watched'],
        );
        $entry->update([
            'status' => 'watched',
            'watched_at' => now(),
            'rewatch_count' => $entry->rewatch_count + 1,
        ]);

        unset($this->entry);
        $this->modal('visto-actions')->close();
    }

    public function unwatch(): void
    {
        UserMovie::where('user_id', Auth::id())
            ->where('movie_id', $this->movie->id)
            ->update(['status' => 'watchlist', 'watched_at' => null, 'rewatch_count' => 0]);

        unset($this->entry);
        $this->modal('visto-actions')->close();
    }

    public function addWatchlist(): void
    {
        UserMovie::updateOrCreate(
            ['user_id' => Auth::id(), 'movie_id' => $this->movie->id],
            ['status' => 'watchlist'],
        );

        unset($this->entry);
    }

    public function remove(): void
    {
        UserMovie::where('user_id', Auth::id())
            ->where('movie_id', $this->movie->id)
            ->delete();

        unset($this->entry);
    }

    public function toggleFavorite(): void
    {
        $entry = UserMovie::firstOrCreate(
            ['user_id' => Auth::id(), 'movie_id' => $this->movie->id],
            ['status' => 'watchlist'],
        );
        $entry->update(['is_favorite' => ! $entry->is_favorite]);

        unset($this->entry);
    }

    public function rate(int $stars): void
    {
        UserMovie::updateOrCreate(
            ['user_id' => Auth::id(), 'movie_id' => $this->movie->id],
            ['rating' => $stars >= 1 ? min($stars, 5) : null],
        );

        unset($this->entry);
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
        return $this->movie->lists()->pluck('user_lists.id')->all();
    }

    public function toggleList(int $listId): void
    {
        if (! UserList::where('user_id', Auth::id())->whereKey($listId)->exists()) {
            return;
        }

        $this->movie->lists()->toggle($listId);

        unset($this->listIds);
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <x-back-button />

    <div class="relative overflow-hidden rounded-2xl bg-zinc-100 dark:bg-zinc-800">
        @if ($this->backdrop)
            <img src="https://image.tmdb.org/t/p/w780{{ $this->backdrop }}" alt="" aria-hidden="true"
                class="absolute inset-0 h-full w-full object-cover object-top" />
            <div class="absolute inset-0 bg-gradient-to-t from-zinc-100 via-zinc-100/85 to-zinc-100/30 dark:from-zinc-800 dark:via-zinc-800/85 dark:to-zinc-800/30"></div>
        @endif

        <div class="relative flex items-end gap-4 p-4 pt-24 sm:pt-28">
            @if ($movie->poster_path)
                <img src="https://image.tmdb.org/t/p/w342{{ $movie->poster_path }}" alt="{{ $movie->title }}"
                    class="h-44 w-28 shrink-0 rounded-xl object-cover shadow-lg ring-1 ring-black/10 dark:ring-white/10" />
            @endif
            <div class="flex flex-1 flex-col gap-2">
                <flux:heading size="xl">{{ $movie->title }}</flux:heading>
                <flux:text class="tabular-nums text-zinc-600 dark:text-zinc-300">
                    @if ($movie->release_date) {{ $movie->release_date->year }} @endif
                    @if ($movie->runtime) · {{ $movie->runtime }} {{ __('min') }} @endif
                </flux:text>

                @if ($movie->genres)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($movie->genres as $genre)
                            <flux:badge size="sm" color="zinc">{{ $genre }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @if ($this->entry?->status === 'watched')
            <flux:modal.trigger name="visto-actions">
                <flux:button icon="check" variant="primary" color="green">
                    {{ __('Visto') }}
                    @if ($this->entry->rewatch_count > 0)
                        <span class="ml-1 opacity-80">×{{ $this->entry->rewatch_count + 1 }}</span>
                    @endif
                </flux:button>
            </flux:modal.trigger>
            @if ($this->entry->watched_at)
                <flux:text size="sm" class="text-zinc-500">
                    {{ __('il') }} {{ $this->entry->watched_at->format('d/m/Y') }}
                </flux:text>
            @endif
        @else
            <flux:button wire:click="markWatched" icon="check" variant="primary">
                {{ __('Segna visto') }}
            </flux:button>
        @endif

        @unless ($this->entry)
            <flux:button wire:click="addWatchlist" icon="bookmark" variant="outline">
                {{ __('Da guardare') }}
            </flux:button>
        @endunless

        @if ($this->entry)
            <flux:button wire:click="remove" variant="danger" size="sm" icon="trash"
                wire:confirm="{{ __('Rimuovere il film dalla libreria?') }}">{{ __('Rimuovi') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <button type="button" wire:click="toggleFavorite" class="shrink-0" aria-label="{{ __('Preferito') }}">
            <flux:icon.heart class="size-6 {{ $this->entry?->is_favorite ? 'fill-current text-red-500' : 'text-zinc-400' }}" />
        </button>
        @include('partials.star-rating', ['rating' => $this->entry?->rating])
        @include('partials.add-to-list', ['lists' => $this->userLists, 'activeIds' => $this->listIds])
    </div>

    @if ($this->entry?->status === 'watchlist')
        <flux:text size="sm" class="text-zinc-500">{{ __('Da vedere') }}</flux:text>
    @endif

    @if ($movie->overview)
        <flux:separator />
        <flux:text class="leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $movie->overview }}</flux:text>
    @endif

    @if ($this->trailer || $this->providers['flatrate'])
        <flux:separator />
        <div class="flex flex-col gap-4">
            @if ($this->trailer)
                <flux:button :href="$this->trailer" target="_blank" icon="play" variant="outline" class="self-start">
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

    <flux:modal name="visto-actions" class="max-w-sm">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ __('Visto') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Cosa vuoi fare con questo film?') }}</flux:text>
            <div class="flex flex-col gap-2">
                <flux:button wire:click="rewatch" icon="arrow-path" variant="primary">
                    {{ __('Visto di nuovo') }}
                </flux:button>
                <flux:button wire:click="unwatch" icon="x-mark" variant="danger">
                    {{ __('Rimuovi visto') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
