<?php

namespace Tests\Feature\Books;

use App\Enums\BookCondition;
use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 002 - RF-02 (creation), RF-04 (ISBN) and RF-05 (editing, except location, cover and loans).
 */
class BookFormTest extends TestCase
{
    use RefreshDatabase;

    // Creation (CA-05 to CA-08)

    public function test_user_creates_a_book_with_only_a_title(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('books.form')
            ->set('title', 'Pedro Páramo')
            ->call('save')
            ->assertHasNoErrors();

        $book = Book::where('title', 'Pedro Páramo')->firstOrFail();

        $this->assertTrue($book->user->is($user));
        $this->assertNull($book->author);
    }

    public function test_creation_form_saves_author_isbn_and_publisher(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('books.form')
            ->set('title', 'Ficciones')
            ->set('author', 'Jorge Luis Borges')
            ->set('isbn', '978-84-206-3363-3')
            ->set('publisher', 'Alianza')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('books', [
            'title' => 'Ficciones',
            'author' => 'Jorge Luis Borges',
            'isbn' => '9788420633633',
            'publisher' => 'Alianza',
        ]);
    }

    public function test_title_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('books.form')->set('title', '')->call('save')->assertHasErrors(['title' => 'required']);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_admin_creates_a_book_in_another_users_library(): void
    {
        $owner = User::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('books.form')
            ->set('ownerId', $owner->id)
            ->set('title', 'Para el usuario')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($owner->id, Book::where('title', 'Para el usuario')->value('user_id'));
    }

    public function test_admin_creation_page_preselects_the_library_owner(): void
    {
        $owner = User::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('books.form', ['owner' => $owner->id])->assertSet('ownerId', $owner->id);
    }

    public function test_user_cannot_assign_a_book_to_another_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        Volt::test('books.form')
            ->set('ownerId', $other->id)
            ->set('title', 'Intento')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('books', ['user_id' => $other->id]);
    }

    // ISBN (CA-13 to CA-17)

    public function test_valid_isbn_10_and_isbn_13_are_accepted(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['0-306-40615-2', '84-376-0494-X', '9780306406157'] as $isbn) {
            Volt::test('books.form')->set('title', "Libro {$isbn}")->set('isbn', $isbn)->call('save')->assertHasNoErrors();
        }

        $this->assertDatabaseCount('books', 3);
    }

    public function test_invalid_isbn_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['12345', '978-84-206-3363-2', 'ABCDEFGHIJ'] as $isbn) {
            Volt::test('books.form')->set('title', 'Libro')->set('isbn', $isbn)->call('save')->assertHasErrors(['isbn']);
        }

        $this->assertDatabaseCount('books', 0);
    }

    public function test_duplicate_isbn_in_the_same_library_asks_for_confirmation(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['isbn' => '9780306406157']);
        $this->actingAs($user);

        $component = Volt::test('books.form')
            ->set('title', 'Segundo ejemplar')
            ->set('isbn', '978-0-306-40615-7')
            ->call('save')
            ->assertSet('duplicateIsbnWarning', true);

        $this->assertDatabaseCount('books', 1);

        $component->call('cancelDuplicate')->assertSet('duplicateIsbnWarning', false);
        $this->assertDatabaseCount('books', 1);

        $component->call('save')->call('saveConfirmingDuplicate')->assertHasNoErrors();
        $this->assertDatabaseCount('books', 2);
    }

    public function test_isbn_in_another_library_is_saved_without_warning(): void
    {
        Book::factory()->create(['isbn' => '9780306406157']);
        $this->actingAs(User::factory()->create());

        Volt::test('books.form')
            ->set('title', 'Mío')
            ->set('isbn', '9780306406157')
            ->call('save')
            ->assertSet('duplicateIsbnWarning', false)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('books', 2);
    }

    public function test_admin_duplicate_check_uses_the_owners_library(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        Book::factory()->for($admin)->create(['isbn' => '9780306406157']);
        $this->actingAs($admin);

        Volt::test('books.form')
            ->set('ownerId', $owner->id)
            ->set('title', 'Sin aviso')
            ->set('isbn', '9780306406157')
            ->call('save')
            ->assertSet('duplicateIsbnWarning', false);

        $this->assertSame(1, Book::where('user_id', $owner->id)->count());

        Volt::test('books.form')
            ->set('ownerId', $owner->id)
            ->set('title', 'Con aviso')
            ->set('isbn', '9780306406157')
            ->call('save')
            ->assertSet('duplicateIsbnWarning', true);

        $this->assertSame(1, Book::where('user_id', $owner->id)->count());
    }

    public function test_editing_a_book_does_not_warn_about_its_own_isbn(): void
    {
        $book = Book::factory()->create(['isbn' => '9780306406157']);
        $this->actingAs($book->user);

        Volt::test('books.form', ['book' => $book])
            ->set('title', 'Título nuevo')
            ->call('save')
            ->assertSet('duplicateIsbnWarning', false)
            ->assertHasNoErrors();

        $this->assertSame('Título nuevo', $book->fresh()->title);
    }

    // Editing (CA-18, CA-20)

    public function test_owner_edits_every_descriptive_field(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);

        Volt::test('books.form', ['book' => $book])
            ->set('title', 'La Regenta')
            ->set('author', 'Leopoldo Alas "Clarín"')
            ->set('publisher', 'Cátedra')
            ->set('publicationYear', 1884)
            ->set('genre', 'Novela')
            ->set('language', 'Español')
            ->set('pages', 912)
            ->set('readingStatus', ReadingStatus::Reading->value)
            ->set('rating', 5)
            ->set('notes', 'Edición anotada.')
            ->set('condition', BookCondition::Good->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('books.show', $book));

        $book->refresh();

        $this->assertSame('La Regenta', $book->title);
        $this->assertSame(1884, $book->publication_year);
        $this->assertSame(912, $book->pages);
        $this->assertSame(ReadingStatus::Reading, $book->reading_status);
        $this->assertSame(5, $book->rating);
        $this->assertSame(BookCondition::Good, $book->condition);
        $this->assertSame('Edición anotada.', $book->notes);
    }

    public function test_out_of_range_or_unknown_values_are_rejected(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);

        Volt::test('books.form', ['book' => $book])->set('rating', 6)->call('save')->assertHasErrors(['rating']);
        Volt::test('books.form', ['book' => $book])->set('rating', 0)->call('save')->assertHasErrors(['rating']);
        Volt::test('books.form', ['book' => $book])->set('condition', 'roto')->call('save')->assertHasErrors(['condition']);
        Volt::test('books.form', ['book' => $book])->set('readingStatus', 'abandonado')->call('save')->assertHasErrors(['readingStatus']);
        Volt::test('books.form', ['book' => $book])->set('pages', 0)->call('save')->assertHasErrors(['pages']);
    }

    public function test_user_cannot_edit_a_book_of_another_user(): void
    {
        $book = Book::factory()->create(['title' => 'Original']);
        $this->actingAs(User::factory()->create());

        Volt::test('books.form', ['book' => $book])->assertForbidden();

        $this->assertSame('Original', $book->fresh()->title);
    }
}
