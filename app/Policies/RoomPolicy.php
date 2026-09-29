<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;
use App\Services\RoomService;
use App\Services\UserRoleService;

/**
 * Rooms belong to a library: its owner and administrators can manage them (spec 004, RF-01).
 */
class RoomPolicy
{
    public function __construct(private UserRoleService $roles, private RoomService $rooms) {}

    public function update(User $actor, Room $room): bool
    {
        return $this->roles->canAccessLibraryOf($actor, $this->rooms->ownerOf($room));
    }

    public function delete(User $actor, Room $room): bool
    {
        return $this->update($actor, $room);
    }
}
