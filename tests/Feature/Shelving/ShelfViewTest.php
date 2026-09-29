<?php

namespace Tests\Feature\Shelving;

use App\Models\Book;
use App\Models\Bookcase;
use App\Models\Compartment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 004 - RF-05 (shelf view and table), RF-06 (drag and drop) and RF-07 (alternatives to dragging).
 * Drag and drop and the keyboard alternatives all end in the same component actions tested here.
 */
class ShelfViewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Bookcase $bookcase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->bookcase = Bookcase::factory()
            ->for(Room::factory()->for($this->owner)->create(['name' => 'Salón']))
            ->withShelves([2, 3])
            ->create(['name' => 'Estantería grande']);
    }

    private function compartment(int $shelf, int $number): Compartment
    {
        return $this->bookcase->shelves()->where('number', $shelf)->firstOrFail()
            ->compartments()->where('number', $number)->firstOrFail();
    }

    private function placed(Compartment $compartment, int $position, string $title): Book
    {
        return Book::factory()->for($this->owner)->create(['title' => $title, 'compartment_id' => $compartment->id, 'position' => $position]);
    }

    /**
     * Titles in a compartment, in position order.
     *
     * @return list<string>
     */
    private function titlesIn(Compartment $compartment): array
    {
        return $compartment->books()->orderBy('position')->pluck('title')->all();
    }

    private function shelfView()
    {
        return Volt::test('books.shelf-view', ['owner' => $this->owner]);
    }

    // Drawing (CA-12, CA-12c, CA-12d)

    public function test_draws_the_chosen_bookcase_with_its_books_in_order(): void
    {
        $this->bookcase->shelves()->where('number', 2)->update(['name' => 'Poesía']);
        $this->placed($this->compartment(1, 1), 2, 'Segundo');
        $this->placed($this->compartment(1, 1), 1, 'Primero');
        $this->actingAs($this->owner);

        $this->shelfView()
            ->assertSet('bookcaseId', $this->bookcase->id)
            ->assertSeeInOrder(['Balda 1', 'Hueco 1', 'Primero', 'Segundo', 'Balda 2 · Poesía', 'Hueco 3']);
    }

    public function test_each_spine_carries_the_book_details_shown_on_hover_or_focus(): void
    {
        $book = $this->placed($this->compartment(1, 1), 1, 'Rayuela');
        $book->update(['author' => 'Julio Cortázar']);
        $this->actingAs($this->owner);

        $this->shelfView()
            ->assertSeeHtml('data-spine="'.$book->id.'"')
            ->assertSeeHtml('role="tooltip"')
            ->assertSee('Julio Cortázar');
    }

    public function test_room_and_bookcase_selectors_choose_the_bookcase(): void
    {
        $other = Bookcase::factory()->for(Room::factory()->for($this->owner)->create(['name' => 'Despacho']))->withShelves([1])->create(['name' => 'Estantería del despacho']);
        $this->actingAs($this->owner);

        $this->shelfView()
            ->set('roomId', $other->room_id)
            ->assertSet('bookcaseId', $other->id)
            ->assertSee('Estantería del despacho');
    }

    public function test_user_without_bookcases_is_told_to_create_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('books.shelf-view', ['owner' => $user])->assertSee(route('rooms.index'));
    }

    // Table (CA-12, CA-12b)

    public function test_table_shows_unplaced_books_alphabetically_eight_at_a_time(): void
    {
        foreach (range(1, 20) as $number) {
            Book::factory()->for($this->owner)->create(['title' => sprintf('Libro %02d', 21 - $number)]);
        }
        $this->placed($this->compartment(1, 1), 1, 'Ubicado');
        $this->actingAs($this->owner);

        $view = $this->shelfView();
        $this->assertSame(['Libro 01', 'Libro 02', 'Libro 03', 'Libro 04', 'Libro 05', 'Libro 06', 'Libro 07', 'Libro 08'], $view->viewData('tableBooks')->pluck('title')->all());

        $view->call('nextTablePage');
        $this->assertSame('Libro 09', $view->viewData('tableBooks')->first()->title);

        $view->call('nextTablePage');
        $this->assertSame(['Libro 17', 'Libro 18', 'Libro 19', 'Libro 20'], $view->viewData('tableBooks')->pluck('title')->all());

        $view->call('nextTablePage');
        $this->assertSame('Libro 17', $view->viewData('tableBooks')->first()->title);

        $view->call('previousTablePage')->call('previousTablePage');
        $this->assertSame('Libro 01', $view->viewData('tableBooks')->first()->title);
    }

    // Moving (CA-13, CA-14, CA-15, CA-18, CA-03)

    public function test_moving_a_book_from_the_table_to_a_position_in_a_compartment(): void
    {
        $target = $this->compartment(2, 2);
        $this->placed($target, 1, 'A');
        $this->placed($target, 2, 'C');
        $book = Book::factory()->for($this->owner)->create(['title' => 'B']);
        $this->actingAs($this->owner);

        $this->shelfView()->call('moveBook', $book->id, $target->id, 2)->assertHasNoErrors();

        $this->assertSame(['A', 'B', 'C'], $this->titlesIn($target));
        $this->assertSame([1, 2, 3], $target->books()->orderBy('position')->pluck('position')->all());
    }

    public function test_reordering_inside_a_compartment(): void
    {
        $compartment = $this->compartment(1, 2);
        $first = $this->placed($compartment, 1, 'Uno');
        $this->placed($compartment, 2, 'Dos');
        $this->placed($compartment, 3, 'Tres');
        $this->actingAs($this->owner);

        $this->shelfView()->call('moveBook', $first->id, $compartment->id, 3);

        $this->assertSame(['Dos', 'Tres', 'Uno'], $this->titlesIn($compartment));
        $this->assertSame([1, 2, 3], $compartment->books()->orderBy('position')->pluck('position')->all());
    }

    public function test_moving_between_compartments_renumbers_both(): void
    {
        $origin = $this->compartment(1, 1);
        $destination = $this->compartment(2, 1);
        $moving = $this->placed($origin, 1, 'Viajero');
        $this->placed($origin, 2, 'Se queda');
        $this->placed($destination, 1, 'Residente');
        $this->actingAs($this->owner);

        $this->shelfView()->call('moveBook', $moving->id, $destination->id, 1);

        $this->assertSame(['Se queda'], $this->titlesIn($origin));
        $this->assertSame(1, $origin->books()->first()->position);
        $this->assertSame(['Viajero', 'Residente'], $this->titlesIn($destination));
    }

    public function test_moving_a_book_to_the_table_clears_its_location_and_renumbers(): void
    {
        $compartment = $this->compartment(1, 1);
        $moving = $this->placed($compartment, 1, 'A la mesa');
        $this->placed($compartment, 2, 'Resto');
        $this->actingAs($this->owner);

        $this->shelfView()->call('moveBook', $moving->id, null, null)->assertSet('announcement', fn ($text) => str_contains($text, 'mesa'));

        $this->assertNull($moving->fresh()->compartment_id);
        $this->assertNull($moving->fresh()->position);
        $this->assertSame(1, $compartment->books()->first()->position);
    }

    public function test_position_beyond_the_end_places_the_book_last(): void
    {
        $compartment = $this->compartment(1, 1);
        $this->placed($compartment, 1, 'Existente');
        $book = Book::factory()->for($this->owner)->create(['title' => 'Nuevo']);
        $this->actingAs($this->owner);

        $this->shelfView()->call('moveBook', $book->id, $compartment->id, 99);

        $this->assertSame(['Existente', 'Nuevo'], $this->titlesIn($compartment));
        $this->assertSame(2, $book->fresh()->position);
    }

    public function test_books_cannot_be_moved_to_another_owners_compartment(): void
    {
        $foreign = Bookcase::factory()->withShelves([1])->create()->compartments()->firstOrFail();
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($this->owner);

        $this->shelfView()->call('moveBook', $book->id, $foreign->id, 1)->assertHasErrors(['move']);

        $this->assertNull($book->fresh()->compartment_id);
    }

    public function test_other_users_cannot_open_or_move_in_someone_elses_shelf_view(): void
    {
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs(User::factory()->create());

        $this->shelfView()->assertForbidden();
    }

    public function test_admin_moves_books_of_any_user(): void
    {
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->shelfView()->call('moveBook', $book->id, $this->compartment(1, 1)->id, 1)->assertHasNoErrors();

        $this->assertSame($this->compartment(1, 1)->id, $book->fresh()->compartment_id);
    }

    // "Mover a…" (CA-16)

    public function test_move_to_dialog_places_a_book_with_chosen_location_and_position(): void
    {
        $target = $this->compartment(2, 3);
        $this->placed($target, 1, 'Primero');
        $book = Book::factory()->for($this->owner)->create(['title' => 'Elegido']);
        $this->actingAs($this->owner);

        $this->shelfView()
            ->call('openMoveDialog', $book->id)
            ->assertSet('movingBookId', $book->id)
            ->set('moveBookcaseId', $this->bookcase->id)
            ->set('moveShelfId', $target->shelf_id)
            ->set('moveCompartmentId', $target->id)
            ->set('movePosition', 1)
            ->call('confirmMove')
            ->assertHasNoErrors()
            ->assertSet('movingBookId', null);

        $this->assertSame(['Elegido', 'Primero'], $this->titlesIn($target));
    }

    public function test_move_to_dialog_can_send_a_book_to_the_table(): void
    {
        $book = $this->placed($this->compartment(1, 1), 1, 'De vuelta');
        $this->actingAs($this->owner);

        $this->shelfView()
            ->call('openMoveDialog', $book->id)
            ->set('moveToTable', true)
            ->call('confirmMove')
            ->assertHasNoErrors();

        $this->assertNull($book->fresh()->compartment_id);
    }

    public function test_move_to_dialog_requires_a_compartment_unless_moving_to_the_table(): void
    {
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($this->owner);

        $this->shelfView()
            ->call('openMoveDialog', $book->id)
            ->call('confirmMove')
            ->assertHasErrors(['moveCompartmentId']);
    }

    // "Seleccionar y colocar" (CA-16, CA-17)

    public function test_select_and_place_moves_the_selected_book(): void
    {
        $target = $this->compartment(1, 2);
        $book = Book::factory()->for($this->owner)->create(['title' => 'Seleccionado']);
        $this->actingAs($this->owner);

        $this->shelfView()
            ->call('selectBook', $book->id)
            ->assertSet('selectedBookId', $book->id)
            ->assertSee('Colocar aquí')
            ->call('placeSelected', $target->id, 1)
            ->assertSet('selectedBookId', null);

        $this->assertSame($target->id, $book->fresh()->compartment_id);
    }

    public function test_cancelling_the_selection_changes_nothing(): void
    {
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($this->owner);

        $this->shelfView()
            ->call('selectBook', $book->id)
            ->call('cancelSelection')
            ->assertSet('selectedBookId', null);

        $this->assertNull($book->fresh()->compartment_id);
    }

    // Accessibility (RF-07)

    public function test_moves_are_announced_to_screen_readers(): void
    {
        $book = Book::factory()->for($this->owner)->create(['title' => 'Anunciado']);
        $this->actingAs($this->owner);

        $this->shelfView()
            ->assertSeeHtml('aria-live="polite"')
            ->call('moveBook', $book->id, $this->compartment(2, 1)->id, 1)
            ->assertSet('announcement', fn ($text) => str_contains($text, 'Anunciado') && str_contains($text, 'Balda 2') && str_contains($text, 'Hueco 1'));
    }

    public function test_focus_returns_to_the_book_after_moving_or_cancelling(): void
    {
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($this->owner);

        $this->shelfView()->call('openMoveDialog', $book->id)->call('closeMoveDialog')->assertDispatched('shelf-focus-book', id: $book->id);
        $this->shelfView()->call('selectBook', $book->id)->call('cancelSelection')->assertDispatched('shelf-focus-book', id: $book->id);
        $this->shelfView()->call('selectBook', $book->id)->call('placeSelected', $this->compartment(1, 1)->id, 1)->assertDispatched('shelf-focus-book', id: $book->id);
    }

    public function test_every_book_offers_the_move_menu(): void
    {
        $this->placed($this->compartment(1, 1), 1, 'En estantería');
        Book::factory()->for($this->owner)->create(['title' => 'En la mesa']);
        $this->actingAs($this->owner);

        $html = $this->shelfView()->html();

        $this->assertSame(2, substr_count($html, 'Mover a…'));
        $this->assertSame(2, substr_count($html, 'Seleccionar y colocar'));
    }
}
