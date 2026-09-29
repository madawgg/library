<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Bookcase;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data for the virtual bookcase and the table next to it (spec 004, RF-05).
 */
class ShelfViewService
{
    /** Books shown on the table at a time. */
    public const TABLE_PAGE_SIZE = 8;

    /**
     * Bookcases of the owner, grouped by room name for the selectors.
     *
     * @return Collection<int, Bookcase>
     */
    public function bookcasesOf(User $owner, ?int $roomId = null): Collection
    {
        return Bookcase::with('room')
            ->whereHas('room', fn ($query) => $query->where('user_id', $owner->id)->when($roomId, fn ($query) => $query->whereKey($roomId)))
            ->orderBy('name')
            ->get();
    }

    /**
     * The bookcase with its shelves (top to bottom), compartments (left to right)
     * and the books of each compartment in position order.
     */
    public function layout(Bookcase $bookcase): Bookcase
    {
        return $bookcase->load([
            'room',
            'shelves' => fn ($query) => $query->orderBy('number'),
            'shelves.compartments' => fn ($query) => $query->orderBy('number'),
            'shelves.compartments.books' => fn ($query) => $query->orderBy('position')->with('overdueLoan'),
        ]);
    }

    /**
     * Books of the owner without a location, alphabetically, a page at a time.
     *
     * @return LengthAwarePaginator<int, Book>
     */
    public function tableBooks(User $owner, int $page): LengthAwarePaginator
    {
        return $owner->books()
            ->with('overdueLoan')
            ->whereNull('compartment_id')
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(self::TABLE_PAGE_SIZE, ['*'], 'tablePage', $page);
    }
}
