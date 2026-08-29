<?php

use App\Models\Episode;
use App\Models\WatchedEpisode;
use App\Services\WatchProviders;
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

    /**
     * Piattaforme streaming (flatrate) della serie, da TMDB e in cache.
     *
     * @return array{link: string|null, flatrate: array<int, array{name: string, logo_path: string|null}>}
     */
    #[Computed]
    public function providers(): array
    {
        return app(WatchProviders::class)->forShow($this->episode->show);
    }

    public function toggle(): void
    {
        if ($watch = $this->watch()) {
            $watch->delete();
            unset($this->watch);

            return;
        }

        WatchedEpisode::create([
            'user_id' => Auth::id(),
            'episode_id' => $this->episode->id,
            'watched_at' => now(),
        ]);
        unset($this->watch);

        if ($this->hasEarlierUnwatched()) {
            $this->modal('mark-previous')->show();
        }
    }

    /**
     * Ci sono episodi della serie precedenti a questo ancora non visti?
     */
    private function hasEarlierUnwatched(): bool
    {
        $earlier = $this->episode->show->episodes()
            ->where(function ($q) {
                $q->where('season_number', '<', $this->episode->season_number)
                    ->orWhere(fn ($q2) => $q2->where('season_number', $this->episode->season_number)
                        ->where('episode_number', '<', $this->episode->episode_number));
            })
            ->pluck('id');

        if ($earlier->isEmpty()) {
            return false;
        }

        $seen = WatchedEpisode::where('user_id', Auth::id())
            ->whereIn('episode_id', $earlier)
            ->count();

        return $seen < $earlier->count();
    }

    public function markPrevious(): void
    {
        $ids = $this->episode->show->episodes()
            ->where(function ($q) {
                $q->where('season_number', '<', $this->episode->season_number)
                    ->orWhere(fn ($q2) => $q2->where('season_number', $this->episode->season_number)
                        ->where('episode_number', '<=', $this->episode->episode_number));
            })
            ->pluck('id');

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
        }

        unset($this->watch);
        $this->modal('mark-previous')->close();
    }

    public function dismissPrevious(): void
    {
        $this->modal('mark-previous')->close();
    }

    public function rate(int $stars): void
    {
        $watch = WatchedEpisode::firstOrCreate(
            ['user_id' => Auth::id(), 'episode_id' => $this->episode->id],
            ['watched_at' => now()],
        );
        $watch->update(['rating' => $stars >= 1 ? min($stars, 5) : null]);

        unset($this->watch);
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <x-back-button />

    @if ($episode->still_path)
        <img src="https://image.tmdb.org/t/p/w780{{ $episode->still_path }}" alt="{{ $episode->name }}"
            class="aspect-video w-full rounded-xl object-cover" />
    @endif

    <div class="flex flex-col gap-2">
        <flux:link :href="route('shows.show', $episode->show)" wire:navigate class="text-sm font-medium">
            {{ $episode->show->name }}
        </flux:link>
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

    <div class="flex flex-col gap-2">
        <flux:text size="sm" class="text-zinc-500">{{ __('La tua valutazione') }}</flux:text>
        @include('partials.star-rating', ['rating' => $this->watch?->rating])
    </div>

    @if ($episode->overview)
        <flux:text class="leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $episode->overview }}</flux:text>
    @endif

    @if ($this->providers['flatrate'])
        <flux:separator />
        <div class="flex flex-col gap-2">
            <flux:text size="sm" class="text-zinc-500">{{ __('Dove vederlo') }}</flux:text>
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

    <flux:modal name="mark-previous" class="max-w-sm">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ __('Episodi precedenti') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Hai già visto anche gli episodi precedenti? Li segno come visti.') }}</flux:text>
            <div class="flex gap-2">
                <flux:button wire:click="markPrevious" variant="primary" class="flex-1">{{ __('Sì, segnali') }}</flux:button>
                <flux:button wire:click="dismissPrevious" variant="outline" class="flex-1">{{ __('No') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
