<?php

namespace Tests\Feature\Interface;

use App\Enums\BookView;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 003 - RF-06 (view selector: grid, table and shelf, stored in the account)
 * and spec 004 - RF-05 (the shelf view is only offered from 1280 px).
 */
class BookViewSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_see_the_grid_by_default(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['title' => 'En cuadrícula']);

        $this->assertSame(BookView::Grid, $user->fresh()->book_view);

        $this->actingAs($user)->get('/books')->assertSee('data-book-view="grid"', false)->assertSee('En cuadrícula');
    }

    public function test_chosen_view_is_saved_in_the_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('books.index')->call('setView', 'table')->assertHasNoErrors();

        $this->assertSame(BookView::Table, $user->fresh()->book_view);
        $this->get('/books')->assertSee('data-book-view="table"', false);
    }

    public function test_unknown_views_are_ignored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('books.index')->call('setView', 'carousel');

        $this->assertSame(BookView::Grid, $user->fresh()->book_view);
    }

    public function test_shelf_option_is_only_shown_on_wide_screens(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/books')
            ->assertSee('data-view-option="shelf"', false)
            ->assertSee('hidden xl:inline-flex', false);
    }

    public function test_shelf_view_falls_back_to_the_table_on_narrow_screens(): void
    {
        $user = User::factory()->create(['book_view' => BookView::Shelf]);
        Book::factory()->for($user)->create(['title' => 'Visible en móvil']);

        $this->actingAs($user)
            ->get('/books')
            ->assertSee('data-book-view="shelf"', false)
            ->assertSee('data-shelf-view', false)
            ->assertSee('data-table-fallback', false)
            ->assertSee('Visible en móvil');
    }

    public function test_global_listing_does_not_offer_the_shelf_view(): void
    {
        $admin = User::factory()->admin()->create(['book_view' => BookView::Shelf]);

        $this->actingAs($admin)
            ->get('/admin/books')
            ->assertSee('data-book-view="table"', false)
            ->assertDontSee('data-view-option="shelf"', false);
    }

    public function test_grid_shows_the_cover_or_a_placeholder_with_title_and_author(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('covers/c.webp', 'x');
        $user = User::factory()->create();
        $withCover = Book::factory()->for($user)->create(['title' => 'Con portada']);
        $withCover->forceFill(['cover_path' => 'covers/c.webp'])->save();
        Book::factory()->for($user)->create(['title' => 'Sin portada', 'author' => 'Autora Anónima']);

        $this->actingAs($user)
            ->get('/books')
            ->assertSee(route('books.cover', $withCover))
            ->assertSee('data-cover-placeholder', false)
            ->assertSee('Autora Anónima');
    }
}
