<?php

use App\Models\Book;
use App\Models\Compartment;
use App\Models\Shelf;
use App\Models\User;
use App\Services\BookcaseService;
use App\Services\BookLocationService;
use App\Services\ShelfViewService;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    /** Owner of the library whose bookcases are drawn. */
    #[Locked]
    public User $owner;

    public ?int $roomId = null;

    public ?int $bookcaseId = null;

    public int $tablePage = 1;

    /** "Mover a…" dialog. */
    #[Locked]
    public ?int $movingBookId = null;

    public bool $moveToTable = false;
    public ?int $moveBookcaseId = null;
    public ?int $moveShelfId = null;
    public ?int $moveCompartmentId = null;
    public ?int $movePosition = null;

    /** "Seleccionar y colocar": the book waiting for a destination. */
    #[Locked]
    public ?int $selectedBookId = null;

    /** Last change, read out by screen readers. */
    public string $announcement = '';

    /**
     * @param  int|null  $bookcase  bookcase to show first (links from "Salas y estanterías", spec 005 M-06/M-07)
     */
    public function mount(User $owner, ShelfViewService $shelfView, ?int $bookcase = null): void
    {
        $this->authorize('manageLibrary', $owner);

        $this->owner = $owner;

        $bookcases = $shelfView->bookcasesOf($owner);
        $this->bookcaseId = $bookcases->firstWhere('id', $bookcase)?->id ?? $bookcases->first()?->id;
    }

    public function updatedRoomId(ShelfViewService $shelfView): void
    {
        $this->bookcaseId = $shelfView->bookcasesOf($this->owner, $this->roomId)->first()?->id;
    }

    public function updatedMoveBookcaseId(): void
    {
        $this->reset('moveShelfId', 'moveCompartmentId');
    }

    public function updatedMoveShelfId(): void
    {
        $this->reset('moveCompartmentId');
    }

    /**
     * Single entry point of every move: drag and drop, "Mover a…" and "Seleccionar y colocar".
     */
    public function moveBook(int $bookId, ?int $compartmentId, ?int $position, BookLocationService $locations): void
    {
        $book = Book::findOrFail($bookId);

        $this->authorize('update', $book);

        $compartment = $compartmentId ? Compartment::findOrFail($compartmentId) : null;

        try {
            $locations->move($book, $compartment, $position);
        } catch (InvalidArgumentException) {
            $this->addError('move', __('Ese hueco no pertenece a esta biblioteca.'));

            return;
        }

        $book->refresh();

        $this->announcement = $compartment
            ? __('«:title» colocado en :place, posición :position.', ['title' => $book->title, 'place' => $locations->compartmentLabel($compartment), 'position' => $book->position])
            : __('«:title» está ahora en la mesa.', ['title' => $book->title]);
    }

    /**
     * Move a shelf, with its compartments and books, to another position (spec 004, RF-08).
     * Used by dragging the shelf handle and by its "Subir balda" / "Bajar balda" buttons.
     */
    public function moveShelf(int $shelfId, int $position, BookcaseService $bookcases): void
    {
        $shelf = Shelf::with('bookcase')->findOrFail($shelfId);

        $this->authorize('update', $shelf->bookcase);

        $bookcases->moveShelf($shelf, $position);

        $shelf->refresh();
        $this->announcement = __('Balda movida a la posición :position, con todos sus libros.', ['position' => $shelf->number])
            .($shelf->name ? ' ('.$shelf->name.')' : '');
    }

    public function openMoveDialog(int $bookId): void
    {
        $this->authorize('update', Book::findOrFail($bookId));

        $this->resetErrorBag();
        $this->movingBookId = $bookId;
        $this->moveToTable = false;
        $this->moveBookcaseId = $this->bookcaseId;
        $this->reset('moveShelfId', 'moveCompartmentId', 'movePosition');
    }

    public function closeMoveDialog(): void
    {
        if ($this->movingBookId !== null) {
            $this->dispatch('shelf-focus-book', id: $this->movingBookId);
        }

        $this->reset('movingBookId', 'moveToTable', 'moveBookcaseId', 'moveShelfId', 'moveCompartmentId', 'movePosition');
    }

    public function confirmMove(BookLocationService $locations): void
    {
        $validated = $this->validate([
            'moveToTable' => ['boolean'],
            'moveCompartmentId' => ['exclude_if:moveToTable,true', 'required', 'integer'],
            'movePosition' => ['exclude_if:moveToTable,true', 'nullable', 'integer', 'min:1'],
        ]);

        $this->moveBook(
            $this->movingBookId,
            $this->moveToTable ? null : $validated['moveCompartmentId'],
            $this->moveToTable ? null : ($validated['movePosition'] ?? null),
            $locations,
        );

        if (! $this->getErrorBag()->has('move')) {
            $this->closeMoveDialog();
        }
    }

    public function selectBook(int $bookId): void
    {
        $book = Book::findOrFail($bookId);

        $this->authorize('update', $book);

        $this->selectedBookId = $book->id;
        $this->announcement = __('«:title» seleccionado. Elige dónde colocarlo o pulsa Escape para cancelar.', ['title' => $book->title]);
    }

    public function placeSelected(?int $compartmentId, ?int $position, BookLocationService $locations): void
    {
        if ($this->selectedBookId === null) {
            return;
        }

        $this->moveBook($this->selectedBookId, $compartmentId, $position, $locations);
        $this->dispatch('shelf-focus-book', id: $this->selectedBookId);
        $this->selectedBookId = null;
    }

    public function cancelSelection(): void
    {
        if ($this->selectedBookId !== null) {
            $this->dispatch('shelf-focus-book', id: $this->selectedBookId);
            $this->selectedBookId = null;
            $this->announcement = __('Selección cancelada.');
        }
    }

    public function nextTablePage(ShelfViewService $shelfView): void
    {
        $this->tablePage = min($this->tablePage + 1, $shelfView->tableBooks($this->owner, 1)->lastPage());
    }

    public function previousTablePage(): void
    {
        $this->tablePage = max(1, $this->tablePage - 1);
    }

    public function with(ShelfViewService $shelfView, BookLocationService $locations): array
    {
        $bookcases = $shelfView->bookcasesOf($this->owner, $this->roomId);
        $bookcase = $bookcases->firstWhere('id', $this->bookcaseId);

        $tableBooks = $shelfView->tableBooks($this->owner, $this->tablePage);
        if ($tableBooks->isEmpty() && $this->tablePage > 1) {
            $this->tablePage = max(1, $tableBooks->lastPage());
            $tableBooks = $shelfView->tableBooks($this->owner, $this->tablePage);
        }

        return [
            'rooms' => $locations->roomOptions($this->owner),
            'bookcases' => $bookcases,
            'bookcase' => $bookcase ? $shelfView->layout($bookcase) : null,
            'tableBooks' => $tableBooks,
            'movingBook' => $this->movingBookId ? Book::find($this->movingBookId) : null,
            'selectedBook' => $this->selectedBookId ? Book::find($this->selectedBookId) : null,
            'moveBookcases' => $shelfView->bookcasesOf($this->owner),
            'moveShelves' => $locations->shelfOptions($this->owner, $this->moveBookcaseId),
            'moveCompartments' => $locations->compartmentOptions($this->owner, $this->moveShelfId),
            'locations' => $locations,
        ];
    }
}; ?>

