<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Bookcase;
use App\Models\Compartment;
use App\Models\Room;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Where each book is: a compartment and a position inside it, or the table (spec 002 RF-05c, spec 004).
 */
class BookLocationService
{
    /**
     * Place the book at the end of the compartment, or on the table when null.
     * Keeping the same compartment leaves the position untouched.
     */
    public function place(Book $book, ?Compartment $compartment): void
    {
        if ($compartment && ! $this->belongsToLibraryOf($compartment, $book->user)) {
            throw new InvalidArgumentException('A book can only be placed in its owner\'s bookcases.');
        }

        if ($book->compartment_id === $compartment?->id) {
            return;
        }

        DB::transaction(function () use ($book, $compartment) {
            $previousCompartmentId = $book->compartment_id;

            $book->compartment()->associate($compartment);
            $book->position = $compartment ? $compartment->books()->max('position') + 1 : null;
            $book->save();

            if ($previousCompartmentId) {
                $this->renumber($previousCompartmentId);
            }
        });
    }

    /**
     * Put the book at the given position of a compartment (1 = leftmost; beyond the end = last),
     * or on the table when the compartment is null. Origin and destination are renumbered without gaps
     * (spec 004, RF-06 and RF-07).
     */
    public function move(Book $book, ?Compartment $compartment, ?int $position): void
    {
        if ($compartment && ! $this->belongsToLibraryOf($compartment, $book->user)) {
            throw new InvalidArgumentException('A book can only be placed in its owner\'s bookcases.');
        }

        DB::transaction(function () use ($book, $compartment, $position) {
            $previousCompartmentId = $book->compartment_id;

            if (! $compartment) {
                $book->compartment()->dissociate();
                $book->position = null;
                $book->save();
            } else {
                $siblings = $compartment->books()->whereKeyNot($book->id)->orderBy('position')->get()->values();
                $index = max(0, min(($position ?? PHP_INT_MAX) - 1, $siblings->count()));

                $book->compartment()->associate($compartment);
                $siblings->splice($index, 0, [$book]);

                $siblings->each(function (Book $sibling, int $order) {
                    if ($sibling->position !== $order + 1 || $sibling->isDirty()) {
                        $sibling->position = $order + 1;
                        $sibling->save();
                    }
                });
            }

            if ($previousCompartmentId && $previousCompartmentId !== $compartment?->id) {
                $this->renumber($previousCompartmentId);
            }
        });
    }

    /**
     * "Estantería grande, Balda 2 · Poesía, Hueco 3" for announcements and labels.
     */
    public function compartmentLabel(Compartment $compartment): string
    {
        $shelf = $compartment->shelf;

        return implode(', ', [
            $shelf->bookcase->name,
            $this->numberedName(__('Balda :number', ['number' => $shelf->number]), $shelf->name),
            $this->numberedName(__('Hueco :number', ['number' => $compartment->number]), $compartment->name),
        ]);
    }

    /**
     * Move every book of the given compartments to the table (the compartments are about to disappear).
     *
     * @param  iterable<int>  $compartmentIds
     */
    public function moveToTable(iterable $compartmentIds): void
    {
        Book::whereIn('compartment_id', collect($compartmentIds))->update(['compartment_id' => null, 'position' => null]);
    }

    public function belongsToLibraryOf(Compartment $compartment, User $owner): bool
    {
        return $compartment->shelf->bookcase->room->user_id === $owner->id;
    }

    /**
     * "Sala › Estantería › Balda 2 · Poesía › Hueco 1", or null when the book is on the table.
     *
     * @return list<string>|null
     */
    public function describe(Book $book): ?array
    {
        $compartment = $book->compartment;

        if (! $compartment) {
            return null;
        }

        $shelf = $compartment->shelf;

        return [
            $shelf->bookcase->room->name,
            $shelf->bookcase->name,
            $this->numberedName(__('Balda :number', ['number' => $shelf->number]), $shelf->name),
            $this->numberedName(__('Hueco :number', ['number' => $compartment->number]), $compartment->name),
        ];
    }

    /**
     * @return Collection<int, Room>
     */
    public function roomOptions(User $owner): Collection
    {
        return $owner->rooms()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Bookcase>
     */
    public function bookcaseOptions(User $owner, ?int $roomId): Collection
    {
        return $roomId
            ? Bookcase::whereHas('room', fn ($query) => $query->whereKey($roomId)->where('user_id', $owner->id))->orderBy('name')->get()
            : new Collection;
    }

    /**
     * Bookcases of the owner's library, optionally limited to one room (listing filters).
     *
     * @return Collection<int, Bookcase>
     */
    public function libraryBookcaseOptions(User $owner, ?int $roomId): Collection
    {
        return Bookcase::whereHas('room', fn ($query) => $query->where('user_id', $owner->id)->when($roomId, fn ($query) => $query->whereKey($roomId)))
            ->with('room')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Shelf>
     */
    public function shelfOptions(User $owner, ?int $bookcaseId): Collection
    {
        return $bookcaseId
            ? Shelf::where('bookcase_id', $bookcaseId)->whereHas('bookcase.room', fn ($query) => $query->where('user_id', $owner->id))->orderBy('number')->get()
            : new Collection;
    }

    /**
     * @return Collection<int, Compartment>
     */
    public function compartmentOptions(User $owner, ?int $shelfId): Collection
    {
        return $shelfId
            ? Compartment::where('shelf_id', $shelfId)->whereHas('shelf.bookcase.room', fn ($query) => $query->where('user_id', $owner->id))->orderBy('number')->get()
            : new Collection;
    }

    public function numberedName(string $numbered, ?string $name): string
    {
        return filled($name) ? "{$numbered} · {$name}" : $numbered;
    }

    private function renumber(int $compartmentId): void
    {
        Book::where('compartment_id', $compartmentId)
            ->orderBy('position')
            ->get()
            ->each(function (Book $book, int $index) {
                if ($book->position !== $index + 1) {
                    $book->position = $index + 1;
                    $book->save();
                }
            });
    }
}
