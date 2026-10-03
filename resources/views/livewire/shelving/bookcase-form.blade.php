<?php

use App\Models\Bookcase;
use App\Models\Room;
use App\Models\User;
use App\Services\BookcaseService;
use App\Services\RoomService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public ?Bookcase $bookcase = null;

    /** Owner of the library the bookcase belongs to. */
    #[Locked]
    public User $owner;

    public string $name = '';

    public ?int $roomId = null;

    /** @var list<array{id: ?int, name: ?string, compartment_count: int, compartment_names: list<?string>}> */
    public array $shelves = [];

    public function mount(?Room $room = null, ?Bookcase $bookcase = null, BookcaseService $bookcases, RoomService $rooms): void
    {
        if ($bookcase?->exists) {
            $this->authorize('update', $bookcase);

            $this->bookcase = $bookcase;
            $this->owner = $bookcases->ownerOf($bookcase);
            $this->name = $bookcase->name;
            $this->roomId = $bookcase->room_id;
            $this->shelves = array_map(fn (array $shelf) => [
                'id' => $shelf['id'],
                'name' => $shelf['name'] ?? '',
                'compartment_count' => count($shelf['compartment_names']),
                'compartment_names' => array_map(fn (?string $name) => $name ?? '', $shelf['compartment_names']),
            ], $bookcases->structureOf($bookcase));

            return;
        }

        $this->authorize('update', $room);

        $this->owner = $rooms->ownerOf($room);
        $this->roomId = $room->id;
        $this->shelves = [$this->emptyShelf()];
    }

    public function addShelf(): void
    {
        $this->shelves[] = $this->emptyShelf();
    }

    public function removeShelf(int $index): void
    {
        unset($this->shelves[$index]);
        $this->shelves = array_values($this->shelves);
    }

    /**
     * Shelves keep their id when they move, so their books go with them on save (spec 004, RF-08).
     */
    public function moveShelfUp(int $index): void
    {
        $this->swapShelves($index, $index - 1);
    }

    public function moveShelfDown(int $index): void
    {
        $this->swapShelves($index, $index + 1);
    }

    public function save(BookcaseService $bookcases): void
    {
        $this->bookcase
            ? $this->authorize('update', $this->bookcase)
            : $this->authorize('manageLibrary', $this->owner);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'roomId' => ['required', 'integer', Rule::exists('rooms', 'id')->where('user_id', $this->owner->id)],
            'shelves' => ['required', 'array', 'min:1'],
            'shelves.*.id' => ['nullable', 'integer'],
            'shelves.*.name' => ['nullable', 'string', 'max:255'],
            'shelves.*.compartment_count' => ['required', 'integer', 'min:1'],
            'shelves.*.compartment_names' => ['array'],
            'shelves.*.compartment_names.*' => ['nullable', 'string', 'max:255'],
        ]);

        $structure = array_map(fn (array $shelf) => [
            // The shelf identity travels with it; without "id" the service matches shelves by number.
            ...(array_key_exists('id', $shelf) ? ['id' => $shelf['id'] ? (int) $shelf['id'] : null] : []),
            'name' => $shelf['name'] ?? null,
            'compartment_names' => array_slice(
                array_pad($shelf['compartment_names'] ?? [], (int) $shelf['compartment_count'], null),
                0,
                (int) $shelf['compartment_count'],
            ),
        ], $validated['shelves']);

        $room = Room::findOrFail($validated['roomId']);

        $this->bookcase
            ? $bookcases->update($this->bookcase, $room, $validated['name'], $structure)
            : $bookcases->create($room, $validated['name'], $structure);

        $this->redirect(
            $this->owner->is(Auth::user()) ? route('rooms.index') : route('admin.users.rooms', $this->owner),
            navigate: true,
        );
    }

    public function rendering($view): void
    {
        $view->title($this->bookcase ? __('Editar estantería') : __('Nueva estantería'));
    }

    public function with(RoomService $rooms): array
    {
        return ['roomOptions' => $rooms->roomsOf($this->owner)];
    }

    private function emptyShelf(): array
    {
        return ['id' => null, 'name' => '', 'compartment_count' => 1, 'compartment_names' => ['']];
    }

    private function swapShelves(int $from, int $to): void
    {
        if (! isset($this->shelves[$from], $this->shelves[$to])) {
            return;
        }

        [$this->shelves[$from], $this->shelves[$to]] = [$this->shelves[$to], $this->shelves[$from]];
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <flux:heading size="xl" level="1">{{ $bookcase ? __('Editar estantería') : __('Nueva estantería') }}</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Nombre')" required />

            <flux:select wire:model="roomId" :label="__('Sala')">
                @foreach ($roomOptions as $roomOption)
                    <flux:select.option value="{{ $roomOption->id }}">{{ $roomOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <fieldset class="space-y-4">
            <legend class="font-serif text-2xl font-semibold">{{ __('Baldas') }}</legend>
            <p class="text-sm text-ink-muted">{{ __('Las baldas se numeran de arriba abajo y los huecos de izquierda a derecha. Los nombres son opcionales.') }}</p>

            <flux:error name="shelves" />

            @foreach ($shelves as $index => $shelf)
                <div wire:key="shelf-{{ $index }}" class="rounded-lg border border-zinc-200 bg-surface p-4 dark:border-zinc-700">
                    <div class="flex flex-wrap items-end gap-3">
                        <p class="w-full font-semibold sm:w-auto sm:self-center">{{ __('Balda :number', ['number' => $index + 1]) }}</p>

                        <div class="min-w-0 flex-1">
                            <flux:input wire:model="shelves.{{ $index }}.name" :label="__('Nombre de la balda :number (opcional)', ['number' => $index + 1])" />
                        </div>

                        <div class="w-36">
                            <flux:input type="number" min="1" wire:model.blur="shelves.{{ $index }}.compartment_count" :label="__('Huecos')" />
                        </div>

                        {{-- Move the shelf with its compartments and books (spec 004, RF-08). --}}
                        <div class="flex gap-1">
                            <flux:button type="button" size="sm" variant="ghost" icon="arrow-up" wire:click="moveShelfUp({{ $index }})" :disabled="$loop->first">
                                <span class="sr-only">{{ __('Subir') }} {{ __('balda :number', ['number' => $index + 1]) }}</span>
                            </flux:button>
                            <flux:button type="button" size="sm" variant="ghost" icon="arrow-down" wire:click="moveShelfDown({{ $index }})" :disabled="$loop->last">
                                <span class="sr-only">{{ __('Bajar') }} {{ __('balda :number', ['number' => $index + 1]) }}</span>
                            </flux:button>
                        </div>

                        @if (count($shelves) > 1)
                            <flux:button type="button" size="sm" variant="ghost" wire:click="removeShelf({{ $index }})">
                                {{ __('Quitar') }}<span class="sr-only"> {{ __('balda :number', ['number' => $index + 1]) }}</span>
                            </flux:button>
                        @endif
                    </div>

                    <flux:error name="shelves.{{ $index }}.compartment_count" />

                    @if ((int) $shelf['compartment_count'] > 0)
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm text-leather underline underline-offset-2">
                                {{ __('Nombres de los huecos de la balda :number', ['number' => $index + 1]) }}
                            </summary>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                @for ($compartment = 0; $compartment < (int) $shelf['compartment_count']; $compartment++)
                                    <flux:input
                                        wire:key="shelf-{{ $index }}-compartment-{{ $compartment }}"
                                        wire:model="shelves.{{ $index }}.compartment_names.{{ $compartment }}"
                                        :label="__('Hueco :number', ['number' => $compartment + 1])"
                                    />
                                @endfor
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach

            <flux:button type="button" icon="plus" wire:click="addShelf">{{ __('Añadir balda') }}</flux:button>
        </fieldset>

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('Guardar estantería') }}</flux:button>
            <flux:button :href="$owner->is(auth()->user()) ? route('rooms.index') : route('admin.users.rooms', $owner)" wire:navigate>{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</section>
