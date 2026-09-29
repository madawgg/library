<?php

use App\Enums\BookCondition;
use App\Enums\BookView;
use App\Enums\ReadingStatus;
use App\Models\User;
use App\Services\BookCatalogService;
use App\Services\BookLocationService;
use App\Services\UserPreferenceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /** Library being listed: the current user's, another user's (administrators) or null for every library. */
    #[Locked]
    public ?User $owner = null;

    #[Locked]
    public bool $allLibraries = false;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $genre = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $condition = '';

    #[Url(as: 'room', except: '')]
    public string $roomId = '';

    #[Url(as: 'bookcase', except: '')]
    public string $bookcaseId = '';

    #[Url(as: 'shelf', except: '')]
    public string $shelfId = '';

    #[Url(as: 'compartment', except: '')]
    public string $compartmentId = '';

    #[Url(as: 'owner', except: '')]
    public string $ownerFilter = '';

    #[Url(as: 'vencidos', except: false)]
    public bool $overdueOnly = false;

    #[Url(except: 'created_at')]
    public string $sort = 'created_at';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    /**
     * @param  string|null  $vista  quick access from the home page: "cuadricula", "tabla" or "estanteria"
     */
    public function mount(?User $user = null, bool $allLibraries = false, ?string $vista = null): void
    {
        $this->allLibraries = $allLibraries;

        $requestedView = match ($vista ?? request()->query('vista')) {
            'cuadricula' => BookView::Grid,
            'tabla' => BookView::Table,
            'estanteria' => BookView::Shelf,
            default => null,
        };

        if ($requestedView) {
            app(UserPreferenceService::class)->updateBookView(Auth::user(), $requestedView);
        }

        if ($allLibraries) {
            $this->authorize('viewAny', User::class);

            return;
        }

        $this->owner = $user?->exists ? $user : Auth::user();
        $this->authorize('manageLibrary', $this->owner);
    }

    public function updated(string $property): void
    {
        match ($property) {
            'ownerFilter' => $this->reset('roomId', 'bookcaseId', 'shelfId', 'compartmentId'),
            'roomId' => $this->reset('bookcaseId', 'shelfId', 'compartmentId'),
            'bookcaseId' => $this->reset('shelfId', 'compartmentId'),
            'shelfId' => $this->reset('compartmentId'),
            // Newest first for the creation date, A to Z for texts.
            'sort' => $this->direction = $this->sort === 'created_at' ? 'desc' : 'asc',
            default => null,
        };

        $this->resetPage();
    }

    /**
     * Change the view of the listing and remember it in the account (spec 003, RF-06).
     */
    public function setView(string $view, UserPreferenceService $preferences): void
    {
        $bookView = BookView::tryFrom($view);

        if (! $bookView || ($bookView === BookView::Shelf && $this->allLibraries)) {
            return;
        }

        $preferences->updateBookView(Auth::user(), $bookView);
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'genre', 'status', 'condition', 'roomId', 'bookcaseId', 'shelfId', 'compartmentId', 'ownerFilter', 'overdueOnly');
        $this->resetPage();
    }

    public function rendering($view): void
    {
        $view->title(match (true) {
            $this->allLibraries => __('Todos los libros'),
            $this->owner->is(Auth::user()) => __('Mis libros'),
            default => __('Libros de :name', ['name' => $this->owner->name]),
        });
    }

    public function with(BookCatalogService $catalog, BookLocationService $locations, UserPreferenceService $preferences): array
    {
        $filters = [
            'search' => $this->search,
            'genre' => $this->genre,
            'status' => $this->status,
            'condition' => $this->condition,
            'room' => $this->roomId,
            'bookcase' => $this->bookcaseId,
            'shelf' => $this->bookcaseId === BookCatalogService::UNSPECIFIED ? '' : $this->shelfId,
            'compartment' => $this->bookcaseId === BookCatalogService::UNSPECIFIED ? '' : $this->compartmentId,
            'owner' => $this->ownerFilter,
            'overdue' => $this->overdueOnly,
        ];

        // Location filters use the structure of the listed library; in the global listing, of the chosen owner.
        $structureOwner = $this->owner ?? ($this->ownerFilter !== '' ? User::find($this->ownerFilter) : null);
        $room = is_numeric($this->roomId) ? (int) $this->roomId : null;

        return [
            'view' => $preferences->bookViewFor(Auth::user(), singleLibrary: ! $this->allLibraries),
            'viewOptions' => $this->allLibraries ? [BookView::Grid, BookView::Table] : BookView::cases(),
            'books' => $catalog->search($this->owner, $filters, $this->sort, $this->direction),
            // Filters inside "Más filtros" that are in use (spec 005, M-05).
            'activeMoreFilters' => collect([$this->genre, $this->status, $this->condition, $this->roomId, $this->bookcaseId, $this->shelfId, $this->compartmentId, $this->ownerFilter])
                ->filter(fn (string $value) => $value !== '')
                ->count() + (int) $this->overdueOnly,
            'isOwnLibrary' => $this->owner?->is(Auth::user()) ?? false,
            'unspecified' => BookCatalogService::UNSPECIFIED,
            'statuses' => ReadingStatus::cases(),
            'conditions' => BookCondition::cases(),
            'ownerOptions' => $this->allLibraries ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'structureOwner' => $structureOwner,
            'roomOptions' => $structureOwner ? $locations->roomOptions($structureOwner) : collect(),
            'bookcaseOptions' => $structureOwner ? $locations->libraryBookcaseOptions($structureOwner, $room) : collect(),
            'shelfOptions' => $structureOwner && is_numeric($this->bookcaseId) ? $locations->shelfOptions($structureOwner, (int) $this->bookcaseId) : collect(),
            'compartmentOptions' => $structureOwner && is_numeric($this->shelfId) ? $locations->compartmentOptions($structureOwner, (int) $this->shelfId) : collect(),
            'locations' => $locations,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">
                {{ $allLibraries ? __('Todos los libros') : ($isOwnLibrary ? __('Mis libros') : __('Libros')) }}
            </flux:heading>
            @if (! $allLibraries && ! $isOwnLibrary)
                <flux:subheading>{{ __('Biblioteca de :name', ['name' => $owner->name]) }}</flux:subheading>
            @endif
        </div>

        <flux:button variant="primary" icon="plus" :href="route('books.create', $isOwnLibrary || $allLibraries ? [] : ['owner' => $owner->id])" wire:navigate>
            {{ __('Nuevo libro') }}
        </flux:button>
    </div>

    {{-- View selector (spec 003, RF-06). The shelf option only appears from 1280 px (spec 004, RF-05). --}}
    <div role="group" aria-label="{{ __('Vista del listado') }}" class="flex flex-wrap gap-2">
        @foreach ($viewOptions as $option)
            <button
                type="button"
                wire:click="setView('{{ $option->value }}')"
                data-view-option="{{ $option->value }}"
                aria-pressed="{{ $view === $option ? 'true' : 'false' }}"
                @class([
                    'items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium',
                    'hidden xl:inline-flex' => $option === App\Enums\BookView::Shelf,
                    'inline-flex' => $option !== App\Enums\BookView::Shelf,
                    'border-leather bg-leather text-surface' => $view === $option,
                    'border-field-border bg-surface text-ink' => $view !== $option,
                ])
            >
                <flux:icon :name="$option->icon()" variant="mini" />
                {{ $option->label() }}
            </button>
        @endforeach
    </div>

    {{-- Spec 005 (M-05): only search and sorting are visible; the other filters live in the "Más filtros" panel. --}}
    <form
        @class(['space-y-4 rounded-lg border border-zinc-200 bg-surface p-4 dark:border-zinc-700', 'xl:hidden' => $view === App\Enums\BookView::Shelf])
        wire:submit.prevent
        role="search"
        aria-label="{{ __('Buscar y filtrar libros') }}"
        x-data="{ moreFilters: false }"
    >
        <div data-filters-main class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <flux:input wire:model.live.debounce.400ms="search" type="search" icon="magnifying-glass" :label="__('Buscar por título o autor')" />
            </div>

            <div class="w-44">
                <flux:select wire:model.live="sort" :label="__('Ordenar por')">
                    <flux:select.option value="created_at">{{ __('Fecha de alta') }}</flux:select.option>
                    <flux:select.option value="title">{{ __('Título') }}</flux:select.option>
                    <flux:select.option value="author">{{ __('Autor') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="w-52">
                <flux:select wire:model.live="direction" :label="__('Orden')">
                    <flux:select.option value="desc">{{ $sort === 'created_at' ? __('Más recientes primero') : __('Z → A') }}</flux:select.option>
                    <flux:select.option value="asc">{{ $sort === 'created_at' ? __('Más antiguos primero') : __('A → Z') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:button type="button" variant="ghost" wire:click="clearFilters">{{ __('Quitar filtros') }}</flux:button>

            <flux:button
                type="button"
                icon="adjustments-horizontal"
                aria-controls="more-filters"
                x-on:click="moreFilters = ! moreFilters"
                x-bind:aria-expanded="moreFilters.toString()"
            >
                {{ $activeMoreFilters ? __('Más filtros (:count)', ['count' => $activeMoreFilters]) : __('Más filtros') }}
            </flux:button>
        </div>

        <div id="more-filters" x-show="moreFilters" x-cloak class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if ($allLibraries)
                    <flux:select wire:model.live="ownerFilter" :label="__('Propietario')">
                        <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                        @foreach ($ownerOptions as $ownerOption)
                            <flux:select.option value="{{ $ownerOption->id }}">{{ $ownerOption->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:input wire:model.live.debounce.400ms="genre" :label="__('Género')" />

                <flux:select wire:model.live="status" :label="__('Estado de lectura')">
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    @foreach ($statuses as $statusOption)
                        <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="{{ $unspecified }}">{{ __('Sin especificar') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="condition" :label="__('Condición')">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach ($conditions as $conditionOption)
                        <flux:select.option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="{{ $unspecified }}">{{ __('Sin especificar') }}</flux:select.option>
                </flux:select>

                <flux:checkbox wire:model.live="overdueOnly" :label="__('Solo préstamos vencidos')" class="self-end pb-2" />
            </div>

            @if ($structureOwner)
                <fieldset class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <legend class="mb-2 text-sm font-semibold">{{ __('Ubicación') }}</legend>

                    {{-- The bookcase filter has priority, so it comes first (spec 002, RF-06). --}}
                    <flux:select wire:model.live="bookcaseId" :label="__('Estantería')">
                        <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                        @foreach ($bookcaseOptions as $bookcaseOption)
                            <flux:select.option value="{{ $bookcaseOption->id }}">{{ $bookcaseOption->name }} ({{ $bookcaseOption->room->name }})</flux:select.option>
                        @endforeach
                        <flux:select.option value="{{ $unspecified }}">{{ __('Sin especificar (en la mesa)') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model.live="roomId" :label="__('Sala')">
                        <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                        @foreach ($roomOptions as $roomOption)
                            <flux:select.option value="{{ $roomOption->id }}">{{ $roomOption->name }}</flux:select.option>
                        @endforeach
                        <flux:select.option value="{{ $unspecified }}">{{ __('Sin especificar (en la mesa)') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model.live="shelfId" :label="__('Balda')" :disabled="! is_numeric($bookcaseId)">
                        <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                        @foreach ($shelfOptions as $shelfOption)
                            <flux:select.option value="{{ $shelfOption->id }}">{{ $locations->numberedName(__('Balda :number', ['number' => $shelfOption->number]), $shelfOption->name) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="compartmentId" :label="__('Hueco')" :disabled="! is_numeric($shelfId)">
                        <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                        @foreach ($compartmentOptions as $compartmentOption)
                            <flux:select.option value="{{ $compartmentOption->id }}">{{ $locations->numberedName(__('Hueco :number', ['number' => $compartmentOption->number]), $compartmentOption->name) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </fieldset>
            @endif
        </div>
    </form>

    {{-- Listing results. In shelf mode the bookcase is only drawn from 1280 px; narrower screens get the table (spec 004, RF-05). --}}
    <div data-book-view="{{ $view->value }}" class="space-y-4">
        <div @class(['space-y-4', 'xl:hidden' => $view === App\Enums\BookView::Shelf]) @if ($view === App\Enums\BookView::Shelf) data-table-fallback @endif>
            <p class="text-sm text-ink-muted" role="status" aria-live="polite">
                {{ trans_choice(':count libro|:count libros', $books->total()) }}
            </p>

            @if ($books->isNotEmpty())
                @if ($view === App\Enums\BookView::Grid)
                    @include('livewire.books.partials.grid')
                @else
                    @include('livewire.books.partials.table')
                @endif

                {{ $books->links() }}
            @endif
        </div>

        @if ($view === App\Enums\BookView::Shelf && $owner)
            <div class="hidden xl:block" data-shelf-view>
                <livewire:books.shelf-view :owner="$owner" :bookcase="is_numeric($bookcaseId) ? (int) $bookcaseId : null" :key="'shelf-view-'.$owner->id" />
            </div>
        @endif
    </div>
</section>
