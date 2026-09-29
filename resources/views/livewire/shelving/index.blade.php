<?php

use App\Models\Bookcase;
use App\Models\Room;
use App\Models\User;
use App\Services\BookcaseService;
use App\Services\RoomService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Salas y estanterías')] class extends Component {
    /** Owner of the library being managed: the current user, or another user for administrators. */
    #[Locked]
    public User $owner;

    public string $newRoomName = '';

    #[Locked]
    public ?int $renamingRoomId = null;

    public string $renamingRoomName = '';

    public function mount(?User $user = null): void
    {
        $this->owner = $user?->exists ? $user : Auth::user();

        $this->authorize('manageLibrary', $this->owner);
    }

    public function createRoom(RoomService $rooms): void
    {
        $this->authorize('manageLibrary', $this->owner);

        $this->validate(['newRoomName' => ['required', 'string', 'max:255']]);

        $rooms->create($this->owner, $this->newRoomName);

        $this->reset('newRoomName');
    }

    public function startRenaming(int $roomId): void
    {
        $room = Room::findOrFail($roomId);

        $this->authorize('update', $room);

        $this->renamingRoomId = $room->id;
        $this->renamingRoomName = $room->name;
    }

    public function cancelRenaming(): void
    {
        $this->reset('renamingRoomId', 'renamingRoomName');
    }

    public function renameRoom(RoomService $rooms): void
    {
        $room = Room::findOrFail($this->renamingRoomId);

        $this->authorize('update', $room);

        $this->validate(['renamingRoomName' => ['required', 'string', 'max:255']]);

        $rooms->rename($room, $this->renamingRoomName);

        $this->cancelRenaming();
    }

    public function deleteRoom(int $roomId, RoomService $rooms): void
    {
        $room = Room::findOrFail($roomId);

        $this->authorize('delete', $room);

        $rooms->delete($room);
    }

    public function deleteBookcase(int $bookcaseId, BookcaseService $bookcases): void
    {
        $bookcase = Bookcase::findOrFail($bookcaseId);

        $this->authorize('delete', $bookcase);

        $bookcases->delete($bookcase);
    }

    public function with(RoomService $rooms): array
    {
        return [
            'rooms' => $rooms->roomsOf($this->owner),
            'isOwnLibrary' => $this->owner->is(Auth::user()),
        ];
    }
}; ?>

<section class="w-full space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Salas y estanterías') }}</flux:heading>
        @unless ($isOwnLibrary)
            <flux:subheading>{{ __('Biblioteca de :name', ['name' => $owner->name]) }}</flux:subheading>
        @endunless
    </div>

    <form wire:submit="createRoom" class="flex max-w-lg flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <flux:input wire:model="newRoomName" :label="__('Nueva sala')" :placeholder="__('Por ejemplo, Salón')" />
        </div>
        <flux:button type="submit" variant="primary">{{ __('Crear sala') }}</flux:button>
    </form>

    @forelse ($rooms as $room)
        <article wire:key="room-{{ $room->id }}" class="rounded-lg border border-zinc-200 bg-surface p-5 shadow-sm dark:border-zinc-700" aria-labelledby="room-{{ $room->id }}-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                @if ($renamingRoomId === $room->id)
                    <form wire:submit="renameRoom" class="flex flex-1 flex-wrap items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <flux:input wire:model="renamingRoomName" :label="__('Nombre de la sala')" autofocus />
                        </div>
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Guardar') }}</flux:button>
                        <flux:button type="button" size="sm" wire:click="cancelRenaming">{{ __('Cancelar') }}</flux:button>
                    </form>
                @else
                    <flux:heading size="lg" level="2" id="room-{{ $room->id }}-heading">{{ $room->name }}</flux:heading>

                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" :href="route('bookcases.create', $room)" wire:navigate>
                            {{ __('Nueva estantería') }}<span class="sr-only"> {{ __('en :room', ['room' => $room->name]) }}</span>
                        </flux:button>
                        <flux:button size="sm" wire:click="startRenaming({{ $room->id }})">
                            {{ __('Renombrar') }}<span class="sr-only"> {{ $room->name }}</span>
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="danger"
                            wire:click="deleteRoom({{ $room->id }})"
                            wire:confirm="{{ __('¿Eliminar la sala :room? Se eliminarán también sus estanterías y sus libros pasarán a la mesa.', ['room' => $room->name]) }}"
                        >
                            {{ __('Eliminar') }}<span class="sr-only"> {{ $room->name }}</span>
                        </flux:button>
                    </div>
                @endif
            </div>

            @if ($room->bookcases->isEmpty())
                <p class="mt-4 text-sm text-ink-muted">{{ __('Esta sala todavía no tiene estanterías.') }}</p>
            @else
                <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($room->bookcases as $bookcase)
                        <li wire:key="bookcase-{{ $bookcase->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <p class="font-semibold">{{ $bookcase->name }}</p>
                                <p class="text-sm text-ink-muted">
                                    {{ trans_choice(':count balda|:count baldas', $bookcase->shelves_count) }}
                                    · {{ trans_choice(':count hueco|:count huecos', $bookcase->compartments_count) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <flux:button size="sm" :href="route('bookcases.edit', $bookcase)" wire:navigate>
                                    {{ __('Editar') }}<span class="sr-only"> {{ $bookcase->name }}</span>
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="deleteBookcase({{ $bookcase->id }})"
                                    wire:confirm="{{ __('¿Eliminar la estantería :bookcase? Sus libros pasarán a la mesa.', ['bookcase' => $bookcase->name]) }}"
                                >
                                    {{ __('Eliminar') }}<span class="sr-only"> {{ $bookcase->name }}</span>
                                </flux:button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>
    @empty
        <p class="text-ink-muted">{{ __('Todavía no hay salas. Crea la primera para empezar a organizar tus estanterías.') }}</p>
    @endforelse
</section>