<div
    data-shelf-view
    class="space-y-6"
    x-data="{
        draggedBookId: null,
        // Books travel as text/plain and shelves as application/x-shelf, so each drop zone only reacts to its kind.
        dropInCompartment(event, compartmentId) {
            const bookId = Number(event.dataTransfer.getData('text/plain'));
            if (! bookId) return;
            const spines = [...event.currentTarget.querySelectorAll('[data-spine]')].filter(spine => Number(spine.dataset.spine) !== bookId);
            const index = spines.findIndex(spine => {
                const box = spine.getBoundingClientRect();
                return event.clientX < box.left + box.width / 2;
            });
            $wire.moveBook(bookId, compartmentId, index === -1 ? spines.length + 1 : index + 1);
        },
        dropOnTable(event) {
            const bookId = Number(event.dataTransfer.getData('text/plain'));
            if (bookId) $wire.moveBook(bookId, null, null);
        },
        dropShelf(event, targetNumber) {
            const shelfId = Number(event.dataTransfer.getData('application/x-shelf'));
            if (shelfId) $wire.moveShelf(shelfId, targetNumber);
        },
    }"
    x-on:keydown.escape.window="if ($wire.selectedBookId !== null) $wire.cancelSelection()"
    {{-- Keyboard users keep their place: focus returns to the book after closing the dialog or placing it. --}}
    x-on:shelf-focus-book.window="$nextTick(() => document.querySelector(`[data-spine='${$event.detail.id}']`)?.focus())"
