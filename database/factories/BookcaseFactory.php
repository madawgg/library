<?php

namespace Database\Factories;

use App\Models\Bookcase;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookcase>
 */
class BookcaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'name' => 'Estantería '.fake()->unique()->numberBetween(1, 9999),
        ];
    }

    /**
     * Create the shelves with the given number of compartments each, top to bottom.
     *
     * @param  list<int>  $compartmentsPerShelf
     */
    public function withShelves(array $compartmentsPerShelf): static
    {
        return $this->afterCreating(function (Bookcase $bookcase) use ($compartmentsPerShelf) {
            foreach ($compartmentsPerShelf as $index => $compartmentCount) {
                $shelf = $bookcase->shelves()->create(['number' => $index + 1]);

                for ($number = 1; $number <= $compartmentCount; $number++) {
                    $shelf->compartments()->create(['number' => $number]);
                }
            }
        });
    }
}
