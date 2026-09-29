<?php

use App\Enums\BookCondition;
use App\Enums\ReadingStatus;
use App\Models\User;
use App\Services\BookCatalogService;
use App\Services\BookLocationService;
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

    #[Url(except: 'created_at')]
    public string $sort = 'created_at';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    public function mount(?User $user = null, bool $allLibraries = false): void
    {
        $this->allLibraries = $allLibraries;

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

    public function clearFilters(): void
    {
        $this->reset('search', 'genre', 'status', 'condition', 'roomId', 'bookcaseId', 'shelfId', 'compartmentId', 'ownerFilter');
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

    public function with(BookCatalogService $catalog, BookLocationService $locations): array
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
        ];

        // Location filters use the structure of the listed library; in the global listing, of the chosen owner.
        $structureOwner = $this->owner ?? ($this->ownerFilter !== '' ? User::find($this->ownerFilter) : null);
        $room = is_numeric($this->roomId) ? (int) $this->roomId : null;

        return [
            'books' => $catalog->search($this->owner, $filters, $this->sort, $this->direction),
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

    <form class="space-y-4 rounded-lg border border-zinc-200 bg-surface p-4 dark:border-zinc-700" wire:submit.prevent role="search" aria-label="{{ __('Buscar y filtrar libros') }}">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <flux:input wire:model.live.debounce.400ms="search" type="search" icon="magnifying-glass" :label="__('Buscar por título o autor')" />
            </div>

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

        <div class="flex flex-wrap items-end gap-4">
            <flux:select wire:model.live="sort" :label="__('Ordenar por')" class="max-w-xs">
                <flux:select.option value="created_at">{{ __('Fecha de alta') }}</flux:select.option>
                <flux:select.option value="title">{{ __('Título') }}</flux:select.option>
                <flux:select.option value="author">{{ __('Autor') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="direction" :label="__('Orden')" class="max-w-xs">
                <flux:select.option value="desc">{{ $sort === 'created_at' ? __('Más recientes primero') : __('Z → A') }}</flux:select.option>
                <flux:select.option value="asc">{{ $sort === 'created_at' ? __('Más antiguos primero') : __('A → Z') }}</flux:select.option>
            </flux:select>

            <flux:button type="button" variant="ghost" wire:click="clearFilters">{{ __('Quitar filtros') }}</flux:button>
        </div>
    </form>

    <p class="text-sm text-ink-muted" role="status" aria-live="polite">
        {{ trans_choice(':count libro|:count libros', $books->total()) }}
    </p>

    @if ($books->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">{{ __('Libros') }}</caption>
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th scope="col" class="py-3 pe-4 font-semibold">{{ __('Título') }}</th>
                        <th scope="col" class="py-3 pe-4 font-semibold">{{ __('Autor') }}</th>
                        <th scope="col" class="py-3 pe-4 font-semibold">{{ __('Estado de lectura') }}</th>
                        @if ($allLibraries)
                            <th scope="col" class="py-3 font-semibold">{{ __('Propietario') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($books as $book)
                        <tr wire:key="book-{{ $book->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="py-3 pe-4">
                                <a href="{{ route('books.show', $book) }}" class="font-semibold text-leather underline-offset-2 hover:underline" wire:navigate>{{ $book->title }}</a>
                            </td>
                            <td class="py-3 pe-4">{{ $book->author }}</td>
                            <td class="py-3 pe-4">{{ $book->reading_status?->label() ?? __('Sin especificar') }}</td>
                            @if ($allLibraries)
                                <td class="py-3">{{ $book->user->name }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $books->links() }}
    @endif
</section>
