<?php

namespace Tests\Feature\Shelving;

use App\Models\Book;
use App\Models\Bookcase;
use App\Models\Room;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 004 - RF-08 (moving shelves with their compartments and books).
 */
class MoveShelvesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Bookcase $bookcase;

    /** @var array<int, Book> book per shelf number, placed in the first compartment of each shelf */
    private array $books = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->bookcase = Bookcase::factory()->for(Room::factory()->for($this->owner))->withShelves([2, 3, 1])->create();

        foreach ($this->shelves() as $shelf) {
            $shelf->update(['name' => "Balda original {$shelf->number}"]);
            $this->books[$shelf->number] = Book::factory()->for($this->owner)->create([
                'title' => "Libro de la balda {$shelf->number}",
                'compartment_id' => $shelf->compartments()->where('number', 1)->value('id'),
                'position' => 1,
            ]);
        }
    }

    /**
     * @return Collection<int, Shelf>
     */
    private function shelves()
    {
        return $this->bookcase->shelves()->orderBy('number')->get();
    }

    /**
     * Names of the shelves from top to bottom.
     *
     * @return list<?string>
     */
    private function order(): array
    {
        return $this->shelves()->pluck('name')->all();
    }

    /**
     * The book keeps its compartment, so it travels with its shelf.
     */
    private function assertBookStillOnShelfNamed(int $originalNumber): void
    {
        $book = $this->books[$originalNumber]->fresh();

        $this->assertNotNull($book->compartment_id, "The book of shelf {$originalNumber} must not go to the table.");
        $this->assertSame("Balda original {$originalNumber}", $book->compartment->shelf->name);
        $this->assertSame(1, $book->position);
    }

    // Bookcase form (CA-19, CA-20, CA-21)

    public function test_moving_a_shelf_up_in_the_form_carries_its_books(): void
    {
        $this->actingAs($this->owner);

        Volt::test('shelving.bookcase-form', ['bookcase' => $this->bookcase])
            ->call('moveShelfUp', 2)
            ->call('moveShelfUp', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Balda original 3', 'Balda original 1', 'Balda original 2'], $this->order());
        $this->assertSame([1, 2, 3], $this->shelves()->pluck('number')->all());

        foreach ([1, 2, 3] as $originalNumber) {
            $this->assertBookStillOnShelfNamed($originalNumber);
        }

        // Each shelf keeps its compartments: the one that was third had a single compartment.
        $this->assertSame(1, $this->shelves()->first()->compartments()->count());
    }

    public function test_moving_a_shelf_down_in_the_form(): void
    {
        $this->actingAs($this->owner);

        Volt::test('shelving.bookcase-form', ['bookcase' => $this->bookcase])
            ->call('moveShelfDown', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Balda original 2', 'Balda original 1', 'Balda original 3'], $this->order());
        $this->assertBookStillOnShelfNamed(1);
    }

    public function test_a_new_shelf_can_be_moved_to_the_top(): void
    {
        $this->actingAs($this->owner);

        Volt::test('shelving.bookcase-form', ['bookcase' => $this->bookcase])
            ->call('addShelf')
            ->set('shelves.3.name', 'Nueva')
            ->set('shelves.3.compartment_count', 2)
            ->call('moveShelfUp', 3)
            ->call('moveShelfUp', 2)
            ->call('moveShelfUp', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Nueva', 'Balda original 1', 'Balda original 2', 'Balda original 3'], $this->order());
        $this->assertSame(2, $this->shelves()->first()->compartments()->count());

        foreach ([1, 2, 3] as $originalNumber) {
            $this->assertBookStillOnShelfNamed($originalNumber);
        }
    }

    public function test_removing_a_middle_shelf_only_sends_its_own_books_to_the_table(): void
    {
        $this->actingAs($this->owner);

        Volt::test('shelving.bookcase-form', ['bookcase' => $this->bookcase])
            ->call('removeShelf', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Balda original 1', 'Balda original 3'], $this->order());
        $this->assertNull($this->books[2]->fresh()->compartment_id);
        $this->assertBookStillOnShelfNamed(1);
        $this->assertBookStillOnShelfNamed(3);
    }

    public function test_move_buttons_are_offered_in_the_form(): void
    {
        $this->actingAs($this->owner)
            ->get(route('bookcases.edit', $this->bookcase))
            ->assertSee('Subir')
            ->assertSee('Bajar');
    }

    // Shelf view (CA-22, CA-23)

    public function test_moving_a_shelf_in_the_shelf_view_saves_at_once_and_announces_it(): void
    {
        $this->actingAs($this->owner);
        $third = $this->shelves()->last();

        Volt::test('books.shelf-view', ['owner' => $this->owner])
            ->call('moveShelf', $third->id, 1)
            ->assertHasNoErrors()
            ->assertSet('announcement', fn (string $text) => str_contains($text, 'posición 1'));

        $this->assertSame(['Balda original 3', 'Balda original 1', 'Balda original 2'], $this->order());

        foreach ([1, 2, 3] as $originalNumber) {
            $this->assertBookStillOnShelfNamed($originalNumber);
        }
    }

    public function test_shelf_view_offers_drag_handles_and_up_down_buttons(): void
    {
        $this->actingAs($this->owner);

        $html = Volt::test('books.shelf-view', ['owner' => $this->owner])->html();

        $this->assertSame(3, substr_count($html, 'data-shelf-handle'));
        $this->assertStringContainsString('Subir balda', $html);
        $this->assertStringContainsString('Bajar balda', $html);
    }

    public function test_positions_outside_the_bookcase_are_clamped(): void
    {
        $this->actingAs($this->owner);
        $first = $this->shelves()->first();

        Volt::test('books.shelf-view', ['owner' => $this->owner])->call('moveShelf', $first->id, 99);

        $this->assertSame(['Balda original 2', 'Balda original 3', 'Balda original 1'], $this->order());
    }

    public function test_other_users_cannot_move_shelves(): void
    {
        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        Volt::test('books.shelf-view', ['owner' => $intruder])
            ->call('moveShelf', $this->shelves()->last()->id, 1)
            ->assertForbidden();

        $this->assertSame(['Balda original 1', 'Balda original 2', 'Balda original 3'], $this->order());
    }
}
