<?php

use App\Enums\BookCondition;
use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Compartment;
use App\Models\User;
use App\Rules\ValidIsbn;
use App\Services\BookLocationService;
use App\Services\BookService;
use App\Services\IsbnService;
use App\Services\LoanService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Services\BookCoverService;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?Book $book = null;

    /** Picture chosen by the user (camera or file), before processing. */
    public $cover = null;

    /** Processed cover waiting for the book to be saved. */
    #[Locked]
    public ?string $pendingCoverPath = null;

    #[Locked]
    public ?string $coverPreviewUrl = null;

    /** True when the processed cover had to lose quality and the user has not accepted it yet. */
    #[Locked]
    public bool $coverNeedsConfirmation = false;

    public bool $removeCover = false;

    public ?int $ownerId = null;

    public string $title = '';
    public string $author = '';
    public string $isbn = '';
    public string $publisher = '';

    public ?int $publicationYear = null;
    public string $genre = '';
    public string $language = '';
    public ?int $pages = null;
    public string $readingStatus = '';
    public string $loanBorrower = '';
    public string $loanDate = '';
    public ?int $rating = null;
    public string $notes = '';
    public string $condition = '';

    public ?int $locationRoomId = null;
    public ?int $locationBookcaseId = null;
    public ?int $locationShelfId = null;
    public ?int $compartmentId = null;

    /** Set when the ISBN already exists in the owner's library and the save is waiting for confirmation. */
    public bool $duplicateIsbnWarning = false;

    public function mount(?Book $book = null, ?int $owner = null): void
    {
        if ($book?->exists) {
            $this->authorize('update', $book);
            $this->fillFromBook($book);

            return;
        }

        $this->ownerId = $owner ?? (request()->integer('owner') ?: Auth::id());
        $this->authorize('manageLibrary', User::findOrFail($this->ownerId));
    }

    /**
     * Process the chosen picture right away so the user can see the result before saving.
     */
    public function updatedCover(BookCoverService $covers): void
    {
        $covers->discardPending($this->pendingCoverPath);
        $this->reset('pendingCoverPath', 'coverPreviewUrl', 'coverNeedsConfirmation');

        $this->validateOnly('cover', $this->coverRules());

        $pending = $covers->preparePending($this->cover->getRealPath());

        $this->pendingCoverPath = $pending['path'];
        $this->coverPreviewUrl = $pending['preview'];
        $this->coverNeedsConfirmation = $pending['compressed'];
        $this->removeCover = false;
    }

    public function confirmCompressedCover(): void
    {
        $this->coverNeedsConfirmation = false;
    }

    /**
     * Drop the chosen picture: the book keeps its current cover.
     */
    public function discardCover(BookCoverService $covers): void
    {
        $covers->discardPending($this->pendingCoverPath);
        $this->reset('cover', 'pendingCoverPath', 'coverPreviewUrl', 'coverNeedsConfirmation');
    }

    public function updatedLocationRoomId(): void
    {
        $this->reset('locationBookcaseId', 'locationShelfId', 'compartmentId');
    }

    public function updatedLocationBookcaseId(): void
    {
        $this->reset('locationShelfId', 'compartmentId');
    }

    public function updatedLocationShelfId(): void
    {
        $this->reset('compartmentId');
    }

    public function save(BookService $books, BookLocationService $locations, IsbnService $isbns, BookCoverService $covers, LoanService $loans): void
    {
        $this->persist($books, $locations, $isbns, $covers, $loans, duplicateConfirmed: false);
    }

    public function saveConfirmingDuplicate(BookService $books, BookLocationService $locations, IsbnService $isbns, BookCoverService $covers, LoanService $loans): void
    {
        $this->persist($books, $locations, $isbns, $covers, $loans, duplicateConfirmed: true);
    }

    public function cancelDuplicate(): void
    {
        $this->duplicateIsbnWarning = false;
    }

    public function rendering($view): void
    {
        $view->title($this->book ? __('Editar libro') : __('Nuevo libro'));
    }

    public function with(BookLocationService $locations): array
    {
        $owner = $this->owner();

        return [
            'isEditing' => (bool) $this->book,
            'canChooseOwner' => ! $this->book && Auth::user()->can('viewAny', User::class),
            'ownerOptions' => ! $this->book && Auth::user()->can('viewAny', User::class) ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'readingStatuses' => ReadingStatus::cases(),
            'conditions' => BookCondition::cases(),
            'locationRooms' => $locations->roomOptions($owner),
            'locationBookcases' => $locations->bookcaseOptions($owner, $this->locationRoomId),
            'locationShelves' => $locations->shelfOptions($owner, $this->locationBookcaseId),
            'locationCompartments' => $locations->compartmentOptions($owner, $this->locationShelfId),
            'locations' => $locations,
        ];
    }

    private function persist(BookService $books, BookLocationService $locations, IsbnService $isbns, BookCoverService $covers, LoanService $loans, bool $duplicateConfirmed): void
    {
        $owner = $this->owner();

        $this->book
            ? $this->authorize('update', $this->book)
            : $this->authorize('manageLibrary', $owner);

        $validated = $this->validate([
            ...$this->coverRules(),
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', new ValidIsbn],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publicationYear' => ['nullable', 'integer', 'min:1', 'max:'.now()->year],
            'genre' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:255'],
            'pages' => ['nullable', 'integer', 'min:1'],
            'readingStatus' => ['nullable', Rule::enum(ReadingStatus::class)],
            'loanBorrower' => ['nullable', 'required_if:readingStatus,'.ReadingStatus::Lent->value, 'string', 'max:255'],
            'loanDate' => ['nullable', 'required_if:readingStatus,'.ReadingStatus::Lent->value, 'date', 'before_or_equal:today'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'condition' => ['nullable', Rule::enum(BookCondition::class)],
            'compartmentId' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($locations, $owner) {
                    $compartment = Compartment::find($value);
                    if (! $compartment || ! $locations->belongsToLibraryOf($compartment, $owner)) {
                        $fail(__('Elige un hueco de las estanterías del propietario del libro.'));
                    }
                },
            ],
        ]);

        if ($this->coverNeedsConfirmation) {
            $this->addError('cover', __('Revisa la portada: hemos tenido que reducir su calidad. Acéptala o descártala antes de guardar.'));

            return;
        }

        if (! $duplicateConfirmed && filled($validated['isbn']) && $isbns->existsInLibrary($owner, $validated['isbn'], $this->book)) {
            $this->duplicateIsbnWarning = true;

            return;
        }

        $this->duplicateIsbnWarning = false;

        $data = [
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'],
            'publisher' => $validated['publisher'],
        ];

        if ($this->book) {
            $data += [
                'publication_year' => $validated['publicationYear'],
                'genre' => $validated['genre'],
                'language' => $validated['language'],
                'pages' => $validated['pages'],
                'reading_status' => $validated['readingStatus'],
                'rating' => $validated['rating'],
                'notes' => $validated['notes'],
                'condition' => $validated['condition'],
            ];

            $previousStatus = $this->book->reading_status;

            $book = $books->update($this->book, $data);
            $loans->syncWithStatus($book, $previousStatus, $validated['loanBorrower'], $validated['loanDate']);
            $locations->place($book, $validated['compartmentId'] ? Compartment::find($validated['compartmentId']) : null);
        } else {
            $book = $books->create($owner, $data);
        }

        if ($this->pendingCoverPath) {
            $covers->attachPending($book, $this->pendingCoverPath);
        } elseif ($this->removeCover) {
            $covers->remove($book);
        }

        $this->redirectRoute('books.show', $book, navigate: true);
    }

    /**
     * @return array<string, list<string>>
     */
    private function coverRules(): array
    {
        return ['cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('books.cover_upload_max_kilobytes')]];
    }

    private function owner(): User
    {
        return $this->book?->user ?? User::findOrFail($this->ownerId);
    }

    private function fillFromBook(Book $book): void
    {
        $this->book = $book;
        $this->ownerId = $book->user_id;
        $this->title = $book->title;
        $this->author = $book->author ?? '';
        $this->isbn = $book->isbn ?? '';
        $this->publisher = $book->publisher ?? '';
        $this->publicationYear = $book->publication_year;
        $this->genre = $book->genre ?? '';
        $this->language = $book->language ?? '';
        $this->pages = $book->pages;
        $this->readingStatus = $book->reading_status?->value ?? '';
        $this->rating = $book->rating;
        $this->notes = $book->notes ?? '';
        $this->condition = $book->condition?->value ?? '';

        if ($activeLoan = app(LoanService::class)->activeLoanOf($book)) {
            $this->loanBorrower = $activeLoan->borrower_name;
            $this->loanDate = $activeLoan->loaned_on->toDateString();
        }

        if ($compartment = $book->compartment) {
            $this->compartmentId = $compartment->id;
            $this->locationShelfId = $compartment->shelf_id;
            $this->locationBookcaseId = $compartment->shelf->bookcase_id;
            $this->locationRoomId = $compartment->shelf->bookcase->room_id;
        }
    }
}; ?>

