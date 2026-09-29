<?php

namespace Tests\Feature\Improvements;

use App\Enums\BookView;
use App\Models\Book;
use App\Models\Bookcase;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 005 - improvements M-01 to M-07.
 */
class ImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Room $room;

    private Bookcase $bookcase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->room = Room::factory()->for($this->owner)->create(['name' => 'Salón']);
        $this->bookcase = Bookcase::factory()->for($this->room)->withShelves([2, 3])->create(['name' => 'Estantería grande']);
    }

    private function placeIn(Bookcase $bookcase, string $title): Book
    {
        $compartment = $bookcase->compartments()->orderBy('compartments.id')->firstOrFail();

        return Book::factory()->for($bookcase->room->user)->create(['title' => $title, 'compartment_id' => $compartment->id, 'position' => $compartment->books()->count() + 1]);
    }

    private function shelfLink(Bookcase $bookcase): string
    {
        return route('books.index', ['vista' => 'estanteria', 'bookcase' => $bookcase->id]);
    }

    // M-01 (CA-01) and M-02 (CA-02)

    public function test_book_edit_form_uses_the_full_width_but_the_creation_form_does_not(): void
    {
        $book = Book::factory()->for($this->owner)->create();
        $this->actingAs($this->owner);

        $this->get(route('books.edit', $book))->assertSee('data-wide-form', false);
        $this->get(route('books.create'))->assertDontSee('data-wide-form', false);
    }

    public function test_book_page_uses_the_full_width(): void
    {
        $book = Book::factory()->for($this->owner)->create();

        $this->actingAs($this->owner)
            ->get(route('books.show', $book))
            ->assertSee('data-book-page', false)
            ->assertDontSee('max-w-3xl', false);
    }

    // M-03 (CA-03) and M-04 (CA-04)

    public function test_shift_click_on_a_spine_opens_the_book_page(): void
    {
        $book = $this->placeIn($this->bookcase, 'Rayuela');
        $this->actingAs($this->owner);

        Volt::test('books.shelf-view', ['owner' => $this->owner])
            ->assertSeeHtml('data-show-url="'.route('books.show', $book).'"')
            ->assertSeeHtml('$event.shiftKey');
    }

    public function test_spines_are_24_px_wide(): void
    {
        $book = $this->placeIn($this->bookcase, 'Fino');
        $this->actingAs($this->owner);

        $html = Volt::test('books.shelf-view', ['owner' => $this->owner])->html();

        $this->assertMatchesRegularExpression('/data-spine="'.$book->id.'"[^>]*class="[^"]*\bw-6\b/s', $html);
    }

    // M-05 (CA-05, CA-06)

    public function test_only_search_and_sorting_are_visible_the_rest_is_in_more_filters(): void
    {
        $this->actingAs($this->owner)
            ->get('/books')
            ->assertSeeInOrder([
                'data-filters-main',
                'Buscar por título o autor',
                'Ordenar por',
                'Orden',
                'Quitar filtros',
                'Más filtros',
                'id="more-filters"',
                'Género',
                'Estado de lectura',
                'Condición',
                'Solo préstamos vencidos',
            ], false);
    }

    public function test_owner_filter_of_the_global_listing_is_inside_more_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/books')
            ->assertSeeInOrder(['Quitar filtros', 'id="more-filters"', 'Propietario'], false);
    }

    public function test_more_filters_button_counts_the_active_filters(): void
    {
        $this->actingAs($this->owner);

        Volt::test('books.index')
            ->assertSee('Más filtros')
            ->assertDontSee('Más filtros (')
            ->set('genre', 'nov')
            ->set('status', 'read')
            ->assertSee('Más filtros (2)');
    }

    // M-06 (CA-07)

    public function test_room_name_links_to_the_room_page_with_a_card_per_bookcase(): void
    {
        $second = Bookcase::factory()->for($this->room)->withShelves([1])->create(['name' => 'Estantería pequeña']);
        $this->placeIn($this->bookcase, 'Uno');
        $this->placeIn($this->bookcase, 'Dos');
        $this->actingAs($this->owner);

        $this->get('/rooms')->assertSee(route('rooms.show', $this->room));

        $this->get(route('rooms.show', $this->room))
            ->assertOk()
            ->assertSee('<title>Salón · Biblioteca Personal</title>', false)
            ->assertSeeInOrder(['Estantería grande', '2 libros', 'Estantería pequeña', '0 libros'])
            ->assertSee('data-mini-shelf', false)
            ->assertSee($this->shelfLink($this->bookcase))
            ->assertSee($this->shelfLink($second));
    }

    public function test_room_page_is_only_for_the_owner_and_administrators(): void
    {
        $this->actingAs(User::factory()->create())->get(route('rooms.show', $this->room))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('rooms.show', $this->room))->assertOk();
    }

    // M-07 (CA-08)

    public function test_bookcase_name_links_to_its_shelf_view(): void
    {
        $this->actingAs($this->owner)->get('/rooms')->assertSee($this->shelfLink($this->bookcase));
    }

    public function test_the_shelf_link_opens_the_shelf_view_on_that_bookcase_or_the_filtered_table(): void
    {
        $other = Bookcase::factory()->for($this->room)->withShelves([1])->create(['name' => 'Otra']);
        $this->placeIn($this->bookcase, 'Dentro');
        $this->placeIn($other, 'Fuera');
        $this->actingAs($this->owner);

        $this->get($this->shelfLink($this->bookcase))
            ->assertSee('data-book-view="shelf"', false)
            ->assertSeeInOrder(['data-table-fallback', 'Dentro'], false)
            ->assertDontSee('Fuera');

        $this->assertSame(BookView::Shelf, $this->owner->fresh()->book_view);

        Volt::test('books.shelf-view', ['owner' => $this->owner, 'bookcase' => $other->id])->assertSet('bookcaseId', $other->id);
    }

    // CA-09

    public function test_admin_links_go_to_the_owners_library(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $expected = route('admin.users.books', ['user' => $this->owner, 'vista' => 'estanteria', 'bookcase' => $this->bookcase->id]);

        $this->get(route('admin.users.rooms', $this->owner))->assertSee($expected);
        $this->get(route('rooms.show', $this->room))->assertSee($expected);
    }
}
