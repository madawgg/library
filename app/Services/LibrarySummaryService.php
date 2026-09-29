<?php

namespace App\Services;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Summary of a library for the home page (spec 003, RF-05).
 */
class LibrarySummaryService
{
    public const LATEST_BOOKS = 3;

    /**
     * @return array{books: int, read: int, lent: int, overdue: int}
     */
    public function totals(User $owner): array
    {
        return [
            'books' => $owner->books()->count(),
            'read' => $owner->books()->where('reading_status', ReadingStatus::Read)->count(),
            'lent' => $owner->books()->where('reading_status', ReadingStatus::Lent)->count(),
            'overdue' => $this->overdueLoansQuery($owner)->count(),
        ];
    }

    /**
     * @return Collection<int, Book>
     */
    public function latestBooks(User $owner): Collection
    {
        return $owner->books()->with('overdueLoan')->latest()->latest('id')->limit(self::LATEST_BOOKS)->get();
    }

    /**
     * Loans in progress flagged as overdue, oldest first.
     *
     * @return Collection<int, Loan>
     */
    public function overdueLoans(User $owner): Collection
    {
        return $this->overdueLoansQuery($owner)->with('book')->orderBy('loaned_on')->get();
    }

    private function overdueLoansQuery(User $owner)
    {
        return Loan::whereNull('returned_on')
            ->where('is_overdue', true)
            ->whereHas('book', fn ($query) => $query->where('user_id', $owner->id));
    }
}