{{-- Spec 005 (M-01): the edit form uses the full width and spreads its fields over several columns. --}}
<section @class(["w-full space-y-6", "max-w-3xl" => ! $isEditing]) @if ($isEditing) data-wide-form @endif>
    <flux:heading size="xl" level="1">{{ $isEditing ? __('Editar libro') : __('Nuevo libro') }}</flux:heading>

    <form wire:submit="save" class="space-y-8">
        @if ($canChooseOwner)
            <flux:select wire:model="ownerId" :label="__('Propietario')">
                @foreach ($ownerOptions as $ownerOption)
                    <flux:select.option value="{{ $ownerOption->id }}">{{ $ownerOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <fieldset @class(["grid gap-4 sm:grid-cols-2", "lg:grid-cols-4" => $isEditing])>
            <legend class="sr-only">{{ __('Datos básicos') }}</legend>
            <div @class(["sm:col-span-2", "lg:col-span-2" => $isEditing])>
                <flux:input wire:model="title" :label="__('Título')" required />
            </div>
            <flux:input wire:model="author" :label="__('Autor')" />
            <flux:input wire:model="publisher" :label="__('Editorial')" />
            <flux:input wire:model="isbn" :label="__('ISBN')" :description="__('ISBN-10 o ISBN-13. Puedes escribirlo con o sin guiones.')" />
        </fieldset>

        <fieldset class="space-y-3" aria-describedby="cover-help">
            <legend class="mb-2 font-serif text-2xl font-semibold">{{ __('Portada') }}</legend>
            <p id="cover-help" class="text-sm text-ink-muted">{{ __('JPG, PNG o WebP de hasta 10 MB. Se guardará en formato WebP.') }}</p>

            <div class="flex flex-wrap items-start gap-6">
                @if ($coverPreviewUrl)
                    <img src="{{ $coverPreviewUrl }}" alt="{{ __('Vista previa de la nueva portada') }}" class="h-48 w-auto rounded border border-zinc-200 shadow-sm dark:border-zinc-700" />
                @elseif ($isEditing && $book->cover_path && ! $removeCover)
                    <img src="{{ route('books.cover', $book) }}" alt="{{ __('Portada actual de :title', ['title' => $book->title]) }}" class="h-48 w-auto rounded border border-zinc-200 shadow-sm dark:border-zinc-700" />
                @endif

                <div class="space-y-3">
                    <div class="flex flex-wrap gap-2">
                        {{-- The camera option only makes sense on touch devices (phones and tablets). --}}
                        <label class="hidden cursor-pointer items-center gap-2 rounded-lg border border-field-border bg-surface px-4 py-2 text-sm font-medium focus-within:outline-3 focus-within:outline-english-green pointer-coarse:inline-flex">
                            <flux:icon.camera variant="mini" />
                            {{ __('Hacer una foto') }}
                            <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" capture="environment" wire:model="cover" />
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-field-border bg-surface px-4 py-2 text-sm font-medium focus-within:outline-3 focus-within:outline-english-green">
                            <flux:icon.photo variant="mini" />
                            {{ __('Elegir un archivo') }}
                            <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" wire:model="cover" />
                        </label>
                    </div>

                    <p wire:loading wire:target="cover" class="text-sm text-ink-muted" role="status">{{ __('Procesando la imagen…') }}</p>
                    <flux:error name="cover" />

                    @if ($coverNeedsConfirmation)
                        <div role="alert" class="space-y-2 rounded-lg border-2 border-leather bg-surface p-3">
                            <p class="text-sm">{{ __('La imagen superaba los 2 MB y hemos reducido su calidad. Así es como quedará.') }}</p>
                            <div class="flex gap-2">
                                <flux:button type="button" size="sm" variant="primary" wire:click="confirmCompressedCover">{{ __('Usar esta portada') }}</flux:button>
                                <flux:button type="button" size="sm" wire:click="discardCover">{{ __('Descartar') }}</flux:button>
                            </div>
                        </div>
                    @elseif ($coverPreviewUrl)
                        <flux:button type="button" size="sm" wire:click="discardCover">{{ __('Descartar la nueva portada') }}</flux:button>
                    @endif

                    @if ($isEditing && $book->cover_path && ! $coverPreviewUrl)
                        <flux:checkbox wire:model.live="removeCover" :label="__('Quitar la portada actual')" />
                    @endif
                </div>
            </div>
        </fieldset>

        @if ($isEditing)
            <fieldset class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <legend class="mb-2 font-serif text-2xl font-semibold">{{ __('Más datos') }}</legend>
                <flux:input type="number" wire:model="publicationYear" :label="__('Año de publicación')" />
                <flux:input type="number" wire:model="pages" :label="__('Número de páginas')" min="1" />
                <flux:input wire:model="genre" :label="__('Género')" />
                <flux:input wire:model="language" :label="__('Idioma')" />

                <flux:select wire:model.live="readingStatus" :label="__('Estado de lectura')">
                    <flux:select.option value="">{{ __('Sin especificar') }}</flux:select.option>
                    @foreach ($readingStatuses as $status)
                        <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($readingStatus === App\Enums\ReadingStatus::Lent->value)
                    <div class="grid gap-4 rounded-lg border border-zinc-200 bg-surface p-4 sm:col-span-2 sm:grid-cols-2 lg:col-span-4 dark:border-zinc-700">
                        <flux:input wire:model="loanBorrower" :label="__('Prestado a')" required />
                        <flux:input type="date" wire:model="loanDate" :label="__('Fecha de préstamo')" :max="now()->toDateString()" required />
                    </div>
                @endif

                <flux:select wire:model="rating" :label="__('Valoración')">
                    <flux:select.option value="">{{ __('Sin valorar') }}</flux:select.option>
                    @for ($stars = 1; $stars <= 5; $stars++)
                        <flux:select.option value="{{ $stars }}">{{ trans_choice(':count estrella|:count estrellas', $stars) }}</flux:select.option>
                    @endfor
                </flux:select>

                <flux:select wire:model="condition" :label="__('Condición')">
                    <flux:select.option value="">{{ __('Sin especificar') }}</flux:select.option>
                    @foreach ($conditions as $conditionOption)
                        <flux:select.option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="sm:col-span-2 lg:col-span-4">
                    <flux:textarea wire:model="notes" :label="__('Notas')" rows="4" />
                </div>
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <legend class="mb-2 font-serif text-2xl font-semibold">{{ __('Ubicación') }}</legend>
                <p class="text-sm text-ink-muted sm:col-span-2 lg:col-span-4">
                    {{ __('Elige sala, estantería, balda y hueco, o deja la sala vacía para que el libro quede en la mesa (sin ubicación).') }}
                </p>

                <flux:select wire:model.live="locationRoomId" :label="__('Sala')">
                    <flux:select.option value="">{{ __('Sin ubicación') }}</flux:select.option>
                    @foreach ($locationRooms as $room)
                        <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="locationBookcaseId" :label="__('Estantería')" :disabled="! $locationRoomId">
                    <flux:select.option value="">{{ __('Elige una estantería') }}</flux:select.option>
                    @foreach ($locationBookcases as $bookcaseOption)
                        <flux:select.option value="{{ $bookcaseOption->id }}">{{ $bookcaseOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="locationShelfId" :label="__('Balda')" :disabled="! $locationBookcaseId">
                    <flux:select.option value="">{{ __('Elige una balda') }}</flux:select.option>
                    @foreach ($locationShelves as $shelfOption)
                        <flux:select.option value="{{ $shelfOption->id }}">{{ $locations->numberedName(__('Balda :number', ['number' => $shelfOption->number]), $shelfOption->name) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="compartmentId" :label="__('Hueco')" :disabled="! $locationShelfId">
                    <flux:select.option value="">{{ __('Elige un hueco') }}</flux:select.option>
                    @foreach ($locationCompartments as $compartmentOption)
                        <flux:select.option value="{{ $compartmentOption->id }}">{{ $locations->numberedName(__('Hueco :number', ['number' => $compartmentOption->number]), $compartmentOption->name) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </fieldset>
        @endif

        @if ($duplicateIsbnWarning)
            <div role="alert" class="space-y-3 rounded-lg border-2 border-leather bg-surface p-4">
                <p class="font-semibold">{{ __('Ya tienes un libro con este ISBN en la biblioteca.') }}</p>
                <p class="text-sm text-ink-muted">{{ __('Puede ser otro ejemplar. ¿Quieres guardarlo igualmente?') }}</p>
                <div class="flex gap-2">
                    <flux:button type="button" variant="primary" wire:click="saveConfirmingDuplicate">{{ __('Guardar igualmente') }}</flux:button>
                    <flux:button type="button" wire:click="cancelDuplicate">{{ __('No guardar') }}</flux:button>
                </div>
            </div>
        @endif

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ $isEditing ? __('Guardar cambios') : __('Crear libro') }}</flux:button>
            <flux:button :href="$isEditing ? route('books.show', $book) : route('books.index')" wire:navigate>{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</section>
