<?php

use App\Models\Book;
use App\Models\Loan;
use App\Services\LoanService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Historial de préstamos')] class extends Component {
    #[Locked]
    public Book $book;

    #[Locked]
    public ?int $editingLoanId = null;

    public string $editBorrower = '';
    public string $editLoanedOn = '';
    public string $editReturnedOn = '';

    public function mount(Book $book): void
    {
        $this->authorize('view', $book);

        $this->book = $book;
    }

    public function startEditing(int $loanId): void
    {
        $loan = $this->loan($loanId);

        $this->authorize('update', $loan);

        $this->editingLoanId = $loan->id;
        $this->editBorrower = $loan->borrower_name;
        $this->editLoanedOn = $loan->loaned_on->toDateString();
        $this->editReturnedOn = $loan->returned_on?->toDateString() ?? '';
    }

    public function cancelEditing(): void
    {
        $this->reset('editingLoanId', 'editBorrower', 'editLoanedOn', 'editReturnedOn');
    }

    public function saveLoan(LoanService $loans): void
    {
        $loan = $this->loan($this->editingLoanId);

        $this->authorize('update', $loan);

        $validated = $this->validate([
            'editBorrower' => ['required', 'string', 'max:255'],
            'editLoanedOn' => ['required', 'date', 'before_or_equal:today'],
            'editReturnedOn' => ['nullable', 'date', 'after_or_equal:editLoanedOn', 'before_or_equal:today'],
        ]);

        $loans->update($loan, $validated['editBorrower'], $validated['editLoanedOn'], $validated['editReturnedOn'] ?: null);

        $this->cancelEditing();
    }

    public function deleteLoan(int $loanId, LoanService $loans): void
    {
        $loan = $this->loan($loanId);

        $this->authorize('delete', $loan);

        try {
            $loans->delete($loan);
        } catch (InvalidArgumentException) {
            $this->addError('loan', __('El libro sigue prestado: márcalo como devuelto antes de eliminar este préstamo.'));
        }
    }

    public function with(LoanService $loans): array
    {
        return ['loans' => $loans->historyOf($this->book), 'loanService' => $loans];
    }

    private function loan(?int $loanId): Loan
    {
        return $this->book->loans()->findOrFail($loanId);
    }
}; ?>

<section class="w-full max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Historial de préstamos') }}</flux:heading>
        <flux:subheading>
            <a href="{{ route('books.show', $book) }}" class="text-leather underline underline-offset-2" wire:navigate>{{ $book->title }}</a>
        </flux:subheading>
    </div>

    <flux:error name="loan" />

    @if ($loans->isEmpty())
        <p class="text-ink-muted">{{ __('Este libro no se ha prestado nunca.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">{{ __('Préstamos de :title', ['title' => $book->title]) }}</caption>
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th scope="col" class="py-3 pe-4 font-semibold">{{ __('Prestado a') }}</th>
                        <th scope="col" class="py-3 pe-4 font-semibold">{{ __('Fecha de préstamo') }}</th>
                        <th scope="col" class="py-3 pe-4 font-semibold">{{ __('Devolución') }}</th>
                        <th scope="col" class="py-3 pe-4 font-semibold">{{ __('Días prestado') }}</th>
                        <th scope="col" class="py-3 font-semibold"><span class="sr-only">{{ __('Acciones') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($loans as $loan)
                        <tr wire:key="loan-{{ $loan->id }}" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                            @if ($editingLoanId === $loan->id)
                                <td class="py-3 pe-2"><flux:input wire:model="editBorrower" :label="__('Prestado a')" /></td>
                                <td class="py-3 pe-2"><flux:input type="date" wire:model="editLoanedOn" :label="__('Fecha de préstamo')" /></td>
                                <td class="py-3 pe-2"><flux:input type="date" wire:model="editReturnedOn" :label="__('Devolución')" /></td>
                                <td class="py-3 pe-2">{{ trans_choice(':count día|:count días', $loanService->daysLent($loan)) }}</td>
                                <td class="py-3">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="saveLoan">{{ __('Guardar') }}</flux:button>
                                        <flux:button size="sm" wire:click="cancelEditing">{{ __('Cancelar') }}</flux:button>
                                    </div>
                                </td>
                            @else
                                <td class="py-3 pe-4">{{ $loan->borrower_name }}</td>
                                <td class="py-3 pe-4">{{ $loan->loaned_on->format('d/m/Y') }}</td>
                                <td class="py-3 pe-4">
                                    @if ($loan->returned_on)
                                        {{ $loan->returned_on->format('d/m/Y') }}
                                    @else
                                        {{ __('En préstamo') }}
                                        @if ($loan->is_overdue)
                                            <span class="ms-1 rounded bg-leather px-1.5 py-0.5 text-xs font-semibold text-surface">{{ __('Préstamo vencido') }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-3 pe-4">{{ trans_choice(':count día|:count días', $loanService->daysLent($loan)) }}</td>
                                <td class="py-3">
                                    <div class="flex justify-end gap-2">
                                        @can('update', $loan)
                                            <flux:button size="sm" wire:click="startEditing({{ $loan->id }})">
                                                {{ __('Editar') }}<span class="sr-only"> {{ __('préstamo a :name', ['name' => $loan->borrower_name]) }}</span>
                                            </flux:button>
                                        @endcan
                                        @can('delete', $loan)
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="deleteLoan({{ $loan->id }})"
                                                wire:confirm="{{ __('¿Eliminar este préstamo del historial?') }}"
                                            >
                                                {{ __('Eliminar') }}<span class="sr-only"> {{ __('préstamo a :name', ['name' => $loan->borrower_name]) }}</span>
                                            </flux:button>
                                        @endcan
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
