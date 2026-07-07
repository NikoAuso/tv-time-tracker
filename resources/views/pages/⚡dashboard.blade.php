<?php

use App\Models\Episode;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Serie da vedere')] class extends Component {
    public string $view = 'list';

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
        <flux:heading size="xl">{{ __('Serie da vedere') }}</flux:heading>
        <div class="flex items-center gap-3">
            <flux:text class="whitespace-nowrap text-zinc-500">{{ $this->upNext->count() }} {{ __('serie in corso') }}</flux:text>
            @unless ($this->upNext->isEmpty())
                <div class="flex gap-1">
                    <flux:button size="sm" icon="list-bullet" wire:click="$set('view', 'list')"
                        :variant="$view === 'list' ? 'primary' : 'outline'" aria-label="{{ __('Lista') }}" />
                    <flux:button size="sm" icon="squares-2x2" wire:click="$set('view', 'grid')"
                        :variant="$view === 'grid' ? 'primary' : 'outline'" aria-label="{{ __('Griglia') }}" />
                </div>
            @endunless
        </div>
    </div>

    @if ($this->upNext->isEmpty())
        <div class="flex flex-col items-center gap-2 py-16 text-center">
            <flux:icon.check-badge class="size-10 text-green-500" />
            <flux:heading size="lg">{{ __('Sei in pari!') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Nessun episodio in sospeso nelle serie che segui.') }}</flux:text>
        </div>
    @elseif ($view === 'grid')
        <div class="grid grid-cols-3 gap-4 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            @foreach ($this->upNext as $episode)
                <div class="flex flex-col gap-2">
                    <a href="{{ route('episodes.show', $episode) }}" wire:navigate class="relative block">
                        @include('partials.poster', ['poster' => $episode->show->poster_path, 'title' => $episode->show->name])
                        <span class="absolute left-1.5 top-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-white">
                            S{{ $episode->season_number }}E{{ $episode->episode_number }}
                        </span>
                    </a>
                    <div class="min-w-0">
                        <flux:text size="sm" class="truncate font-medium">{{ $episode->show->name }}</flux:text>
                        <flux:text size="sm" class="truncate text-zinc-500">{{ $episode->name ?: __('Episodio') }}</flux:text>
                    </div>
                    <flux:button size="xs" variant="primary" icon="check" class="w-full"
                        wire:click="markWatched({{ $episode->id }})">
                        {{ __('Visto') }}
                    </flux:button>
                </div>
            @endforeach
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->upNext as $episode)
                <div class="flex gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
                    <a href="{{ route('episodes.show', $episode) }}" wire:navigate class="flex min-w-0 flex-1 gap-3">
                        @include('partials.poster', ['poster' => $episode->show->poster_path, 'title' => $episode->show->name, 'ratio' => 'h-28 w-20 shrink-0', 'size' => 'w185'])
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <flux:heading size="sm" class="truncate">{{ $episode->show->name }}</flux:heading>
                            <flux:text size="sm" class="tabular-nums text-zinc-500">
                                S{{ $episode->season_number }}E{{ $episode->episode_number }}
                            </flux:text>
                            <flux:text size="sm" class="line-clamp-2">{{ $episode->name ?: __('Episodio') }}</flux:text>
                        </div>
                    </a>
                    <div class="flex shrink-0 items-center">
                        <flux:button size="sm" variant="primary" icon="check"
                            wire:click="markWatched({{ $episode->id }})" aria-label="{{ __('Segna visto') }}" />
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
