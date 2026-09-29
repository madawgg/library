<?php

namespace Tests\Feature\Shelving;

use App\Models\Bookcase;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 004 - RF-01 (ownership), RF-03 (bookcases) and the structural part of RF-04.
 * The books moving to the table (CA-07, CA-08, CA-09) are covered with the books spec (002).
 */
class BookcaseManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<int>  $compartmentsPerShelf
     * @return list<array{name: string, compartment_count: int, compartment_names: list<string>}>
     */
    private function shelves(array $compartmentsPerShelf): array
    {
        return array_map(fn (int $count) => [
            'name' => '',
            'compartment_count' => $count,
            'compartment_names' => array_fill(0, $count, ''),
        ], $compartmentsPerShelf);
    }

    /**
     * Compartment counts per shelf, top to bottom.
     *
     * @return list<int>
     */
    private function structureOf(Bookcase $bookcase): array
    {
        return $bookcase->fresh()->shelves()->orderBy('number')->withCount('compartments')->pluck('compartments_count')->all();
    }

    public function test_user_creates_a_bookcase_with_different_compartments_per_shelf(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user)->create();
        $this->actingAs($user);

        Volt::test('shelving.bookcase-form', ['room' => $room])
            ->set('name', 'Estantería del salón')
            ->set('shelves', $this->shelves([2, 4, 3]))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('rooms.index'));

        $bookcase = Bookcase::where('name', 'Estantería del salón')->firstOrFail();

        $this->assertSame($room->id, $bookcase->room_id);
        $this->assertSame([2, 4, 3], $this->structureOf($bookcase));
    }

    public function test_shelves_and_compartments_keep_their_optional_names(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user)->create();
        $this->actingAs($user);

        $shelves = $this->shelves([2]);
        $shelves[0]['name'] = 'Poesía';
        $shelves[0]['compartment_names'] = ['', 'Clásicos'];

        Volt::test('shelving.bookcase-form', ['room' => $room])
            ->set('name', 'Estantería')
            ->set('shelves', $shelves)
            ->call('save')
            ->assertHasNoErrors();

        $shelf = Bookcase::firstOrFail()->shelves()->firstOrFail();

        $this->assertSame(1, $shelf->number);
        $this->assertSame('Poesía', $shelf->name);
        $this->assertSame([null, 'Clásicos'], $shelf->compartments()->orderBy('number')->pluck('name')->all());
    }

    public function test_bookcase_needs_a_name_at_least_one_shelf_and_one_compartment_per_shelf(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user)->create();
        $this->actingAs($user);

        Volt::test('shelving.bookcase-form', ['room' => $room])
            ->set('name', '')
            ->set('shelves', [])
            ->call('save')
            ->assertHasErrors(['name', 'shelves']);

        Volt::test('shelving.bookcase-form', ['room' => $room])
            ->set('name', 'Estantería')
            ->set('shelves', $this->shelves([2, 0]))
            ->call('save')
            ->assertHasErrors(['shelves.1.compartment_count']);

        $this->assertDatabaseCount('bookcases', 0);
    }

    public function test_bookcase_room_must_belong_to_the_same_owner(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user)->create();
        $foreignRoom = Room::factory()->create();
        $this->actingAs($user);

        Volt::test('shelving.bookcase-form', ['room' => $room])
            ->set('name', 'Estantería')
            ->set('roomId', $foreignRoom->id)
            ->set('shelves', $this->shelves([1]))
            ->call('save')
            ->assertHasErrors(['roomId']);

        $this->assertDatabaseCount('bookcases', 0);
    }

    public function test_user_edits_a_bookcase_changing_name_room_and_structure(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user)->create();
        $otherRoom = Room::factory()->for($user)->create();
        $bookcase = Bookcase::factory()->for($room)->withShelves([2, 4, 3])->create();
        $this->actingAs($user);

        Volt::test('shelving.bookcase-form', ['bookcase' => $bookcase])
            ->assertSet('name', $bookcase->name)
            ->set('name', 'Renombrada')
            ->set('roomId', $otherRoom->id)
            ->set('shelves', $this->shelves([2, 3]))
            ->call('save')
            ->assertHasNoErrors();

        $bookcase->refresh();

        $this->assertSame('Renombrada', $bookcase->name);
        $this->assertSame($otherRoom->id, $bookcase->room_id);
        $this->assertSame([2, 3], $this->structureOf($bookcase));
    }

    public function test_growing_a_bookcase_keeps_the_existing_compartments(): void
    {
        $user = User::factory()->create();
        $bookcase = Bookcase::factory()->for(Room::factory()->for($user))->withShelves([2])->create();
        $existingIds = $bookcase->shelves()->first()->compartments()->orderBy('number')->pluck('id')->all();
        $this->actingAs($user);

        Volt::test('shelving.bookcase-form', ['bookcase' => $bookcase])
            ->set('shelves', $this->shelves([4, 1]))
            ->call('save')
            ->assertHasNoErrors();

        $firstShelf = $bookcase->fresh()->shelves()->where('number', 1)->first();

        $this->assertSame([4, 1], $this->structureOf($bookcase));
        $this->assertSame($existingIds, $firstShelf->compartments()->whereIn('number', [1, 2])->orderBy('number')->pluck('id')->all());
    }

    public function test_user_deletes_a_bookcase(): void
    {
        $user = User::factory()->create();
        $bookcase = Bookcase::factory()->for(Room::factory()->for($user))->withShelves([2])->create();
        $this->actingAs($user);

        Volt::test('shelving.index')->call('deleteBookcase', $bookcase->id)->assertHasNoErrors();

        $this->assertNull($bookcase->fresh());
        $this->assertDatabaseCount('compartments', 0);
    }

    // Ownership (CA-01, CA-02)

    public function test_user_cannot_create_edit_or_delete_bookcases_of_another_user(): void
    {
        $foreignRoom = Room::factory()->create();
        $foreignBookcase = Bookcase::factory()->for($foreignRoom)->withShelves([1])->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('bookcases.create', $foreignRoom))->assertForbidden();
        $this->get(route('bookcases.edit', $foreignBookcase))->assertForbidden();

        Volt::test('shelving.index')->call('deleteBookcase', $foreignBookcase->id)->assertForbidden();

        $this->assertNotNull($foreignBookcase->fresh());
    }

    public function test_admin_creates_and_edits_bookcases_of_another_user(): void
    {
        $owner = User::factory()->create();
        $room = Room::factory()->for($owner)->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('bookcases.create', $room))->assertOk();

        Volt::test('shelving.bookcase-form', ['room' => $room])
            ->set('name', 'Del usuario')
            ->set('shelves', $this->shelves([1]))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.rooms', $owner));

        $bookcase = Bookcase::where('name', 'Del usuario')->firstOrFail();

        $this->get(route('bookcases.edit', $bookcase))->assertOk();
    }

    public function test_rooms_page_summarises_each_bookcase(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->for($user)->create();
        Bookcase::factory()->for($room)->withShelves([2, 4, 3])->create(['name' => 'Grande']);

        $this->actingAs($user)
            ->get('/rooms')
            ->assertSee('Grande')
            ->assertSee('3 baldas')
            ->assertSee('9 huecos');
    }
}
