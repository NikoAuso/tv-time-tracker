<?php

use App\Models\UserList;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Liste')] class extends Component {
    public string $newName = '';

    /**
     * @return \Illuminate\Support\Collection<int, UserList>
     */
    #[Computed]
    public function lists()
    {
        return UserList::query()
            ->where('user_id', Auth::id())
            ->withCount(['shows', 'movies'])
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $validated = $this->validate(
            ['newName' => ['required', 'string', 'max:60']],
            ['newName.required' => __('Dai un nome alla lista.')],
        );

        UserList::firstOrCreate([
            'user_id' => Auth::id(),
            'name' => trim($validated['newName']),
        ]);

        $this->reset('newName');
        unset($this->lists);
    }

    public function delete(int $id): void
    {
        UserList::where('user_id', Auth::id())->whereKey($id)->delete();

        unset($this->lists);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Liste') }}</flux:heading>

    <form wire:submit="create" class="flex items-end gap-3">
        <flux:input wire:model="newName" :label="__('Nuova lista')" placeholder="{{ __('Es. Da recuperare') }}"
            class="max-w-xs" />
        <flux:button type="submit" variant="primary" icon="plus">{{ __('Crea') }}</flux:button>
    </form>
    <flux:error name="newName" />

    @if ($this->lists->isEmpty())
        <flux:text class="py-12 text-center text-zinc-500">{{ __('Nessuna lista. Creane una qui sopra.') }}</flux:text>
    @else
        <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($this->lists as $list)
                <div class="flex items-center gap-3 py-3">
                    <flux:link :href="route('lists.show', $list)" wire:navigate
                        class="flex min-w-0 flex-1 items-center gap-3 no-underline">
                        <flux:icon.list-bullet class="size-5 shrink-0 text-zinc-400" />
                        <flux:heading size="sm" class="truncate">{{ $list->name }}</flux:heading>
                        <flux:text size="sm" class="shrink-0 text-zinc-500">
                            {{ $list->shows_count + $list->movies_count }} {{ __('elementi') }}
                        </flux:text>
                    </flux:link>
                    <flux:button size="xs" variant="outline" icon="trash" wire:click="delete({{ $list->id }})"
                        wire:confirm="{{ __('Eliminare la lista :name?', ['name' => $list->name]) }}"
                        aria-label="{{ __('Elimina') }}" />
                </div>
            @endforeach
        </div>
    @endif
</div>
