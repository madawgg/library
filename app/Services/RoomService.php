<?php

namespace App\Services;

use App\Models\Compartment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rooms of a library (spec 004, RF-02).
 */
class RoomService
{
    public function __construct(private BookLocationService $locations) {}

    /**
     * Rooms of the owner with their bookcases and structure counts, ordered by name.
     *
     * @return Collection<int, Room>
     */
    public function roomsOf(User $owner): Collection
    {
        return $owner->rooms()
            ->with(['bookcases' => fn ($query) => $query->orderBy('name')->withCount(['shelves', 'compartments'])])
            ->orderBy('name')
            ->get();
    }

    public function create(User $owner, string $name): Room
    {
        return $owner->rooms()->create(['name' => $name]);
    }

    public function rename(Room $room, string $name): void
    {
        $room->update(['name' => $name]);
    }

    /**
     * Delete the room with its bookcases, shelves and compartments; its books move to the table.
     */
    public function delete(Room $room): void
    {
        DB::transaction(function () use ($room) {
            $this->locations->moveToTable(
                Compartment::whereHas('shelf.bookcase', fn ($query) => $query->where('room_id', $room->id))->pluck('id')
            );
            $room->delete();
        });
    }

    public function ownerOf(Room $room): User
    {
        return $room->user;
    }
}
