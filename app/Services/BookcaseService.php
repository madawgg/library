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
 * ['id' => ?int, 'name' => ?string, 'compartment_names' => list<?string>]: one entry per compartment,
 * left to right. The optional "id" identifies an existing shelf, so it keeps its books when it moves.
 */
class BookcaseService
{
    /** Temporary offset that keeps shelf numbers unique while they are being renumbered. */
    private const NUMBER_PARKING_OFFSET = 1000000;

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
     * @return list<array{id: int, name: ?string, compartment_names: list<?string>}>
     */
    public function structureOf(Bookcase $bookcase): array
    {
        return $bookcase->shelves()
            ->with(['compartments' => fn ($query) => $query->orderBy('number')])
            ->orderBy('number')
            ->get()
            ->map(fn (Shelf $shelf) => [
                'id' => $shelf->id,
                'name' => $shelf->name,
                'compartment_names' => $shelf->compartments->pluck('name')->all(),
            ])
            ->all();
    }

    /**
     * Move a shelf, with its compartments and books, to another position of its bookcase
     * (1 = top; out-of-range positions are clamped). The other shelves are renumbered (spec 004, RF-08).
     */
    public function moveShelf(Shelf $shelf, int $position): void
    {
        DB::transaction(function () use ($shelf, $position) {
            $order = $shelf->bookcase->shelves()->orderBy('number')->pluck('id');
            $order = $order->reject(fn (int $id) => $id === $shelf->id)->values();
            $order->splice(max(0, min($position - 1, $order->count())), 0, [$shelf->id]);

            $this->renumberShelves($shelf->bookcase, $order->all());
        });
    }

    /**
     * Each shelf of the structure may carry its "id": the shelf keeps its compartments and books
     * wherever it is placed, a null id creates a new shelf, and the bookcase shelves left out are
     * removed (their books move to the table). Without the "id" key, shelves are matched by number.
     *
     * @param  list<array{id?: ?int, name: ?string, compartment_names: list<?string>}>  $structure
     */
    private function syncStructure(Bookcase $bookcase, array $structure): void
    {
        if ($structure === []) {
            throw new InvalidArgumentException('A bookcase needs at least one shelf.');
        }

        $structure = array_values($structure);
        $existing = $bookcase->shelves()->get()->keyBy('id');
        $byIdentity = collect($structure)->contains(fn (array $shelfData) => array_key_exists('id', $shelfData));

        // Id of the existing shelf (or null for a new one) for every entry of the structure.
        $targetIds = array_map(function (array $shelfData, int $index) use ($existing, $byIdentity) {
            if ($shelfData['compartment_names'] === []) {
                throw new InvalidArgumentException('Every shelf needs at least one compartment.');
            }

            $shelf = $byIdentity
                ? $existing->get($shelfData['id'] ?? 0)
                : $existing->firstWhere('number', $index + 1);

            return $shelf?->id;
        }, $structure, array_keys($structure));

        $keptIds = collect($targetIds)->filter()->values();
        $removed = $existing->keys()->diff($keptIds);

        if ($removed->isNotEmpty()) {
            $this->deleteCompartments(Compartment::whereIn('shelf_id', $removed->all()));
            Shelf::whereKey($removed->all())->delete();
        }

        // Park the kept shelves out of the way of the unique (bookcase, number) index, then reload them
        // so every shelf gets its final number saved.
        $bookcase->shelves()->whereKey($keptIds->all())->increment('number', self::NUMBER_PARKING_OFFSET);
        $kept = $bookcase->shelves()->whereKey($keptIds->all())->get()->keyBy('id');

        foreach ($structure as $index => $shelfData) {
            $shelf = $targetIds[$index] ? $kept->get($targetIds[$index]) : $bookcase->shelves()->make();
            $shelf->fill(['number' => $index + 1, 'name' => $this->optionalName($shelfData['name'])])->save();

            foreach (array_values($shelfData['compartment_names']) as $compartmentIndex => $compartmentName) {
                $shelf->compartments()->updateOrCreate(
                    ['number' => $compartmentIndex + 1],
                    ['name' => $this->optionalName($compartmentName)],
                );
            }

            $this->deleteCompartments($shelf->compartments()->where('number', '>', count($shelfData['compartment_names'])));
        }
    }

    /**
     * Give the shelves the numbers 1..N in the given order of ids.
     *
     * @param  list<int>  $shelfIds
     */
    private function renumberShelves(Bookcase $bookcase, array $shelfIds): void
    {
        $bookcase->shelves()->increment('number', self::NUMBER_PARKING_OFFSET);

        foreach ($shelfIds as $index => $shelfId) {
            Shelf::whereKey($shelfId)->update(['number' => $index + 1]);
        }
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
