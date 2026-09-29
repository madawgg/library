<?php

use App\Services\LibrarySummaryService;
use App\Services\LoanService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Inicio')] class extends Component {
    public function with(LibrarySummaryService $summary, LoanService $loans): array
    {
        $user = Auth::user();

        return [
            'totals' => $summary->totals($user),
            'latestBooks' => $summary->latestBooks($user),
            'overdueLoans' => $summary->overdueLoans($user),
            'loans' => $loans,
        ];
    }
}; ?>

<div class="w-full space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Inicio') }}</flux:heading>
        <flux:subheading>{{ __('Hola, :name.', ['name' => auth()->user()->name]) }}</flux:subheading>
    </div>

    {{-- Totals (spec 003, RF-05) --}}
    <section aria-labelledby="totals-heading">
        <h2 id="totals-heading" class="sr-only">{{ __('Resumen de tu biblioteca') }}</h2>
        <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                __('Libros') => $totals['books'],
                __('Leídos') => $totals['read'],
                __('Prestados') => $totals['lent'],
                __('Préstamos vencidos') => $totals['overdue'],
            ] as $label => $value)
                <div class="rounded-lg border border-zinc-200 bg-surface p-4 shadow-sm dark:border-zinc-700">
                    <dt class="text-sm font-semibold text-ink-muted">{{ $label }}</dt>
                    {{-- Figures use the sans font: Cormorant's old-style "1" and "0" read as "I" and "o". --}}
                    <dd class="font-sans text-4xl font-bold tabular-nums text-ink">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Overdue loans notice (spec 003, RF-07) --}}
    @if ($overdueLoans->isNotEmpty())
        <section role="alert" aria-labelledby="overdue-heading" class="rounded-lg border-2 border-leather bg-surface p-5">
            <h2 id="overdue-heading" class="flex items-center gap-2 font-serif text-2xl font-semibold text-leather">
                <flux:icon.exclamation-triangle aria-hidden="true" />
                {{ __('Préstamos vencidos') }}
            </h2>
            <p class="mb-3 text-sm text-ink-muted">{{ __('Estos libros llevan más de 2 meses prestados.') }}</p>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($overdueLoans as $loan)
                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
                        <a href="{{ route('books.show', $loan->book) }}" class="font-semibold text-leather underline underline-offset-2" wire:navigate>{{ $loan->book->title }}</a>
                        <span>
                            {{ __('Prestado a :name', ['name' => $loan->borrower_name]) }}
                            · {{ trans_choice(':count día|:count días', $loans->daysLent($loan)) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid gap-8 lg:grid-cols-3">
        {{-- Latest books --}}
        <section aria-labelledby="latest-heading" class="lg:col-span-2">
            <h2 id="latest-heading" class="mb-3 font-serif text-2xl font-semibold">{{ __('Últimos libros añadidos') }}</h2>
            @if ($latestBooks->isEmpty())
                <p class="text-ink-muted">{{ __('Todavía no has añadido ningún libro.') }}</p>
            @else
                @include('livewire.books.partials.grid', ['books' => $latestBooks, 'allLibraries' => false])
            @endif
        </section>

        {{-- Quick access --}}
        <nav aria-labelledby="quick-heading">
            <h2 id="quick-heading" class="mb-3 font-serif text-2xl font-semibold">{{ __('Accesos rápidos') }}</h2>
            <ul class="space-y-2">
                <li><flux:button icon="plus" variant="primary" class="w-full" :href="route('books.create')" wire:navigate>{{ __('Añadir libro') }}</flux:button></li>
                <li><flux:button icon="book-open" class="w-full" :href="route('books.index')" wire:navigate>{{ __('Ver mis libros') }}</flux:button></li>
                {{-- The shelf view only exists from 1280 px (spec 004, RF-05). --}}
                <li class="hidden xl:block"><flux:button icon="building-library" class="w-full" :href="route('books.index', ['vista' => 'estanteria'])" wire:navigate>{{ __('Ver la estantería') }}</flux:button></li>
            </ul>
        </nav>
    </div>
</div>
