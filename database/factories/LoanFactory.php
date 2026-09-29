<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'borrower_name' => fake()->firstName(),
            'loaned_on' => now()->subDays(10)->toDateString(),
            'returned_on' => null,
            'is_overdue' => false,
        ];
    }
}
