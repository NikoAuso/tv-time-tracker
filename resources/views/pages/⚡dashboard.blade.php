<?php

use App\Models\Episode;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Da guardare')] class extends Component {
    /**
     * Primo episodio non visto (già uscito) per ogni serie seguita.
     *
     * @return \Illuminate\Support\Collection<int, Episode>
     */
    #[Computed]
    public function upNext()
    {
        $userId = Auth::id();

        $showIds = UserShow::where('user_id', $userId)
            ->where('status', 'following')
            ->pluck('show_id');

        // NOT EXISTS invece di whereNotIn(watchedIds): evita di caricare e bindare
        // migliaia di id (oltre il limite di variabili di SQLite) a ogni render.
        return Episode::query()
            ->with('show')
            ->whereIn('show_id', $showIds)
            ->where('season_number', '>=', 1)
            ->whereDoesntHave('watches', fn ($q) => $q->where('user_id', $userId))
            ->where(fn ($q) => $q->whereNull('air_date')->orWhereDate('air_date', '<=', now()))
            ->orderBy('show_id')
            ->orderBy('season_number')
            ->orderBy('episode_number')
            ->get()
            ->groupBy('show_id')
            ->map->first()
            ->sortBy(fn (Episode $e) => $e->show->name)
            ->values();
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
    <div class="flex items-end justify-between gap-4">
        <flux:heading size="xl">{{ __('Da guardare') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ $this->upNext->count() }} {{ __('serie in corso') }}</flux:text>
    </div>

    @if ($this->upNext->isEmpty())
        <div class="flex flex-col items-center gap-2 py-16 text-center">
            <flux:icon.check-badge class="size-10 text-green-500" />
            <flux:heading size="lg">{{ __('Sei in pari!') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Nessun episodio in sospeso nelle serie che segui.') }}</flux:text>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->upNext as $episode)
                <div class="flex gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:link :href="route('shows.show', $episode->show)" wire:navigate class="shrink-0">
                        <div class="flex h-28 w-20 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 text-xl font-bold text-zinc-400 dark:bg-zinc-800">
                            @if ($episode->show->poster_path)
                                <img src="https://image.tmdb.org/t/p/w185{{ $episode->show->poster_path }}"
                                    alt="{{ $episode->show->name }}" class="h-full w-full object-cover" />
                            @else
                                {{ Str::of($episode->show->name)->trim()->substr(0, 1)->upper() }}
                            @endif
                        </div>
                    </flux:link>

                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <flux:heading size="sm" class="truncate">{{ $episode->show->name }}</flux:heading>
                        <flux:text size="sm" class="tabular-nums text-zinc-500">
                            S{{ $episode->season_number }}E{{ $episode->episode_number }}
                        </flux:text>
                        <flux:text size="sm" class="line-clamp-2">{{ $episode->name ?: __('Episodio') }}</flux:text>
                        <div class="mt-auto pt-2">
                            <flux:button size="sm" variant="primary" icon="check"
                                wire:click="markWatched({{ $episode->id }})">
                                {{ __('Visto') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
