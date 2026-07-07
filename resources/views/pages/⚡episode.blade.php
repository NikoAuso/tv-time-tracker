<?php

use App\Models\Episode;
use App\Models\WatchedEpisode;
use App\Services\Tmdb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        if (! $this->episode->show->tmdb_id) {
            return ['link' => null, 'flatrate' => []];
        }

        return Cache::remember(
            "show:{$this->episode->show->tmdb_id}:providers",
            now()->addHours(12),
            fn (): array => rescue(
                fn () => app(Tmdb::class)->showProviders($this->episode->show->tmdb_id),
                ['link' => null, 'flatrate' => []],
                report: false,
            ),
        );
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
</div>
