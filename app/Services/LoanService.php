<?php

namespace App\Services;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loans and their history (spec 002, RF-05b).
 */
class LoanService
{
    /** A loan is overdue when it has been active for more than this many months. */
    private const OVERDUE_AFTER_MONTHS = 2;

    /**
     * Keep the loan history in line with a book's new reading status:
     * - lent: open a loan, or correct the active one when the book already was lent;
     * - any other status after being lent: the active loan is returned today.
     */
    public function syncWithStatus(Book $book, ?ReadingStatus $previousStatus, ?string $borrower, ?string $loanedOn): void
    {
        DB::transaction(function () use ($book, $previousStatus, $borrower, $loanedOn) {
            $active = $this->activeLoanOf($book);

            if ($book->reading_status === ReadingStatus::Lent) {
                $loan = $active ?? $book->loans()->make();
                $loan->fill(['borrower_name' => $borrower, 'loaned_on' => $loanedOn]);
                $loan->save();

                return;
            }

            if ($previousStatus === ReadingStatus::Lent && $active) {
                $this->markReturned($active);
            }
        });
    }

    public function activeLoanOf(Book $book): ?Loan
    {
        return $book->loans()->whereNull('returned_on')->latest('loaned_on')->first();
    }

    /**
     * @return Collection<int, Loan>
     */
    public function historyOf(Book $book): Collection
    {
        return $book->loans()->orderByDesc('loaned_on')->orderByDesc('id')->get();
    }

    /**
     * Days lent: until the return date, or until today while the loan is active (never stored).
     */
    public function daysLent(Loan $loan): int
    {
        return (int) $loan->loaned_on->diffInDays($loan->returned_on ?? Carbon::today());
    }

    /**
     * Flag the active loans that have been lent for more than two months (daily check).
     *
     * @return int number of loans flagged
     */
    public function flagOverdueLoans(): int
    {
        return Loan::whereNull('returned_on')
            ->where('is_overdue', false)
            ->whereDate('loaned_on', '<', Carbon::today()->subMonthsNoOverflow(self::OVERDUE_AFTER_MONTHS))
            ->update(['is_overdue' => true]);
    }

    /**
     * Correct a loan of the history (administrators only).
     */
    public function update(Loan $loan, string $borrower, string $loanedOn, ?string $returnedOn): void
    {
        $loan->fill(['borrower_name' => $borrower, 'loaned_on' => $loanedOn, 'returned_on' => $returnedOn]);

        if ($returnedOn !== null) {
            $loan->is_overdue = false;
        }

        $loan->save();
    }

    /**
     * Delete a loan of the history (administrators only). The loan in progress of a lent book
     * cannot be deleted: the book has to be returned first.
     */
    public function delete(Loan $loan): void
    {
        if ($loan->returned_on === null && $loan->book->reading_status === ReadingStatus::Lent) {
            throw new InvalidArgumentException('The active loan of a lent book cannot be deleted.');
        }

        $loan->delete();
    }

    private function markReturned(Loan $loan): void
    {
        $loan->returned_on = Carbon::today();
        $loan->is_overdue = false;
        $loan->save();
    }
}
