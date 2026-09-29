{{--
    A book drawn as a spine (spec 004, RF-05 to RF-07): draggable, with a menu offering
    "Mover a…" and "Seleccionar y colocar", and a card with the cover and details on hover or focus.
--}}
@php
    $spineColors = ['bg-leather text-surface', 'bg-english-green text-surface', 'bg-zinc-700 text-zinc-50', 'bg-[#5b3a24] text-[#f2e8d5]'];
    $spineColor = $spineColors[$book->id % count($spineColors)];
@endphp

<div class="group relative" wire:key="spine-{{ $book->id }}">
    <flux:dropdown position="bottom" align="start">
        <button
            type="button"
            data-spine="{{ $book->id }}"
            draggable="true"
            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $book->id }}'); $event.dataTransfer.effectAllowed = 'move'"
            class="{{ $spineColor }} flex h-40 w-9 cursor-grab items-center justify-center overflow-hidden rounded-sm px-1 py-2 shadow-md {{ $selected ? 'ring-4 ring-english-green ring-offset-2' : '' }}"
            aria-label="{{ $book->title }}{{ $book->author ? ', '.$book->author : '' }}. {{ __('Opciones del libro') }}"
        >
            <span class="truncate text-xs font-semibold [writing-mode:vertical-rl] rotate-180">{{ $book->title }}</span>
        </button>

        @if ($book->overdueLoan)
            {{-- On the spine the badge sits across the top so it stays readable. --}}
            <span data-overdue-badge class="absolute -top-2 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded bg-leather px-1 text-[0.65rem] font-semibold text-surface shadow">
                {{ __('Vencido') }}<span class="sr-only"> ({{ __('Préstamo vencido') }})</span>
            </span>
        @endif

        <flux:menu>
            <flux:menu.item icon="arrows-right-left" wire:click="openMoveDialog({{ $book->id }})">{{ __('Mover a…') }}</flux:menu.item>
            <flux:menu.item icon="cursor-arrow-rays" wire:click="selectBook({{ $book->id }})">{{ __('Seleccionar y colocar') }}</flux:menu.item>
            <flux:menu.item icon="eye" :href="route('books.show', $book)" wire:navigate>{{ __('Ver ficha') }}</flux:menu.item>
        </flux:menu>
    </flux:dropdown>

    <div
        role="tooltip"
        class="pointer-events-none invisible absolute bottom-full left-1/2 z-30 mb-2 w-56 -translate-x-1/2 rounded-lg border border-zinc-200 bg-surface p-3 text-sm text-ink shadow-xl group-hover:visible group-focus-within:visible dark:border-zinc-700"
    >
        @if ($book->cover_path)
            <img src="{{ route('books.cover', $book) }}" alt="" class="mb-2 h-28 w-auto rounded" loading="lazy" />
        @endif
        <p class="font-semibold">{{ $book->title }}</p>
        @if ($book->author)
            <p class="text-ink-muted">{{ $book->author }}</p>
        @endif
        @if ($book->reading_status)
            <p class="text-ink-muted">{{ $book->reading_status->label() }}</p>
        @endif
        @if ($book->overdueLoan)
            <p class="mt-1 font-semibold text-leather">{{ __('Préstamo vencido') }}</p>
        @endif
    </div>
</div>
