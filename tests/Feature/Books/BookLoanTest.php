<?php

namespace Tests\Feature\Books;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 002 - RF-05b (loans): loan data, history, return date, overdue flag and the daily check.
 */
class BookLoanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-29 10:00'));
    }

    private function lend(Book $book, string $borrower, string $date): void
    {
        Volt::test('books.form', ['book' => $book->fresh()])
            ->set('readingStatus', ReadingStatus::Lent->value)
            ->set('loanBorrower', $borrower)
            ->set('loanDate', $date)
            ->call('save')
            ->assertHasNoErrors();
    }

    private function giveBack(Book $book, ReadingStatus $status = ReadingStatus::Read): void
    {
        Volt::test('books.form', ['book' => $book->fresh()])
            ->set('readingStatus', $status->value)
            ->call('save')
            ->assertHasNoErrors();
    }

    // Loan data (CA-20b)

    public function test_lending_requires_borrower_and_date(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);

        Volt::test('books.form', ['book' => $book])
            ->set('readingStatus', ReadingStatus::Lent->value)
            ->call('save')
            ->assertHasErrors(['loanBorrower', 'loanDate']);

        $this->assertNull($book->fresh()->reading_status);
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_lending_a_book_records_an_active_loan(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);

        $this->lend($book, 'Lucía', '2026-09-20');

        $this->assertSame(ReadingStatus::Lent, $book->fresh()->reading_status);
        $this->assertDatabaseHas('loans', ['book_id' => $book->id, 'borrower_name' => 'Lucía', 'returned_on' => null]);
    }

    public function test_editing_the_borrower_while_lent_corrects_the_active_loan(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);
        $this->lend($book, 'Lucia', '2026-09-20');

        Volt::test('books.form', ['book' => $book->fresh()])
            ->assertSet('loanBorrower', 'Lucia')
            ->assertSet('loanDate', '2026-09-20')
            ->set('loanBorrower', 'Lucía')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Loan::count());
        $this->assertSame('Lucía', Loan::first()->borrower_name);
    }

    // Return (CA-20c, CA-20f, CA-20o)

    public function test_returning_keeps_the_loan_and_records_todays_return_date(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);
        $this->lend($book, 'Lucía', '2026-09-20');

        $this->giveBack($book);

        $loan = Loan::firstOrFail();

        $this->assertSame(ReadingStatus::Read, $book->fresh()->reading_status);
        $this->assertSame('2026-09-29', $loan->returned_on->toDateString());
    }

    public function test_returning_clears_the_overdue_flag(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);
        $this->lend($book, 'Lucía', '2026-06-01');
        $this->artisan('loans:check-overdue')->assertSuccessful();
        $this->assertTrue(Loan::firstOrFail()->is_overdue);

        $this->giveBack($book);

        $this->assertFalse(Loan::firstOrFail()->fresh()->is_overdue);
    }

    public function test_lending_again_creates_a_new_loan_in_the_history(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);

        $this->lend($book, 'Lucía', '2026-08-01');
        $this->giveBack($book);
        $this->lend($book, 'Mario', '2026-09-25');

        $this->assertSame(2, Loan::count());
        $this->assertSame(1, Loan::whereNull('returned_on')->count());
    }

    // History (CA-20d, CA-20e, CA-20m, CA-20n, CA-20o)

    public function test_history_lists_every_loan_with_the_days_lent(): void
    {
        $book = Book::factory()->create();
        Loan::factory()->for($book)->create(['borrower_name' => 'Lucía', 'loaned_on' => '2026-03-01', 'returned_on' => '2026-03-11']);
        Loan::factory()->for($book)->create(['borrower_name' => 'Mario', 'loaned_on' => '2026-09-24', 'returned_on' => null]);

        $this->actingAs($book->user)
            ->get(route('books.loans', $book))
            ->assertOk()
            ->assertSee('<title>Historial de préstamos · Biblioteca Personal</title>', false)
            ->assertSeeInOrder(['Mario', '5 días', 'Lucía', '10 días']);
    }

    public function test_returned_overdue_loan_still_shows_its_days(): void
    {
        $book = Book::factory()->create();
        Loan::factory()->for($book)->create(['borrower_name' => 'Lucía', 'loaned_on' => '2026-05-01', 'returned_on' => '2026-08-01', 'is_overdue' => false]);

        $this->actingAs($book->user)->get(route('books.loans', $book))->assertSee('92 días');
    }

    public function test_book_page_links_to_the_history(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($book->user)
            ->get(route('books.show', $book))
            ->assertSee('Ver historial de préstamos')
            ->assertSee(route('books.loans', $book));
    }

    public function test_user_cannot_open_the_history_of_another_users_book(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('books.loans', $book))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('books.loans', $book))->assertOk();
    }

    // Editing the history (CA-20g, CA-20h)

    public function test_owner_cannot_edit_or_delete_loans(): void
    {
        $book = Book::factory()->create();
        $loan = Loan::factory()->for($book)->create(['borrower_name' => 'Lucía', 'loaned_on' => '2026-03-01', 'returned_on' => '2026-03-11']);
        $this->actingAs($book->user);

        Volt::test('books.loans', ['book' => $book])->call('startEditing', $loan->id)->assertForbidden();
        Volt::test('books.loans', ['book' => $book])->call('deleteLoan', $loan->id)->assertForbidden();

        $this->assertNotNull($loan->fresh());
    }

    public function test_admin_edits_and_deletes_loans(): void
    {
        $book = Book::factory()->create();
        $loan = Loan::factory()->for($book)->create(['borrower_name' => 'Lucia', 'loaned_on' => '2026-03-01', 'returned_on' => '2026-03-11']);
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('books.loans', ['book' => $book])
            ->call('startEditing', $loan->id)
            ->set('editBorrower', 'Lucía')
            ->set('editLoanedOn', '2026-03-02')
            ->set('editReturnedOn', '2026-03-12')
            ->call('saveLoan')
            ->assertHasNoErrors();

        $loan->refresh();
        $this->assertSame('Lucía', $loan->borrower_name);
        $this->assertSame('2026-03-12', $loan->returned_on->toDateString());

        Volt::test('books.loans', ['book' => $book])->call('deleteLoan', $loan->id)->assertHasNoErrors();

        $this->assertNull($loan->fresh());
    }

    public function test_the_active_loan_cannot_be_deleted_while_the_book_is_lent(): void
    {
        $book = Book::factory()->create(['reading_status' => ReadingStatus::Lent]);
        $loan = Loan::factory()->for($book)->create(['loaned_on' => '2026-09-01', 'returned_on' => null]);
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('books.loans', ['book' => $book])->call('deleteLoan', $loan->id)->assertHasErrors(['loan']);

        $this->assertNotNull($loan->fresh());
    }

    // Overdue check (CA-20i, CA-20j, CA-20k, CA-20l)

    public function test_daily_check_flags_only_active_loans_older_than_two_months(): void
    {
        $old = Loan::factory()->create(['loaned_on' => '2026-07-28', 'returned_on' => null]);
        $exactlyTwoMonths = Loan::factory()->create(['loaned_on' => '2026-07-29', 'returned_on' => null]);
        $recent = Loan::factory()->create(['loaned_on' => '2026-09-01', 'returned_on' => null]);
        $returnedEarly = Loan::factory()->create(['loaned_on' => '2026-06-01', 'returned_on' => '2026-07-01']);

        $this->artisan('loans:check-overdue')->assertSuccessful();

        $this->assertTrue($old->fresh()->is_overdue);
        $this->assertFalse($exactlyTwoMonths->fresh()->is_overdue);
        $this->assertFalse($recent->fresh()->is_overdue);
        $this->assertFalse($returnedEarly->fresh()->is_overdue);
    }

    public function test_the_check_is_scheduled_every_day_at_two_in_the_morning(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'loans:check-overdue'));

        $this->assertNotNull($event);
        $this->assertSame('0 2 * * *', $event->expression);
    }
}
