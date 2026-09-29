<?php

namespace Tests\Feature\Interface;

use App\Enums\BookView;
use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Bookcase;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 003 - RF-07 (overdue loan badge in every view and listing filter).
 */
class OverdueAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $overdue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->overdue = Book::factory()->for($this->user)->create(['title' => 'Vencido', 'reading_status' => ReadingStatus::Lent]);
        Loan::factory()->for($this->overdue)->create(['loaned_on' => now()->subMonths(3), 'is_overdue' => true]);

        $onTime = Book::factory()->for($this->user)->create(['title' => 'A tiempo', 'reading_status' => ReadingStatus::Lent]);
        Loan::factory()->for($onTime)->create(['loaned_on' => now()->subDays(3)]);

        $returned = Book::factory()->for($this->user)->create(['title' => 'Devuelto', 'reading_status' => ReadingStatus::Read]);
        Loan::factory()->for($returned)->create(['loaned_on' => now()->subMonths(5), 'returned_on' => now()->subMonth()]);
    }

    public function test_badge_is_shown_with_text_in_the_grid_and_the_table(): void
    {
        $this->actingAs($this->user);

        foreach ([BookView::Grid, BookView::Table] as $view) {
            $this->user->update(['book_view' => $view]);

            $html = $this->get('/books')->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'data-overdue-badge'), "View {$view->value}");
            $this->assertStringContainsString('Préstamo vencido', $html);
        }
    }

    public function test_badge_is_shown_on_the_spine_and_on_the_book_page(): void
    {
        $compartment = Bookcase::factory()->for(Room::factory()->for($this->user))->withShelves([1])->create()->compartments()->first();
        $this->overdue->forceFill(['compartment_id' => $compartment->id, 'position' => 1])->save();
        $this->actingAs($this->user);

        $this->assertStringContainsString('data-overdue-badge', Volt::test('books.shelf-view', ['owner' => $this->user])->html());

        $this->get(route('books.show', $this->overdue))->assertSee('data-overdue-badge', false)->assertSee('Préstamo vencido');
    }

    public function test_listing_filter_shows_only_books_with_an_overdue_loan(): void
    {
        $this->actingAs($this->user);

        $titles = Volt::test('books.index')->set('overdueOnly', true)->viewData('books')->pluck('title')->all();

        $this->assertSame(['Vencido'], $titles);
    }
}
