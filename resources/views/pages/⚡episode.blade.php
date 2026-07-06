<?php

use App\Models\Episode;
use App\Models\WatchedEpisode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Episode $episode;

    public function mount(Episode $episode): void
    {
        $this->episode = $episode->load('show');
    }

    #[Computed]
    public function watch(): ?WatchedEpisode
    {
        return WatchedEpisode::where('user_id', Auth::id())
            ->where('episode_id', $this->episode->id)
            ->first();
    }

    public function toggle(): void
    {
        if ($watch = $this->watch()) {
            $watch->delete();
        } else {
            WatchedEpisode::create([
                'user_id' => Auth::id(),
                'episode_id' => $this->episode->id,
                'watched_at' => now(),
            ]);
        }

        unset($this->watch);
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <flux:button :href="route('shows.show', $episode->show)" wire:navigate variant="outline" size="sm"
        icon="arrow-left" class="self-start">{{ $episode->show->name }}</flux:button>

    @if ($episode->still_path)
        <img src="https://image.tmdb.org/t/p/w780{{ $episode->still_path }}" alt="{{ $episode->name }}"
            class="aspect-video w-full rounded-xl object-cover" />
    @endif

    <div class="flex flex-col gap-2">
        <flux:text class="font-mono text-sm uppercase tracking-wide text-zinc-500">
            S{{ $episode->season_number }}E{{ $episode->episode_number }}
            @if ($episode->air_date) · {{ $episode->air_date->format('d/m/Y') }} @endif
            @if ($episode->runtime) · {{ $episode->runtime }} {{ __('min') }} @endif
        </flux:text>
        <flux:heading size="xl">{{ $episode->name ?: __('Episodio').' '.$episode->episode_number }}</flux:heading>
    </div>

    <div class="flex items-center gap-3">
        <flux:button wire:click="toggle" icon="check"
            :variant="$this->watch ? 'primary' : 'outline'">
            {{ $this->watch ? __('Visto') : __('Segna visto') }}
        </flux:button>
        @if ($this->watch?->watched_at)
            <flux:text size="sm" class="text-zinc-500">
                {{ __('il') }} {{ $this->watch->watched_at->format('d/m/Y') }}
            </flux:text>
        @endif
    </div>

    @if ($episode->overview)
        <flux:text class="leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $episode->overview }}</flux:text>
    @endif
</div>
