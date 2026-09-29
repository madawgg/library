<?php

namespace Tests\Feature\Interface;

use App\Enums\BookView;
use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 003 - RF-05 (home page summary) and the home notice of RF-07.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-30 10:00'));
        $this->user = User::factory()->create();
    }

    public function test_home_shows_the_totals_of_the_library(): void
    {
        Book::factory()->for($this->user)->count(4)->create(['reading_status' => ReadingStatus::Read]);
        Book::factory()->for($this->user)->count(4)->create();
        $lent = Book::factory()->for($this->user)->count(2)->create(['reading_status' => ReadingStatus::Lent]);
        Loan::factory()->for($lent[0])->create(['loaned_on' => '2026-06-01', 'is_overdue' => true]);
        Loan::factory()->for($lent[1])->create(['loaned_on' => '2026-09-20']);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['Libros', '10', 'Leídos', '4', 'Prestados', '2', 'Préstamos vencidos', '1']);
    }

    public function test_home_only_counts_the_users_own_library(): void
    {
        Book::factory()->for($this->user)->create(['title' => 'Mío']);
        Book::factory()->count(5)->create(['title' => 'Ajeno']);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertSee('Mío')
            ->assertDontSee('Ajeno')
            ->assertSeeInOrder(['Libros', '1', 'Leídos', '0']);
    }

    public function test_home_lists_the_three_latest_books(): void
    {
        foreach (['Primero', 'Segundo', 'Tercero', 'Cuarto'] as $index => $title) {
            Book::factory()->for($this->user)->create(['title' => $title, 'created_at' => now()->addMinutes($index)]);
        }

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertSeeInOrder(['Últimos libros añadidos', 'Cuarto', 'Tercero', 'Segundo'])
            ->assertDontSee('Primero');
    }

    public function test_home_offers_quick_access_links(): void
    {
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertSee(route('books.create'))
            ->assertSee(route('books.index'))
            ->assertSee(route('books.index', ['vista' => 'estanteria']));
    }

    public function test_shelf_quick_access_switches_the_listing_to_the_shelf_view(): void
    {
        $this->actingAs($this->user);

        Volt::test('books.index', ['vista' => 'estanteria']);

        $this->assertSame(BookView::Shelf, $this->user->fresh()->book_view);
    }

    public function test_overdue_loans_notice_lists_book_borrower_and_days(): void
    {
        $book = Book::factory()->for($this->user)->create(['title' => 'Olvidado', 'reading_status' => ReadingStatus::Lent]);
        Loan::factory()->for($book)->create(['borrower_name' => 'Lucía', 'loaned_on' => '2026-06-30', 'is_overdue' => true]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertSee('role="alert"', false)
            ->assertSeeInOrder(['Préstamos vencidos', 'Olvidado', 'Lucía', '92 días']);
    }

    public function test_no_notice_without_overdue_loans(): void
    {
        $book = Book::factory()->for($this->user)->create(['reading_status' => ReadingStatus::Lent]);
        Loan::factory()->for($book)->create(['loaned_on' => '2026-09-20']);

        $this->actingAs($this->user)->get('/dashboard')->assertDontSee('role="alert"', false);
    }
}
