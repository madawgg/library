<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Book listings with search, filters, sorting and pagination (spec 002, RF-06 and RF-07).
 */
class BookCatalogService
{
    /** Value of the "Sin especificar" option in the filters. */
    public const UNSPECIFIED = 'none';

    public const PER_PAGE = 15;

    public const SORTS = ['created_at', 'title', 'author'];

    /**
     * @param  User|null  $owner  library to list, or null for every library (administrators)
     * @param  array{search?: ?string, genre?: ?string, status?: ?string, condition?: ?string, room?: int|string|null, bookcase?: int|string|null, shelf?: int|string|null, compartment?: int|string|null, owner?: ?int, overdue?: bool}  $filters
     */
    public function search(?User $owner, array $filters, string $sort = 'created_at', string $direction = 'desc'): LengthAwarePaginator
    {
        $query = Book::query()->with(['user', 'overdueLoan']);

        if ($owner) {
            $query->whereBelongsTo($owner);
        } elseif (filled($filters['owner'] ?? null)) {
            $query->where('user_id', $filters['owner']);
        }

        $this->applySearch($query, $filters['search'] ?? null);

        if (filled($filters['genre'] ?? null)) {
            $query->whereRaw('LOWER(genre) LIKE ?', ['%'.mb_strtolower($filters['genre']).'%']);
        }

        $this->applyNullableFilter($query, 'reading_status', $filters['status'] ?? null);
        $this->applyNullableFilter($query, 'condition', $filters['condition'] ?? null);
        $this->applyLocation($query, $filters);

        if (! empty($filters['overdue'])) {
            $query->whereHas('overdueLoan');
        }

        $sort = in_array($sort, self::SORTS, true) ? $sort : 'created_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->orderBy('id', $direction)->paginate(self::PER_PAGE);
    }

    /**
     * Title or author contains the text; title matches are listed before author-only matches.
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = '%'.mb_strtolower(trim($search)).'%';

        $query->where(fn (Builder $query) => $query
            ->whereRaw('LOWER(title) LIKE ?', [$term])
            ->orWhereRaw('LOWER(author) LIKE ?', [$term]));

        $query->orderByRaw('CASE WHEN LOWER(title) LIKE ? THEN 0 ELSE 1 END', [$term]);
    }

    private function applyNullableFilter(Builder $query, string $column, ?string $value): void
    {
        if (blank($value)) {
            return;
        }

        $value === self::UNSPECIFIED ? $query->whereNull($column) : $query->where($column, $value);
    }

    /**
     * Each location level narrows the result; "Sin especificar" in any of them means "on the table".
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyLocation(Builder $query, array $filters): void
    {
        $levels = [
            'compartment' => 'id',
            'shelf' => 'shelf_id',
            'bookcase' => 'shelf.bookcase_id',
            'room' => 'shelf.bookcase.room_id',
        ];

        foreach ($levels as $level => $column) {
            $value = $filters[$level] ?? null;

            if (blank($value)) {
                continue;
            }

            if ($value === self::UNSPECIFIED) {
                $query->whereNull('compartment_id');

                return;
            }

            $query->whereHas('compartment', function (Builder $compartments) use ($column, $value) {
                $relations = explode('.', $column);
                $field = array_pop($relations);

                $relations === []
                    ? $compartments->where("compartments.{$field}", $value)
                    : $compartments->whereHas(implode('.', $relations), fn (Builder $parent) => $parent->where($field, $value));
            });
        }
    }
}
