<?php

namespace App\Services;

use App\Models\Bookcase;
use App\Models\Compartment;
use App\Models\Room;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bookcases and their shelves and compartments (spec 004, RF-03 and RF-04).
 *
 * A structure is a list of shelves, top to bottom. Each shelf is
 * ['name' => ?string, 'compartment_names' => list<?string>]: one entry per compartment, left to right.
 */
class BookcaseService
{
    public function __construct(private BookLocationService $locations) {}

    /**
     * @param  list<array{name: ?string, compartment_names: list<?string>}>  $structure
     */
    public function create(Room $room, string $name, array $structure): Bookcase
    {
        return DB::transaction(function () use ($room, $name, $structure) {
            $bookcase = $room->bookcases()->create(['name' => $name]);

            $this->syncStructure($bookcase, $structure);

            return $bookcase;
        });
    }

    /**
     * Update name, room and structure. Existing shelves and compartments are kept by number;
     * the ones beyond the new counts are removed.
     *
     * @param  list<array{name: ?string, compartment_names: list<?string>}>  $structure
     */
    public function update(Bookcase $bookcase, Room $room, string $name, array $structure): Bookcase
    {
        if ($room->user_id !== $bookcase->room->user_id) {
            throw new InvalidArgumentException('A bookcase can only be moved to a room of the same owner.');
        }

        return DB::transaction(function () use ($bookcase, $room, $name, $structure) {
            $bookcase->room()->associate($room);
            $bookcase->name = $name;
            $bookcase->save();

            $this->syncStructure($bookcase, $structure);

            return $bookcase;
        });
    }

    /**
     * Delete the bookcase; its books move to the table.
     */
    public function delete(Bookcase $bookcase): void
    {
        DB::transaction(function () use ($bookcase) {
            $this->locations->moveToTable($bookcase->compartments()->pluck('compartments.id'));
            $bookcase->delete();
        });
    }

    public function ownerOf(Bookcase $bookcase): User
    {
        return $bookcase->room->user;
    }

    /**
     * Current structure of the bookcase, in the same format that create() and update() accept.
     *
     * @return list<array{name: ?string, compartment_names: list<?string>}>
     */
    public function structureOf(Bookcase $bookcase): array
    {
        return $bookcase->shelves()
            ->with(['compartments' => fn ($query) => $query->orderBy('number')])
            ->orderBy('number')
            ->get()
            ->map(fn (Shelf $shelf) => [
                'name' => $shelf->name,
                'compartment_names' => $shelf->compartments->pluck('name')->all(),
            ])
            ->all();
    }

    /**
     * @param  list<array{name: ?string, compartment_names: list<?string>}>  $structure
     */
    private function syncStructure(Bookcase $bookcase, array $structure): void
    {
        if ($structure === []) {
            throw new InvalidArgumentException('A bookcase needs at least one shelf.');
        }

        foreach (array_values($structure) as $index => $shelfData) {
            if ($shelfData['compartment_names'] === []) {
                throw new InvalidArgumentException('Every shelf needs at least one compartment.');
            }

            $shelf = $bookcase->shelves()->updateOrCreate(
                ['number' => $index + 1],
                ['name' => $this->optionalName($shelfData['name'])],
            );

            foreach (array_values($shelfData['compartment_names']) as $compartmentIndex => $compartmentName) {
                $shelf->compartments()->updateOrCreate(
                    ['number' => $compartmentIndex + 1],
                    ['name' => $this->optionalName($compartmentName)],
                );
            }

            $this->deleteCompartments($shelf->compartments()->where('number', '>', count($shelfData['compartment_names'])));
        }

        $removedShelves = $bookcase->shelves()->where('number', '>', count($structure));
        $this->deleteCompartments(Compartment::whereIn('shelf_id', (clone $removedShelves)->select('id')));
        $removedShelves->delete();
    }

    /**
     * Delete compartments after moving their books to the table (spec 004, RF-04).
     *
     * @param  Builder<Compartment>|HasMany<Compartment, Shelf>  $compartments
     */
    private function deleteCompartments(Builder|HasMany $compartments): void
    {
        $this->locations->moveToTable((clone $compartments)->pluck('id'));
        $compartments->delete();
    }

    private function optionalName(?string $name): ?string
    {
        return filled($name) ? trim($name) : null;
    }
}
