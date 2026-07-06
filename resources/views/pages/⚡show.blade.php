<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\UserList;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Show $show;

    public string $newSeason = '';

    public string $newEpisode = '';

    public function mount(Show $show): void
    {
        $this->show = $show;
    }

    #[Computed]
    public function userShow(): ?UserShow
    {
        return UserShow::where('user_id', Auth::id())
            ->where('show_id', $this->show->id)
            ->first();
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

        return $episodes
            ->each(fn (Episode $e) => $e->is_watched = $watched->has($e->id))
            ->groupBy('season_number');
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
        unset($this->seasons, $this->watchedCount, $this->totalCount);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-back-button />

    <div class="flex gap-4">
        @if ($show->poster_path)
            <img src="https://image.tmdb.org/t/p/w185{{ $show->poster_path }}" alt="{{ $show->name }}"
                class="hidden h-40 w-28 shrink-0 rounded-xl object-cover sm:block" />
        @endif
        <div class="flex flex-1 flex-col gap-2">
            <flux:heading size="xl">{{ $show->name }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ $this->watchedCount }}/{{ $this->totalCount }} {{ __('episodi visti') }}
            </flux:text>
            <div class="h-2 max-w-xs overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full rounded-full bg-accent"
                    style="width: {{ $this->totalCount ? round($this->watchedCount / $this->totalCount * 100) : 0 }}%"></div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                @if ($this->watchedCount === 0 && $this->userShow?->status !== 'watchlist')
                    <flux:button wire:click="addWatchlist" icon="bookmark" variant="outline">
                        {{ __('Segna da vedere') }}
                    </flux:button>
                @elseif ($this->userShow?->status === 'watchlist')
                    <flux:badge color="zinc" icon="bookmark">{{ __('Da vedere') }}</flux:badge>
                @endif

                @if ($this->userShow)
                    <flux:button wire:click="remove" variant="danger" size="sm" icon="trash"
                        wire:confirm="{{ __('Rimuovere la serie dalla libreria?') }}">{{ __('Rimuovi') }}</flux:button>
                @endif
            </div>

            <div class="mt-1 flex flex-wrap items-center gap-4">
                <button type="button" wire:click="toggleFavorite" class="shrink-0" aria-label="{{ __('Preferito') }}">
                    <flux:icon.heart class="size-6 {{ $this->userShow?->is_favorite ? 'fill-current text-red-500' : 'text-zinc-400' }}" />
                </button>
                @include('partials.star-rating', ['rating' => $this->userShow?->rating])
                @include('partials.add-to-list', ['lists' => $this->userLists, 'activeIds' => $this->listIds])
            </div>
            @if ($show->overview)
                <flux:text size="sm" class="mt-1 line-clamp-3 text-zinc-500">{{ $show->overview }}</flux:text>
            @endif
        </div>
    </div>

    <flux:separator />

    @forelse ($this->seasons as $seasonNumber => $episodes)
        @php $seen = $episodes->where('is_watched')->count(); @endphp
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">
                    {{ $seasonNumber == 0 ? __('Speciali') : __('Stagione').' '.$seasonNumber }}
                    <span class="ml-1 text-sm font-normal text-zinc-500">{{ $seen }}/{{ $episodes->count() }}</span>
                </flux:heading>
                @if ($seen < $episodes->count())
                    <flux:button size="xs" variant="outline" icon="check"
                        wire:click="markSeason({{ $seasonNumber }})">{{ __('Segna stagione') }}</flux:button>
                @endif
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($episodes as $episode)
                    <div class="flex items-center gap-3 py-2">
                        <flux:button size="xs" variant="{{ $episode->is_watched ? 'primary' : 'outline' }}"
                            :color="$episode->is_watched ? 'green' : null"
                            icon="check" wire:click="toggle({{ $episode->id }})"
                            aria-label="{{ __('Segna visto') }}" />

                        <flux:link :href="route('episodes.show', $episode)" wire:navigate
                            class="flex min-w-0 flex-1 items-baseline gap-2 no-underline">
                            <flux:text class="shrink-0 tabular-nums text-zinc-400">{{ $episode->episode_number }}</flux:text>
                            <flux:text class="truncate {{ $episode->is_watched ? '' : 'font-medium' }}">
                                {{ $episode->name ?: __('Episodio').' '.$episode->episode_number }}
                            </flux:text>
                        </flux:link>

                        @if ($episode->air_date)
                            <flux:text size="sm" class="shrink-0 tabular-nums text-zinc-400">
                                {{ $episode->air_date->format('d/m/y') }}
                            </flux:text>
                        @endif

                        @unless ($episode->is_watched)
                            <flux:button size="xs" variant="outline" wire:click="markUpTo({{ $episode->id }})"
                                title="{{ __('Segna tutti fino a qui') }}">{{ __('fino a qui') }}</flux:button>
                        @endunless
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <flux:text class="py-8 text-center">{{ __('Nessun episodio disponibile.') }}</flux:text>
    @endforelse

    <flux:separator />

    <form wire:submit="addEpisode" class="flex flex-wrap items-end gap-3">
        <flux:input wire:model="newSeason" type="number" min="0" :label="__('Stagione')" class="w-24" />
        <flux:input wire:model="newEpisode" type="number" min="0" :label="__('Episodio')" class="w-24" />
        <flux:button type="submit" variant="outline" icon="plus">{{ __('Aggiungi mancante') }}</flux:button>
    </form>
</div>
