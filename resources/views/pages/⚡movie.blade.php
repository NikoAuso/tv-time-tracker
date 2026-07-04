<?php

use App\Models\Movie;
use App\Models\UserList;
use App\Models\UserMovie;
use Illuminate\Support\Facades\Auth;
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

    public function markWatched(): void
    {
        UserMovie::updateOrCreate(
            ['user_id' => Auth::id(), 'movie_id' => $this->movie->id],
            ['status' => 'watched', 'watched_at' => now()],
        );

        unset($this->entry);
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
        $this->movie->lists()->toggle($listId);

        unset($this->listIds);
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <flux:button :href="route('library')" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="self-start">
        {{ __('Libreria') }}
    </flux:button>

    <div class="flex gap-4">
        @if ($movie->poster_path)
            <img src="https://image.tmdb.org/t/p/w342{{ $movie->poster_path }}" alt="{{ $movie->title }}"
                class="h-48 w-32 shrink-0 rounded-xl object-cover" />
        @endif
        <div class="flex flex-1 flex-col gap-2">
            <flux:heading size="xl">{{ $movie->title }}</flux:heading>
            <flux:text class="tabular-nums text-zinc-500">
                @if ($movie->release_date) {{ $movie->release_date->year }} @endif
                @if ($movie->runtime) · {{ $movie->runtime }} {{ __('min') }} @endif
            </flux:text>

            <div class="mt-2 flex flex-wrap items-center gap-3">
                @if ($this->entry?->status === 'watched')
                    <flux:badge color="green" icon="check">{{ __('Visto') }}</flux:badge>
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
                        {{ __('Watchlist') }}
                    </flux:button>
                @endunless

                @if ($this->entry)
                    <flux:button wire:click="remove" variant="ghost" size="sm"
                        wire:confirm="{{ __('Rimuovere il film dalla libreria?') }}">{{ __('Rimuovi') }}</flux:button>
                @endif
            </div>

            <div class="mt-1 flex flex-wrap items-center gap-4">
                @include('partials.star-rating', ['rating' => $this->entry?->rating])
                @include('partials.add-to-list', ['lists' => $this->userLists, 'activeIds' => $this->listIds])
            </div>
        </div>
    </div>

    @if ($this->entry?->status === 'watchlist')
        <flux:text size="sm" class="text-zinc-500">{{ __('Da vedere') }}</flux:text>
    @endif

    @if ($movie->overview)
        <flux:separator />
        <flux:text class="leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $movie->overview }}</flux:text>
    @endif
</div>
