<?php

namespace Tests\Feature\Books;

use App\Enums\BookCondition;
use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Bookcase;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 002 - RF-06 (user listing), RF-07 (global listing for administrators).
 */
class BookListingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function book(array $attributes = []): Book
    {
        return Book::factory()->for($this->owner)->create($attributes);
    }

    /**
     * Titles shown on the current page, in order.
     *
     * @return list<string>
     */
    private function titles($component): array
    {
        return $component->viewData('books')->pluck('title')->all();
    }

    // Default listing (CA-21)

    public function test_listing_shows_own_books_newest_first_15_per_page(): void
    {
        foreach (range(1, 17) as $day) {
            $this->book(['title' => sprintf('Libro %02d', $day), 'created_at' => now()->subDays(20 - $day)]);
        }
        Book::factory()->create(['title' => 'Ajeno']);
        $this->actingAs($this->owner);

        $component = Volt::test('books.index');
        $titles = $this->titles($component);

        $this->assertCount(15, $titles);
        $this->assertSame('Libro 17', $titles[0]);
        $this->assertNotContains('Ajeno', $titles);
        $this->assertSame(17, $component->viewData('books')->total());
    }

    // Search (CA-22)

    public function test_search_matches_title_or_author_and_lists_title_matches_first(): void
    {
        $this->book(['title' => 'Poemas escogidos', 'author' => 'Luis Cernuda', 'created_at' => now()]);
        $this->book(['title' => 'Antología', 'author' => 'Poeta anónimo', 'created_at' => now()->subDay()]);
        $this->book(['title' => 'Ensayos', 'author' => 'Octavio Paz']);
        $this->actingAs($this->owner);

        $component = Volt::test('books.index')->set('search', 'poe');

        $this->assertSame(['Poemas escogidos', 'Antología'], $this->titles($component));

        // Even when the author match is newer, title matches come first.
        $this->book(['title' => 'Recuerdos', 'author' => 'Poemario Colectivo', 'created_at' => now()->addDay()]);

        $this->assertSame('Poemas escogidos', $this->titles(Volt::test('books.index')->set('search', 'poe'))[0]);
    }

    // Filters (CA-21b to CA-21e, CA-23)

    public function test_genre_filter_matches_partially_and_ignores_case(): void
    {
        $this->book(['title' => 'Novela', 'genre' => 'Novela histórica']);
        $this->book(['title' => 'Poesía', 'genre' => 'Poesía']);
        $this->actingAs($this->owner);

        $this->assertSame(['Novela'], $this->titles(Volt::test('books.index')->set('genre', 'nov')));
    }

    public function test_status_and_condition_filters_including_unspecified(): void
    {
        $this->book(['title' => 'Leído', 'reading_status' => ReadingStatus::Read, 'condition' => BookCondition::Good]);
        $this->book(['title' => 'Pendiente', 'reading_status' => ReadingStatus::Pending]);
        $this->book(['title' => 'Sin datos']);
        $this->actingAs($this->owner);

        $this->assertSame(['Leído'], $this->titles(Volt::test('books.index')->set('status', 'read')));
        $this->assertSame(['Sin datos'], $this->titles(Volt::test('books.index')->set('status', 'none')));
        $this->assertSame(['Leído'], $this->titles(Volt::test('books.index')->set('condition', 'good')));
        $this->assertEqualsCanonicalizing(['Pendiente', 'Sin datos'], $this->titles(Volt::test('books.index')->set('condition', 'none')));
    }

    public function test_location_filters_by_room_bookcase_shelf_and_compartment(): void
    {
        $living = Room::factory()->for($this->owner)->create(['name' => 'Salón']);
        $big = Bookcase::factory()->for($living)->withShelves([2, 2])->create();
        $small = Bookcase::factory()->for($living)->withShelves([1])->create();
        $office = Bookcase::factory()->for(Room::factory()->for($this->owner))->withShelves([1])->create();

        $place = function (Bookcase $bookcase, int $shelf, int $compartment, string $title): void {
            $target = $bookcase->shelves()->where('number', $shelf)->first()->compartments()->where('number', $compartment)->first();
            $this->book(['title' => $title, 'compartment_id' => $target->id, 'position' => 1]);
        };

        $place($big, 1, 1, 'Grande 1-1');
        $place($big, 2, 2, 'Grande 2-2');
        $place($small, 1, 1, 'Pequeña 1-1');
        $place($office, 1, 1, 'Despacho');
        $this->book(['title' => 'En la mesa']);
        $this->actingAs($this->owner);

        $this->assertEqualsCanonicalizing(['Grande 1-1', 'Grande 2-2', 'Pequeña 1-1'], $this->titles(Volt::test('books.index')->set('roomId', $living->id)));
        $this->assertEqualsCanonicalizing(['Grande 1-1', 'Grande 2-2'], $this->titles(Volt::test('books.index')->set('bookcaseId', $big->id)));

        $shelfTwo = $big->shelves()->where('number', 2)->first();
        $this->assertSame(['Grande 2-2'], $this->titles(Volt::test('books.index')->set('bookcaseId', $big->id)->set('shelfId', $shelfTwo->id)));

        $compartment = $shelfTwo->compartments()->where('number', 1)->first();
        $this->assertSame([], $this->titles(Volt::test('books.index')->set('bookcaseId', $big->id)->set('shelfId', $shelfTwo->id)->set('compartmentId', $compartment->id)));

        $this->assertSame(['En la mesa'], $this->titles(Volt::test('books.index')->set('bookcaseId', 'none')));
    }

    /**
     * Spec 005 (M-08) replaces the original order of spec 002: room first, then bookcase, shelf and compartment.
     */
    public function test_location_filters_follow_the_room_bookcase_shelf_compartment_order(): void
    {
        Room::factory()->for($this->owner)->create();

        $this->actingAs($this->owner)
            ->get('/books')
            ->assertSeeInOrder(['id="more-filters"', 'Ubicación', 'Sala', 'Estantería', 'Balda', 'Hueco'], false);
    }

    // Sorting (CA-24)

    public function test_sorting_by_title_and_author(): void
    {
        $this->book(['title' => 'Beta', 'author' => 'Zeta']);
        $this->book(['title' => 'Alfa', 'author' => 'Ypsilon']);
        $this->book(['title' => 'Gamma', 'author' => 'Alba']);
        $this->actingAs($this->owner);

        $this->assertSame(['Alfa', 'Beta', 'Gamma'], $this->titles(Volt::test('books.index')->set('sort', 'title')));
        $this->assertSame(['Gamma', 'Alfa', 'Beta'], $this->titles(Volt::test('books.index')->set('sort', 'author')));
        $this->assertSame(['Gamma', 'Beta', 'Alfa'], $this->titles(Volt::test('books.index')->set('sort', 'title')->set('direction', 'desc')));
    }

    // Global listing (CA-25 to CA-27)

    public function test_admin_global_listing_shows_every_library_with_its_owner(): void
    {
        $this->book(['title' => 'De Ana']);
        $other = User::factory()->create(['name' => 'Beatriz Lectora']);
        Book::factory()->for($other)->create(['title' => 'De Beatriz']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/books')
            ->assertOk()
            ->assertSee('<title>Todos los libros · Biblioteca Personal</title>', false)
            ->assertSee('De Ana')
            ->assertSee('De Beatriz')
            ->assertSee('Beatriz Lectora')
            ->assertSee($this->owner->name);
    }

    public function test_admin_filters_the_global_listing_by_owner(): void
    {
        $this->book(['title' => 'De Ana']);
        Book::factory()->create(['title' => 'De otro']);
        $this->actingAs(User::factory()->admin()->create());

        $component = Volt::test('books.index', ['allLibraries' => true])->set('ownerFilter', $this->owner->id);

        $this->assertSame(['De Ana'], $this->titles($component));
    }

    public function test_regular_users_cannot_open_the_global_listing(): void
    {
        $this->actingAs($this->owner)->get('/admin/books')->assertForbidden();

        Volt::test('books.index', ['allLibraries' => true])->assertForbidden();
    }

    public function test_admin_navigation_links_to_all_books(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertSee(route('admin.books.index'))->assertSee('Todos los libros');
        $this->actingAs($this->owner)->get('/dashboard')->assertDontSee(route('admin.books.index'));
    }
}
