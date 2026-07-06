<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\UserList;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public UserList $userList;

    public function mount(UserList $userList): void
    {
        abort_unless($userList->user_id === Auth::id(), 403);

        $this->userList = $userList;
    }

    /**
     * Serie e film della lista, uniti e ordinati per titolo.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function items()
    {
        $shows = $this->userList->shows()->get()->map(fn (Show $s) => [
            'type' => 'series',
            'id' => $s->id,
            'title' => $s->name,
            'poster' => $s->poster_path,
            'href' => route('shows.show', $s),
            'meta' => __('Serie'),
        ])->toBase();

        $movies = $this->userList->movies()->get()->map(fn (Movie $m) => [
            'type' => 'movie',
            'id' => $m->id,
            'title' => $m->title,
            'poster' => $m->poster_path,
            'href' => route('movies.show', $m),
            'meta' => __('Film'),
        ])->toBase();

        return $shows->merge($movies)->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function remove(string $type, int $id): void
    {
        $type === 'movie'
            ? $this->userList->movies()->detach($id)
            : $this->userList->shows()->detach($id);

        unset($this->items);
    }

    public function delete(): void
    {
        $this->userList->delete();

        $this->redirectRoute('lists', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:button :href="route('lists')" wire:navigate variant="outline" size="sm" icon="arrow-left" class="self-start">
        {{ __('Liste') }}
    </flux:button>

    <div class="flex items-center justify-between gap-3">
        <flux:heading size="xl" class="truncate">{{ $userList->name }}</flux:heading>
        <flux:button variant="outline" size="sm" icon="trash" class="shrink-0"
            wire:click="delete"
            wire:confirm="{{ __('Eliminare la lista «:name»?', ['name' => $userList->name]) }}"
            aria-label="{{ __('Elimina lista') }}" />
    </div>

    @if ($this->items->isEmpty())
        <flux:text class="py-12 text-center text-zinc-500">
            {{ __('Lista vuota. Aggiungi serie o film dalle loro pagine.') }}
        </flux:text>
    @else
        <div class="grid grid-cols-3 gap-4 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            @foreach ($this->items as $item)
                <div class="group relative flex flex-col gap-2">
                    <flux:link :href="$item['href']" wire:navigate class="flex flex-col gap-2 no-underline">
                        @include('partials.library-card', ['item' => $item])
                    </flux:link>
                    <flux:button class="absolute right-1.5 top-1.5" size="xs" variant="danger" icon="x-mark"
                        wire:click="remove('{{ $item['type'] }}', {{ $item['id'] }})"
                        aria-label="{{ __('Rimuovi dalla lista') }}" />
                </div>
            @endforeach
        </div>
    @endif
</div>
