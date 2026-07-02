<?php

use App\Models\UserShow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Libreria')] class extends Component {
    public string $search = '';

    public string $status = 'following';

    /**
     * Numero di episodi visti per serie, per l'utente corrente.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    #[Computed]
    public function watchedCounts()
    {
        return DB::table('watched_episodes')
            ->join('episodes', 'episodes.id', '=', 'watched_episodes.episode_id')
            ->where('watched_episodes.user_id', Auth::id())
            ->groupBy('episodes.show_id')
            ->selectRaw('episodes.show_id as show_id, count(*) as c')
            ->pluck('c', 'show_id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, UserShow>
     */
    #[Computed]
    public function shows()
    {
        $counts = $this->watchedCounts();

        return UserShow::query()
            ->with('show')
            ->where('user_id', Auth::id())
            ->where('status', $this->status)
            ->when($this->search !== '', fn ($q) => $q->whereHas(
                'show', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%')
            ))
            ->get()
            ->sortByDesc(fn (UserShow $us) => $counts[$us->show_id] ?? 0)
            ->values();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function statusCounts(): array
    {
        return UserShow::where('user_id', Auth::id())
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Libreria') }}</flux:heading>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="{{ __('Cerca una serie...') }}" class="max-w-xs" />
    </div>

    <div class="flex gap-2">
        @foreach (['following' => 'In corso', 'watchlist' => 'Da vedere', 'archived' => 'Archiviate'] as $key => $label)
            <flux:button size="sm" wire:click="$set('status', '{{ $key }}')"
                :variant="$status === $key ? 'primary' : 'ghost'">
                {{ __($label) }}
                <flux:badge size="sm" inset="right">{{ $this->statusCounts[$key] ?? 0 }}</flux:badge>
            </flux:button>
        @endforeach
    </div>

    @if ($this->shows->isEmpty())
        <flux:text class="py-12 text-center">{{ __('Nessuna serie in questa sezione.') }}</flux:text>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($this->shows as $userShow)
                <flux:link :href="route('shows.show', $userShow->show)" wire:navigate
                    class="group flex flex-col gap-2 no-underline">
                    <div class="flex aspect-[2/3] items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 text-3xl font-bold text-zinc-400 transition group-hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500">
                        @if ($userShow->show->poster_path)
                            <img src="https://image.tmdb.org/t/p/w342{{ $userShow->show->poster_path }}"
                                alt="{{ $userShow->show->name }}" class="h-full w-full object-cover" />
                        @else
                            {{ Str::of($userShow->show->name)->trim()->substr(0, 1)->upper() }}
                        @endif
                    </div>
                    <div>
                        <flux:heading size="sm" class="truncate">{{ $userShow->show->name }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ $this->watchedCounts[$userShow->show_id] ?? 0 }} {{ __('episodi visti') }}
                        </flux:text>
                    </div>
                </flux:link>
            @endforeach
        </div>
    @endif
</div>
