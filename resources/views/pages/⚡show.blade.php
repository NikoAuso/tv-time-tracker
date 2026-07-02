<?php

use App\Models\Episode;
use App\Models\Show;
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

    /**
     * Episodi della serie raggruppati per stagione, con flag "visto".
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
        }

        unset($this->seasons, $this->watchedCount);
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

        $this->reset('newSeason', 'newEpisode');
        unset($this->seasons, $this->watchedCount);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center gap-2">
        <flux:button :href="route('library')" wire:navigate variant="ghost" size="sm" icon="arrow-left">
            {{ __('Libreria') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $show->name }}</flux:heading>
            <flux:text class="text-zinc-500">{{ $this->watchedCount }} {{ __('episodi visti') }}</flux:text>
        </div>
    </div>

    <flux:separator />

    <form wire:submit="addEpisode" class="flex flex-wrap items-end gap-3">
        <flux:input wire:model="newSeason" type="number" min="0" :label="__('Stagione')" class="w-28" />
        <flux:input wire:model="newEpisode" type="number" min="0" :label="__('Episodio')" class="w-28" />
        <flux:button type="submit" variant="primary" icon="plus">{{ __('Segna visto') }}</flux:button>
    </form>

    @forelse ($this->seasons as $seasonNumber => $episodes)
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Stagione') }} {{ $seasonNumber }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                @foreach ($episodes as $episode)
                    <flux:button size="sm" wire:click="toggle({{ $episode->id }})"
                        :variant="$episode->is_watched ? 'primary' : 'ghost'"
                        :icon="$episode->is_watched ? 'check' : null">
                        E{{ $episode->episode_number }}
                    </flux:button>
                @endforeach
            </div>
        </div>
    @empty
        <flux:text class="py-8 text-center">
            {{ __('Nessun episodio ancora. I dati completi arriveranno con la sincronizzazione TMDB.') }}
        </flux:text>
    @endforelse
</div>
