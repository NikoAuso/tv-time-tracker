<?php

use App\Models\UserMovie;
use App\Models\UserShow;
use App\Services\SeriesProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Libreria')] class extends Component
{
    public string $search = '';

    public string $type = 'series';

    public string $status = 'in_progress';

    public function updatedType(): void
    {
        // "In corso" esiste solo per le serie: passando ai Film ripiego su "Visti".
        if ($this->type === 'movies' && $this->status === 'in_progress') {
            $this->status = 'done';
        }
    }

    /**
     * @return Collection<int, int>
     */
    #[Computed]
    public function startedShowIds()
    {
        return SeriesProgress::startedShowIds((int) Auth::id());
    }

    /**
     * @return Collection<int, int>
     */
    #[Computed]
    public function concludedShowIds()
    {
        return SeriesProgress::concludedShowIds((int) Auth::id());
    }

    /**
     * Serie e film uniti in un'unica lista, secondo i filtri tipo/stato.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function items()
    {
        $items = collect();

        if ($this->type !== 'movies') {
            $items = $items->merge($this->seriesItems());
        }

        // I film non hanno lo stato "In corso".
        if ($this->type !== 'series' && $this->status !== 'in_progress') {
            $items = $items->merge($this->movieItems());
        }

        return $items->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function seriesItems()
    {
        $userId = Auth::id();
        $started = $this->startedShowIds;
        $concluded = $this->concludedShowIds;

        $counts = DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->where('watched_episodes.user_id', $userId)
            ->groupBy('episodes.show_id')
            ->selectRaw('episodes.show_id as show_id, count(*) as c')
            ->pluck('c', 'show_id');

        return UserShow::query()
            ->with('show')
            ->where('user_id', $userId)
            ->when($this->status === 'watchlist', fn ($q) => $q->whereNotIn('show_id', $started))
            ->when($this->status === 'in_progress', fn ($q) => $q->whereIn('show_id', $started)->whereNotIn('show_id', $concluded))
            ->when($this->status === 'done', fn ($q) => $q->whereIn('show_id', $concluded))
            ->when($this->search !== '', fn ($q) => $q->whereHas('show', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%')))
            ->get()
            ->map(fn (UserShow $us) => [
                'type' => 'series',
                'title' => $us->show->name,
                'poster' => $us->show->poster_path,
                'href' => route('shows.show', $us->show),
                'meta' => ($counts[$us->show_id] ?? 0).' '.__('episodi visti'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function movieItems()
    {
        $movieStatus = $this->status === 'watchlist' ? 'watchlist' : 'watched';

        return UserMovie::query()
            ->with('movie')
            ->where('user_id', Auth::id())
            ->where('status', $movieStatus)
            ->when($this->search !== '', fn ($q) => $q->whereHas('movie', fn ($m) => $m->where('title', 'like', '%'.$this->search.'%')))
            ->get()
            ->map(fn (UserMovie $um) => [
                'type' => 'movie',
                'title' => $um->movie->title,
                'poster' => $um->movie->poster_path,
                'href' => route('movies.show', $um->movie),
                'meta' => $um->movie->release_date?->year ? (string) $um->movie->release_date->year : __('Film'),
            ]);
    }

    /**
     * Conteggi per stato, coerenti col filtro tipo attivo.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function statusCounts(): array
    {
        $userId = Auth::id();
        $started = $this->startedShowIds;

        $movies = UserMovie::where('user_id', $userId)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $seriesTotal = UserShow::where('user_id', $userId)->count();
        $seriesStarted = UserShow::where('user_id', $userId)->whereIn('show_id', $started)->count();
        $seriesDone = UserShow::where('user_id', $userId)->whereIn('show_id', $this->concludedShowIds)->count();
        $seriesInProgress = max(0, $seriesStarted - $seriesDone);
        $seriesNotStarted = max(0, $seriesTotal - $seriesStarted);

        $wantSeries = $this->type !== 'movies';
        $wantMovies = $this->type !== 'series';

        return [
            'watchlist' => ($wantSeries ? $seriesNotStarted : 0) + ($wantMovies ? (int) ($movies['watchlist'] ?? 0) : 0),
            'in_progress' => $wantSeries ? $seriesInProgress : 0,
            'done' => ($wantSeries ? $seriesDone : 0) + ($wantMovies ? (int) ($movies['watched'] ?? 0) : 0),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Libreria') }}</flux:heading>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="{{ __('Cerca...') }}" class="max-w-xs" />
    </div>

    @php
        $statusOptions = match ($type) {
            'movies' => ['watchlist' => 'Da vedere', 'done' => 'Visti'],
            default => ['watchlist' => 'Da iniziare', 'in_progress' => 'In corso', 'done' => 'Concluse'],
        };
    @endphp

    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="flex gap-2">
            @foreach (['series' => 'Serie', 'movies' => 'Film'] as $key => $label)
                <flux:button size="sm" wire:click="$set('type', '{{ $key }}')"
                    :variant="$type === $key ? 'primary' : 'outline'">{{ __($label) }}</flux:button>
            @endforeach
        </div>

        <flux:separator vertical class="h-6 max-sm:hidden" />

        <div class="flex flex-wrap gap-2">
            @foreach ($statusOptions as $key => $label)
                <flux:button size="sm" wire:click="$set('status', '{{ $key }}')"
                    :variant="$status === $key ? 'primary' : 'outline'">
                    {{ __($label) }}
                    <span class="ml-1.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[11px] font-medium tabular-nums {{ $status === $key ? 'bg-[var(--color-accent-foreground)]/20 text-[var(--color-accent-foreground)]' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">
                        {{ $this->statusCounts[$key] ?? 0 }}
                    </span>
                </flux:button>
            @endforeach
        </div>
    </div>

    @if ($this->items->isEmpty())
        <flux:text class="py-12 text-center">{{ __('Niente in questa sezione.') }}</flux:text>
    @else
        <div class="grid grid-cols-3 gap-4 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            @foreach ($this->items as $item)
                @php $inner = view('partials.library-card', ['item' => $item])->render(); @endphp
                @if ($item['href'])
                    <flux:link :href="$item['href']" wire:navigate class="group flex flex-col gap-2 no-underline">{!! $inner !!}</flux:link>
                @else
                    <div class="group flex flex-col gap-2">{!! $inner !!}</div>
                @endif
            @endforeach
        </div>
    @endif
</div>
