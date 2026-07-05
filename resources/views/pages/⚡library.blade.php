<?php

use App\Models\UserMovie;
use App\Models\UserShow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Libreria')] class extends Component {
    public string $search = '';

    public string $type = 'all';

    public string $status = 'library';

    /**
     * Serie e film uniti in un'unica lista, secondo i filtri tipo/stato.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function items()
    {
        $items = collect();

        if ($this->type !== 'movies' && $this->status !== null) {
            $items = $items->merge($this->seriesItems());
        }

        if ($this->type !== 'series' && $this->status !== 'archived') {
            $items = $items->merge($this->movieItems());
        }

        return $items->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function seriesItems()
    {
        $seriesStatus = match ($this->status) {
            'watchlist' => 'watchlist',
            'archived' => 'archived',
            default => 'following',
        };

        $counts = DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->where('watched_episodes.user_id', Auth::id())
            ->groupBy('episodes.show_id')
            ->selectRaw('episodes.show_id as show_id, count(*) as c')
            ->pluck('c', 'show_id');

        return UserShow::query()
            ->with('show')
            ->where('user_id', Auth::id())
            ->where('status', $seriesStatus)
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
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
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
        $series = UserShow::where('user_id', Auth::id())
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $movies = UserMovie::where('user_id', Auth::id())
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $wantSeries = $this->type !== 'movies';
        $wantMovies = $this->type !== 'series';

        return [
            'library' => ($wantSeries ? (int) ($series['following'] ?? 0) : 0) + ($wantMovies ? (int) ($movies['watched'] ?? 0) : 0),
            'watchlist' => ($wantSeries ? (int) ($series['watchlist'] ?? 0) : 0) + ($wantMovies ? (int) ($movies['watchlist'] ?? 0) : 0),
            'archived' => $wantSeries ? (int) ($series['archived'] ?? 0) : 0,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Libreria') }}</flux:heading>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="{{ __('Cerca...') }}" class="max-w-xs" />
    </div>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="flex gap-2">
            @foreach (['all' => 'Tutti', 'series' => 'Serie', 'movies' => 'Film'] as $key => $label)
                <flux:button size="sm" wire:click="$set('type', '{{ $key }}')"
                    :variant="$type === $key ? 'primary' : 'ghost'">{{ __($label) }}</flux:button>
            @endforeach
        </div>

        <flux:separator vertical class="h-6 max-sm:hidden" />

        <div class="flex gap-2">
            @foreach (['library' => 'In libreria', 'watchlist' => 'Da vedere', 'archived' => 'Archiviate'] as $key => $label)
                @if ($key !== 'archived' || $type !== 'movies')
                    <flux:button size="sm" wire:click="$set('status', '{{ $key }}')"
                        :variant="$status === $key ? 'primary' : 'ghost'">
                        {{ __($label) }}
                        <span class="ml-1.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[11px] font-medium tabular-nums {{ $status === $key ? 'bg-white/25 text-white' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">
                            {{ $this->statusCounts[$key] ?? 0 }}
                        </span>
                    </flux:button>
                @endif
            @endforeach
        </div>
    </div>

    @if ($this->items->isEmpty())
        <flux:text class="py-12 text-center">{{ __('Niente in questa sezione.') }}</flux:text>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
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
