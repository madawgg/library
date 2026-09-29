<?php

use App\Models\Book;
use App\Services\BookLocationService;
use App\Services\BookService;
use App\Services\LoanService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public Book $book;

    public function mount(Book $book): void
    {
        $this->authorize('view', $book);

        $this->book = $book->load('overdueLoan');
    }

    public function delete(BookService $books): void
    {
        $this->authorize('delete', $this->book);

        $owner = $books->ownerOf($this->book);
        $books->delete($this->book);

        $this->redirect(
            $owner->is(Auth::user()) ? route('books.index') : route('admin.users.books', $owner),
            navigate: true,
        );
    }

    public function rendering($view): void
    {
        $view->title($this->book->title);
    }

    public function with(BookLocationService $locations, BookService $books, LoanService $loans): array
    {
        $owner = $books->ownerOf($this->book);

        return [
            'location' => $locations->describe($this->book),
            'activeLoan' => $loans->activeLoanOf($this->book),
            'owner' => $owner,
            'isOwnBook' => $owner->is(Auth::user()),
        ];
    }
}; ?>

<article data-book-page class="w-full space-y-6">
    @if ($book->cover_path)
        <img src="{{ route('books.cover', $book) }}" alt="{{ __('Portada de :title', ['title' => $book->title]) }}" class="float-end ms-6 mb-4 h-64 w-auto rounded border border-zinc-200 shadow-md dark:border-zinc-700" />
    @endif

    <header class="space-y-1">
        <flux:heading size="xl" level="1">{{ $book->title }}</flux:heading>
        @if ($book->author)
            <p class="text-lg text-ink-muted">{{ $book->author }}</p>
        @endif
        @include('livewire.books.partials.overdue-badge')
        @unless ($isOwnBook)
            <p class="text-sm text-ink-muted">{{ __('Biblioteca de :name', ['name' => $owner->name]) }}</p>
        @endunless
    </header>

    <div class="flex flex-wrap gap-2">
        @can('update', $book)
            <flux:button variant="primary" :href="route('books.edit', $book)" wire:navigate>{{ __('Editar') }}</flux:button>
        @endcan
        @can('delete', $book)
            <flux:button
                variant="danger"
                wire:click="delete"
                wire:confirm="{{ __('¿Eliminar «:title»? Esta acción no se puede deshacer.', ['title' => $book->title]) }}"
            >
                {{ __('Eliminar') }}
            </flux:button>
        @endcan
    </div>

    <dl class="grid gap-x-8 gap-y-4 rounded-lg border border-zinc-200 bg-surface p-5 sm:grid-cols-2 dark:border-zinc-700">
        @foreach ([
            __('Editorial') => $book->publisher,
            __('ISBN') => $book->isbn,
            __('Año de publicación') => $book->publication_year,
            __('Género') => $book->genre,
            __('Idioma') => $book->language,
            __('Número de páginas') => $book->pages,
            __('Estado de lectura') => $book->reading_status?->label(),
            __('Valoración') => $book->rating ? trans_choice(':count estrella|:count estrellas', $book->rating) : null,
            __('Condición') => $book->condition?->label(),
        ] as $label => $value)
            <div>
                <dt class="text-sm font-semibold text-ink-muted">{{ $label }}</dt>
                <dd>{{ $value ?? __('Sin especificar') }}</dd>
            </div>
        @endforeach

        <div class="sm:col-span-2">
            <dt class="text-sm font-semibold text-ink-muted">{{ __('Ubicación') }}</dt>
            <dd>
                @if ($location)
                    <ol class="flex flex-wrap items-center gap-1" aria-label="{{ __('Ubicación') }}">
                        @foreach ($location as $level)
                            <li class="flex items-center gap-1">
                                @unless ($loop->first)<span aria-hidden="true">›</span>@endunless
                                {{ $level }}
                            </li>
                        @endforeach
                    </ol>
                @else
                    {{ __('En la mesa (sin ubicación)') }}
                @endif
            </dd>
        </div>

        @if ($activeLoan)
            <div class="sm:col-span-2">
                <dt class="text-sm font-semibold text-ink-muted">{{ __('Préstamo') }}</dt>
                <dd>
                    {{ __('Prestado a :name desde el :date', ['name' => $activeLoan->borrower_name, 'date' => $activeLoan->loaned_on->format('d/m/Y')]) }}
                </dd>
            </div>
        @endif

        @if ($book->notes)
            <div class="sm:col-span-2">
                <dt class="text-sm font-semibold text-ink-muted">{{ __('Notas') }}</dt>
                <dd class="whitespace-pre-line">{{ $book->notes }}</dd>
            </div>
        @endif
    </dl>

    {{-- Discreet link to the loan history: it is not part of the navigation (spec 003, RF-08). --}}
    <p class="text-sm">
        <a href="{{ route('books.loans', $book) }}" class="text-leather underline underline-offset-2" wire:navigate>{{ __('Ver historial de préstamos') }}</a>
    </p>
</article>
