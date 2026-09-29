<?php

namespace Tests\Feature\Shelving;

use App\Models\Bookcase;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 004 - RF-01 (ownership) and RF-02 (rooms).
 * The books moving to the table (CA-09b) are covered with the books spec (002).
 */
class RoomManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/rooms')->assertRedirect('/login');
    }

    public function test_user_sees_only_their_own_rooms(): void
    {
        $user = User::factory()->create();
        Room::factory()->for($user)->create(['name' => 'Salón']);
        Room::factory()->create(['name' => 'Despacho ajeno']);

        $this->actingAs($user)
            ->get('/rooms')
            ->assertOk()
            ->assertSee('Salón')
            ->assertDontSee('Despacho ajeno');
    }

    public function test_user_creates_a_room(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('shelving.index')
            ->set('newRoomName', 'Salón')
            ->call('createRoom')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('rooms', ['user_id' => $user->id, 'name' => 'Salón']);
    }

    public function test_room_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('shelving.index')
            ->set('newRoomName', '')
            ->call('createRoom')
            ->assertHasErrors(['newRoomName' => 'required']);

        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_user_renames_a_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user)->create(['name' => 'Salón']);
        $this->actingAs($user);

        Volt::test('shelving.index')
            ->call('startRenaming', $room->id)
            ->set('renamingRoomName', 'Sala de estar')
            ->call('renameRoom')
            ->assertHasNoErrors();

        $this->assertSame('Sala de estar', $room->fresh()->name);
    }

    public function test_deleting_a_room_deletes_its_bookcases_and_structure(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user)->create();
        $bookcase = Bookcase::factory()->for($room)->withShelves([2, 3])->create();
        $this->actingAs($user);

        Volt::test('shelving.index')->call('deleteRoom', $room->id)->assertHasNoErrors();

        $this->assertNull($room->fresh());
        $this->assertNull($bookcase->fresh());
        $this->assertDatabaseCount('shelves', 0);
        $this->assertDatabaseCount('compartments', 0);
    }

    // Ownership (CA-01, CA-02)

    public function test_user_cannot_see_or_modify_rooms_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->for($other)->create(['name' => 'Ajena']);
        $this->actingAs($user);

        $this->get("/admin/users/{$other->id}/rooms")->assertForbidden();

        Volt::test('shelving.index')->call('startRenaming', $room->id)->assertForbidden();
        Volt::test('shelving.index')->call('deleteRoom', $room->id)->assertForbidden();

        $this->assertNotNull($room->fresh());
        $this->assertSame('Ajena', $room->fresh()->name);
    }

    public function test_admin_manages_rooms_of_another_user(): void
    {
        $owner = User::factory()->create();
        $room = Room::factory()->for($owner)->create(['name' => 'Salón']);
        $this->actingAs(User::factory()->admin()->create());

        $this->get("/admin/users/{$owner->id}/rooms")->assertOk()->assertSee('Salón')->assertSee($owner->name);

        Volt::test('shelving.index', ['user' => $owner])
            ->set('newRoomName', 'Despacho')
            ->call('createRoom')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('rooms', ['user_id' => $owner->id, 'name' => 'Despacho']);

        Volt::test('shelving.index', ['user' => $owner])->call('deleteRoom', $room->id)->assertHasNoErrors();

        $this->assertNull($room->fresh());
    }

    public function test_users_panel_links_to_each_users_rooms_for_admins(): void
    {
        $owner = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/users')
            ->assertSee(route('admin.users.rooms', $owner));
    }

    public function test_navigation_links_to_rooms(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertSee(route('rooms.index'))
            ->assertSee('Salas y estanterías');
    }
}
