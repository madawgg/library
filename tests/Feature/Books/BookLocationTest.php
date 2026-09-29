<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\Bookcase;
use App\Models\Compartment;
use App\Models\Room;
use App\Models\User;
use App\Services\BookcaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 002 - RF-05c (location and position) and the book-related criteria of spec 004
 * (CA-03, CA-07, CA-08, CA-09 and CA-09b: books move to the table when the structure shrinks).
 */
class BookLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Bookcase $bookcase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->bookcase = Bookcase::factory()
            ->for(Room::factory()->for($this->owner))
            ->withShelves([2, 4, 3])
            ->create();
    }

    private function compartment(int $shelfNumber, int $compartmentNumber): Compartment
    {
        return $this->bookcase->shelves()->where('number', $shelfNumber)->firstOrFail()
            ->compartments()->where('number', $compartmentNumber)->firstOrFail();
    }

    private function placeBook(Compartment $compartment, int $position, array $attributes = []): Book
    {
        return Book::factory()->for($this->owner)->create([
            'compartment_id' => $compartment->id,
            'position' => $position,
            ...$attributes,
        ]);
    }

    private function editLocation(Book $book, ?Compartment $compartment): Testable
    {
        $shelf = $compartment?->shelf;

        return Volt::test('books.form', ['book' => $book])
            ->set('locationRoomId', $shelf?->bookcase->room_id)
            ->set('locationBookcaseId', $shelf?->bookcase_id)
            ->set('locationShelfId', $shelf?->id)
            ->set('compartmentId', $compartment?->id)
            ->call('save');
    }

    // RF-05c (CA-19, CA-19b, CA-19c)

    public function test_assigning_a_compartment_places_the_book_at_the_end(): void
    {
        $compartment = $this->compartment(2, 3);
        $this->placeBook($compartment, 1);
        $this->placeBook($compartment, 2);
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($this->owner);

        $this->editLocation($book, $compartment)->assertHasNoErrors();

        $book->refresh();

        $this->assertSame($compartment->id, $book->compartment_id);
        $this->assertSame(3, $book->position);
    }

    public function test_a_compartment_of_another_owner_is_rejected(): void
    {
        $foreignCompartment = Bookcase::factory()->withShelves([1])->create()->compartments()->firstOrFail();
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($this->owner);

        Volt::test('books.form', ['book' => $book])
            ->set('compartmentId', $foreignCompartment->id)
            ->call('save')
            ->assertHasErrors(['compartmentId']);

        $this->assertNull($book->fresh()->compartment_id);
    }

    public function test_admin_can_only_place_a_book_in_its_owners_structure(): void
    {
        $adminCompartment = Bookcase::factory()
            ->for(Room::factory()->for($admin = User::factory()->admin()->create()))
            ->withShelves([1])
            ->create()
            ->compartments()
            ->firstOrFail();
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($admin);

        Volt::test('books.form', ['book' => $book])
            ->set('compartmentId', $adminCompartment->id)
            ->call('save')
            ->assertHasErrors(['compartmentId']);

        $this->editLocation($book, $this->compartment(1, 1))->assertHasNoErrors();

        $this->assertSame($this->compartment(1, 1)->id, $book->fresh()->compartment_id);
    }

    public function test_removing_the_location_clears_it_and_renumbers_the_compartment(): void
    {
        $compartment = $this->compartment(1, 1);
        $first = $this->placeBook($compartment, 1);
        $second = $this->placeBook($compartment, 2);
        $third = $this->placeBook($compartment, 3);
        $this->actingAs($this->owner);

        $this->editLocation($second, null)->assertHasNoErrors();

        $this->assertNull($second->fresh()->compartment_id);
        $this->assertNull($second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $third->fresh()->position);
    }

    public function test_moving_to_another_compartment_renumbers_the_old_one(): void
    {
        $origin = $this->compartment(1, 1);
        $moved = $this->placeBook($origin, 1);
        $stays = $this->placeBook($origin, 2);
        $this->actingAs($this->owner);

        $this->editLocation($moved, $this->compartment(3, 3))->assertHasNoErrors();

        $this->assertSame(1, $stays->fresh()->position);
        $this->assertSame(1, $moved->fresh()->position);
    }

    public function test_saving_without_changing_the_compartment_keeps_the_position(): void
    {
        $compartment = $this->compartment(1, 2);
        $this->placeBook($compartment, 1);
        $book = $this->placeBook($compartment, 2);
        $this->placeBook($compartment, 3);
        $this->actingAs($this->owner);

        $this->editLocation($book, $compartment)->assertHasNoErrors();

        $this->assertSame(2, $book->fresh()->position);
    }

    public function test_location_options_are_chained_and_limited_to_the_owner(): void
    {
        Room::factory()->create(['name' => 'Sala ajena']);
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($this->owner);

        Volt::test('books.form', ['book' => $book])
            ->assertViewHas('locationRooms', fn ($rooms) => $rooms->pluck('name')->doesntContain('Sala ajena') && $rooms->count() === 1)
            ->set('locationRoomId', $this->bookcase->room_id)
            ->assertViewHas('locationBookcases', fn ($bookcases) => $bookcases->pluck('id')->all() === [$this->bookcase->id])
            ->set('locationBookcaseId', $this->bookcase->id)
            ->assertViewHas('locationShelves', fn ($shelves) => $shelves->count() === 3)
            ->set('locationShelfId', $this->compartment(2, 1)->shelf_id)
            ->assertViewHas('locationCompartments', fn ($compartments) => $compartments->count() === 4);
    }

    // Spec 004: structure changes move books to the table

    public function test_removing_shelves_moves_their_books_to_the_table(): void
    {
        $onRemovedShelf = $this->placeBook($this->compartment(3, 1), 1);
        $onKeptShelf = $this->placeBook($this->compartment(1, 1), 1);

        app(BookcaseService::class)->update($this->bookcase, $this->bookcase->room, $this->bookcase->name, [
            ['name' => null, 'compartment_names' => [null, null]],
            ['name' => null, 'compartment_names' => [null, null, null, null]],
        ]);

        $this->assertNull($onRemovedShelf->fresh()->compartment_id);
        $this->assertNull($onRemovedShelf->fresh()->position);
        $this->assertSame($this->compartment(1, 1)->id, $onKeptShelf->fresh()->compartment_id);
    }

    public function test_removing_compartments_moves_only_their_books_to_the_table(): void
    {
        $inRemoved = $this->placeBook($this->compartment(2, 4), 1);
        $inKept = $this->placeBook($this->compartment(2, 3), 1);

        app(BookcaseService::class)->update($this->bookcase, $this->bookcase->room, $this->bookcase->name, [
            ['name' => null, 'compartment_names' => [null, null]],
            ['name' => null, 'compartment_names' => [null, null, null]],
            ['name' => null, 'compartment_names' => [null, null, null]],
        ]);

        $this->assertNull($inRemoved->fresh()->compartment_id);
        $this->assertNull($inRemoved->fresh()->position);
        $this->assertSame(1, $inKept->fresh()->position);
    }

    public function test_deleting_a_bookcase_moves_all_its_books_to_the_table(): void
    {
        $books = [$this->placeBook($this->compartment(1, 1), 1), $this->placeBook($this->compartment(3, 2), 1)];
        $this->actingAs($this->owner);

        Volt::test('shelving.index')->call('deleteBookcase', $this->bookcase->id);

        foreach ($books as $book) {
            $this->assertNotNull($book->fresh());
            $this->assertNull($book->fresh()->compartment_id);
            $this->assertNull($book->fresh()->position);
        }
    }

    public function test_deleting_a_room_moves_all_its_books_to_the_table(): void
    {
        $book = $this->placeBook($this->compartment(2, 2), 1);
        $this->actingAs($this->owner);

        Volt::test('shelving.index')->call('deleteRoom', $this->bookcase->room_id);

        $this->assertNotNull($book->fresh());
        $this->assertNull($book->fresh()->compartment_id);
        $this->assertNull($book->fresh()->position);
    }

    public function test_book_page_shows_its_full_location(): void
    {
        $shelf = $this->compartment(2, 1)->shelf;
        $shelf->update(['name' => 'Poesía']);
        $book = $this->placeBook($this->compartment(2, 1), 1);

        $this->actingAs($this->owner)
            ->get("/books/{$book->id}")
            ->assertSeeInOrder([$this->bookcase->room->name, $this->bookcase->name, 'Balda 2', 'Poesía', 'Hueco 1']);
    }
}