>
    <p class="sr-only" role="status" aria-live="polite">{{ $announcement }}</p>

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-56">
            <flux:select wire:model.live="roomId" :label="__('Sala')">
                <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                @foreach ($rooms as $room)
                    <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-72">
            <flux:select wire:model.live="bookcaseId" :label="__('Estantería')">
                @foreach ($bookcases as $bookcaseOption)
                    <flux:select.option value="{{ $bookcaseOption->id }}">{{ $bookcaseOption->name }} ({{ $bookcaseOption->room->name }})</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($selectedBook)
            <div class="flex items-center gap-3 rounded-lg border-2 border-english-green bg-surface px-4 py-2" role="note">
                <span>{{ __('Colocando «:title»: elige un hueco o la mesa.', ['title' => $selectedBook->title]) }}</span>
                <flux:button size="sm" wire:click="cancelSelection">{{ __('Cancelar (Esc)') }}</flux:button>
            </div>
        @endif
    </div>

    <flux:error name="move" />

    <div class="flex items-start gap-8">
        {{-- The bookcase --}}
        <div class="min-w-0 flex-1">
            @if ($bookcase)
                <section aria-labelledby="bookcase-heading" class="rounded-md border-8 border-[#6b4a2f] bg-[#3b2a1c] p-2 shadow-lg">
                    <h2 id="bookcase-heading" class="sr-only">{{ $bookcase->name }}</h2>

                    @foreach ($bookcase->shelves as $shelf)
                        @php($shelfLabel = $locations->numberedName(__('Balda :number', ['number' => $shelf->number]), $shelf->name))
                        <div
                            wire:key="shelf-{{ $shelf->id }}"
                            class="mb-2 last:mb-0"
                            x-on:dragover.prevent
                            x-on:drop="dropShelf($event, {{ $shelf->number }})"
                        >
                            {{-- Shelf header: drag handle and keyboard alternative to move the whole shelf (spec 004, RF-08). --}}
                            <div class="flex items-center gap-1 px-1 pb-1">
                                <button
                                    type="button"
                                    data-shelf-handle
                                    draggable="true"
                                    x-on:dragstart.stop="$event.dataTransfer.setData('application/x-shelf', '{{ $shelf->id }}'); $event.dataTransfer.effectAllowed = 'move'"
                                    class="cursor-grab rounded px-1 text-[#e6d9c0] hover:bg-[#f6efe0]/10"
                                    aria-label="{{ __('Arrastrar :shelf para moverla', ['shelf' => $shelfLabel]) }}"
                                >
                                    <flux:icon.bars-3 variant="micro" aria-hidden="true" />
                                </button>

                                <h3 class="flex-1 font-sans text-xs font-semibold tracking-wide text-[#f2e8d5]">{{ $shelfLabel }}</h3>

                                <button
                                    type="button"
                                    wire:click="moveShelf({{ $shelf->id }}, {{ $shelf->number - 1 }})"
                                    @disabled($loop->first)
                                    class="rounded px-1 text-[#e6d9c0] hover:bg-[#f6efe0]/10 disabled:opacity-40"
                                >
                                    <flux:icon.arrow-up variant="micro" aria-hidden="true" />
                                    <span class="sr-only">{{ __('Subir balda') }} {{ $shelfLabel }}</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="moveShelf({{ $shelf->id }}, {{ $shelf->number + 1 }})"
                                    @disabled($loop->last)
                                    class="rounded px-1 text-[#e6d9c0] hover:bg-[#f6efe0]/10 disabled:opacity-40"
                                >
                                    <flux:icon.arrow-down variant="micro" aria-hidden="true" />
                                    <span class="sr-only">{{ __('Bajar balda') }} {{ $shelfLabel }}</span>
                                </button>
                            </div>

                            <div class="flex gap-2 border-b-8 border-[#8a6440]">
                                @foreach ($shelf->compartments as $compartment)
                                    @php($compartmentLabel = $locations->compartmentLabel($compartment))
                                    <div
                                        wire:key="compartment-{{ $compartment->id }}"
                                        class="flex min-h-48 min-w-24 flex-1 flex-col rounded-t bg-[#f6efe0]/10 px-1 pt-1"
                                        role="group"
                                        aria-label="{{ $compartmentLabel }}"
                                        x-on:dragover.prevent
                                        x-on:drop.prevent="dropInCompartment($event, {{ $compartment->id }})"
                                    >
                                        <p class="text-[0.7rem] text-[#e6d9c0]">
                                            {{ $locations->numberedName(__('Hueco :number', ['number' => $compartment->number]), $compartment->name) }}
                                        </p>

                                        <div class="mt-auto flex flex-wrap items-end gap-1">
                                            @foreach ($compartment->books as $book)
                                                @if ($selectedBook)
                                                    <flux:button size="xs" variant="primary" class="self-center" wire:click="placeSelected({{ $compartment->id }}, {{ $loop->iteration }})">
                                                        <span aria-hidden="true">↓</span><span class="sr-only">{{ __('Colocar aquí') }}: {{ $compartmentLabel }}, {{ __('posición :position', ['position' => $loop->iteration]) }}</span>
                                                    </flux:button>
                                                @endif

                                                @include('livewire.books.partials.spine', ['book' => $book, 'selected' => $selectedBook?->is($book)])
                                            @endforeach

                                            @if ($selectedBook)
                                                <flux:button size="xs" variant="primary" class="self-center" wire:click="placeSelected({{ $compartment->id }}, {{ $compartment->books->count() + 1 }})">
                                                    {{ __('Colocar aquí') }}<span class="sr-only">: {{ $compartmentLabel }}, {{ __('posición :position', ['position' => $compartment->books->count() + 1]) }}</span>
                                                </flux:button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>
            @else
                <p class="text-ink-muted">
                    {{ __('Todavía no hay estanterías en esta biblioteca.') }}
                    <a href="{{ route('rooms.index') }}" class="text-leather underline underline-offset-2" wire:navigate>{{ __('Crea salas y estanterías') }}</a>
                </p>
            @endif
        </div>

        {{-- The table: books without a location --}}
        <section class="w-80 shrink-0" aria-labelledby="table-heading">
            <h2 id="table-heading" class="mb-2 font-serif text-2xl font-semibold">{{ __('Mesa') }}</h2>

            <div x-on:dragover.prevent x-on:drop.prevent="dropOnTable($event)" role="group" aria-label="{{ __('Mesa: libros sin ubicación') }}">
                <div class="flex min-h-44 flex-wrap items-end gap-1 px-2 pb-1">
                    @forelse ($tableBooks as $book)
                        @include('livewire.books.partials.spine', ['book' => $book, 'selected' => $selectedBook?->is($book)])
                    @empty
                        <p class="self-center text-sm text-ink-muted">{{ __('No hay libros sin ubicación.') }}</p>
                    @endforelse
                </div>

                {{-- Tabletop and a bit of body, kept simple on purpose (spec 004). --}}
                <div class="h-4 rounded-sm bg-[#8a6440] shadow" aria-hidden="true"></div>
                <div class="mx-6 flex h-10 justify-between" aria-hidden="true">
                    <span class="w-3 bg-[#6b4a2f]"></span>
                    <span class="w-3 bg-[#6b4a2f]"></span>
                </div>

                @if ($selectedBook && $selectedBook->compartment_id)
                    <flux:button size="sm" variant="primary" class="mt-2" wire:click="placeSelected(null, null)">{{ __('Dejar en la mesa') }}</flux:button>
                @endif
            </div>

            <div class="mt-3 flex items-center justify-between gap-2">
                <flux:button size="sm" icon="arrow-left" wire:click="previousTablePage" :disabled="$tableBooks->onFirstPage()" :aria-label="__('Libros anteriores de la mesa')" />
                <span class="text-sm text-ink-muted">
                    @if ($tableBooks->total() > 0)
                        {{ __(':from–:to de :total', ['from' => $tableBooks->firstItem(), 'to' => $tableBooks->lastItem(), 'total' => $tableBooks->total()]) }}
                    @endif
                </span>
                <flux:button size="sm" icon="arrow-right" wire:click="nextTablePage" :disabled="! $tableBooks->hasMorePages()" :aria-label="__('Libros siguientes de la mesa')" />
            </div>
        </section>
    </div>

    {{-- "Mover a…": keyboard and mouse alternative to dragging (WCAG 2.5.7) --}}
    @if ($movingBook)
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="move-dialog-heading"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
            x-on:keydown.escape.stop="$wire.closeMoveDialog()"
            x-init="$nextTick(() => $el.querySelector('select, input')?.focus())"
        >
            <form wire:submit="confirmMove" class="w-full max-w-md space-y-4 rounded-lg bg-surface p-6 shadow-xl">
                <h2 id="move-dialog-heading" class="font-serif text-2xl font-semibold">{{ __('Mover «:title»', ['title' => $movingBook->title]) }}</h2>

                <flux:checkbox wire:model.live="moveToTable" :label="__('Dejar en la mesa (sin ubicación)')" />

                @unless ($moveToTable)
                    <flux:select wire:model.live="moveBookcaseId" :label="__('Estantería')">
                        <flux:select.option value="">{{ __('Elige una estantería') }}</flux:select.option>
                        @foreach ($moveBookcases as $option)
                            <flux:select.option value="{{ $option->id }}">{{ $option->name }} ({{ $option->room->name }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="moveShelfId" :label="__('Balda')" :disabled="! $moveBookcaseId">
                        <flux:select.option value="">{{ __('Elige una balda') }}</flux:select.option>
                        @foreach ($moveShelves as $option)
                            <flux:select.option value="{{ $option->id }}">{{ $locations->numberedName(__('Balda :number', ['number' => $option->number]), $option->name) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="moveCompartmentId" :label="__('Hueco')" :disabled="! $moveShelfId">
                        <flux:select.option value="">{{ __('Elige un hueco') }}</flux:select.option>
                        @foreach ($moveCompartments as $option)
                            <flux:select.option value="{{ $option->id }}">{{ $locations->numberedName(__('Hueco :number', ['number' => $option->number]), $option->name) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input type="number" min="1" wire:model="movePosition" :label="__('Posición (opcional)')" :description="__('1 es el primero por la izquierda. Si lo dejas vacío, se coloca al final.')" />
                @endunless

                <flux:error name="move" />

                <div class="flex justify-end gap-2">
                    <flux:button type="button" wire:click="closeMoveDialog">{{ __('Cancelar') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Mover') }}</flux:button>
                </div>
            </form>
        </div>
    @endif
</div>
