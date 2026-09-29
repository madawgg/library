{{-- Book listing as a table (spec 002 RF-06, spec 003 RF-06). --}}
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
                <tr wire:key="row-{{ $book->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
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
