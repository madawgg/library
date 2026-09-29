<?php

namespace App\Services;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rooms of a library (spec 004, RF-02).
 */
class RoomService
{
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
     * Delete the room with its bookcases, shelves and compartments.
     */
    public function delete(Room $room): void
    {
        $room->delete();
    }

    public function ownerOf(Room $room): User
    {
        return $room->user;
    }
}
