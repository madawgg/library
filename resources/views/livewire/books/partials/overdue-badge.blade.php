{{-- Overdue loan badge (spec 003, RF-07): always with text, never only a colour. --}}
@if ($book->overdueLoan)
    <span data-overdue-badge class="inline-flex items-center gap-1 rounded bg-leather px-1.5 py-0.5 text-xs font-semibold text-surface {{ $class ?? '' }}">
        <flux:icon.exclamation-triangle variant="micro" aria-hidden="true" />
        {{ __('Préstamo vencido') }}
    </span>
@endif
