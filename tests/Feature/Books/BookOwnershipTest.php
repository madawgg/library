<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 002 - RF-01 (ownership), RF-08 (a user's library for admins), RF-09 (deletion) and RF-10 (account deletion).
 */
class BookOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_every_book_page(): void
    {
        $book = Book::factory()->create();

        foreach (['/books', '/books/create', "/books/{$book->id}", "/books/{$book->id}/edit"] as $page) {
            $this->get($page)->assertRedirect('/login');
        }
    }

    public function test_owner_sees_their_book(): void
    {
        $book = Book::factory()->create(['title' => 'Cien años de soledad']);

        $this->actingAs($book->user)
            ->get("/books/{$book->id}")
            ->assertOk()
            ->assertSee('Cien años de soledad')
            ->assertSee('<title>Cien años de soledad · Biblioteca Personal</title>', false);
    }

    public function test_user_cannot_see_edit_or_delete_a_book_of_another_user(): void
    {
        $book = Book::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get("/books/{$book->id}")->assertForbidden();
        $this->get("/books/{$book->id}/edit")->assertForbidden();

        Volt::test('books.show', ['book' => $book])->assertForbidden();

        $this->assertNotNull($book->fresh());
    }

    public function test_admin_sees_edits_and_deletes_books_of_any_user(): void
    {
        $book = Book::factory()->create(['title' => 'Rayuela']);
        $this->actingAs(User::factory()->admin()->create());

        $this->get("/books/{$book->id}")->assertOk()->assertSee('Rayuela');
        $this->get("/books/{$book->id}/edit")->assertOk();

        Volt::test('books.show', ['book' => $book])
            ->call('delete')
            ->assertRedirect(route('admin.users.books', $book->user));

        $this->assertNull($book->fresh());
    }

    public function test_owner_deletes_their_book(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);

        Volt::test('books.show', ['book' => $book])
            ->call('delete')
            ->assertRedirect(route('books.index'));

        $this->assertNull($book->fresh());
    }

    public function test_user_library_lists_only_their_books(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['title' => 'Mío']);
        Book::factory()->create(['title' => 'Ajeno']);

        $this->actingAs($user)->get('/books')->assertOk()->assertSee('Mío')->assertDontSee('Ajeno');
    }

    public function test_admin_opens_the_library_of_a_user_from_the_users_panel(): void
    {
        $owner = User::factory()->create();
        Book::factory()->for($owner)->create(['title' => 'Del usuario']);
        Book::factory()->create(['title' => 'De otro']);
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/users')->assertSee(route('admin.users.books', $owner));

        $this->get("/admin/users/{$owner->id}/books")
            ->assertOk()
            ->assertSee('Del usuario')
            ->assertDontSee('De otro')
            ->assertSee($owner->name);
    }

    public function test_user_cannot_open_the_library_of_another_user(): void
    {
        $owner = User::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get("/admin/users/{$owner->id}/books")->assertForbidden();
    }

    public function test_deleting_an_account_deletes_its_books(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->count(3)->create();
        $this->actingAs($user);

        Volt::test('settings.delete-user-form')->set('password', 'password')->call('deleteUser');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_navigation_links_to_my_books(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertSee(route('books.index'))
            ->assertSee('Mis libros');
    }
}
