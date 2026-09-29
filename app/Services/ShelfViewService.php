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
     * Link to the shelf view of "Mis libros" showing this bookcase (spec 005, M-06 and M-07).
     * Administrators managing someone else's rooms go to that user's library. On narrow screens
     * the same link shows the table filtered by the bookcase.
     */
    public function linkFor(Bookcase $bookcase, User $actor): string
    {
        $owner = $bookcase->room->user;
        $parameters = ['vista' => 'estanteria', 'bookcase' => $bookcase->id];

        return $owner->is($actor)
            ? route('books.index', $parameters)
            : route('admin.users.books', ['user' => $owner, ...$parameters]);
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
