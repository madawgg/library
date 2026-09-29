{{-- Book listing as a grid of covers (spec 003, RF-06). Books without a cover show a placeholder with title and author. --}}
<ul class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5" role="list">
    @foreach ($books as $book)
        <li wire:key="card-{{ $book->id }}">
            <a href="{{ route('books.show', $book) }}" class="group block space-y-2" wire:navigate>
                @if ($book->cover_path)
                    <img src="{{ route('books.cover', $book) }}" alt="" loading="lazy" class="aspect-[2/3] w-full rounded object-cover shadow-md transition group-hover:shadow-lg" />
                @else
                    <div data-cover-placeholder class="flex aspect-[2/3] w-full flex-col justify-center gap-2 rounded border border-zinc-300 bg-surface p-3 text-center shadow-sm dark:border-zinc-600" aria-hidden="true">
                        <span class="font-serif text-lg font-semibold leading-tight text-ink">{{ $book->title }}</span>
                        @if ($book->author)
                            <span class="text-xs text-ink-muted">{{ $book->author }}</span>
                        @endif
                    </div>
                @endif
                @include('livewire.books.partials.overdue-badge')
                <span class="block font-semibold leading-tight text-leather group-hover:underline">{{ $book->title }}</span>
                @if ($book->author)
                    <span class="block text-sm text-ink-muted">{{ $book->author }}</span>
                @endif
                @if ($allLibraries)
                    <span class="block text-xs text-ink-muted">{{ $book->user->name }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
