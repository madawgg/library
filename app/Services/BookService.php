<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Books of a library (spec 002).
 */
class BookService
{
    public function __construct(private IsbnService $isbns, private BookCoverService $covers) {}

    /**
     * @param  array<string, mixed>  $data  descriptive fields (see Book::$fillable)
     */
    public function create(User $owner, array $data): Book
    {
        $book = new Book($this->prepare($data));
        $book->user()->associate($owner);
        $book->save();

        return $book;
    }

    /**
     * @param  array<string, mixed>  $data  descriptive fields (see Book::$fillable)
     */
    public function update(Book $book, array $data): Book
    {
        $book->update($this->prepare($data));

        return $book;
    }

    /**
     * Delete the book and its cover file.
     */
    public function delete(Book $book): void
    {
        $this->covers->remove($book);
        $book->delete();
    }

    public function ownerOf(Book $book): User
    {
        return $book->user;
    }

    /**
     * Books of the owner, newest first.
     */
    public function libraryOf(User $owner, int $perPage = 15): LengthAwarePaginator
    {
        return $owner->books()->latest()->latest('id')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        if (array_key_exists('isbn', $data)) {
            $data['isbn'] = filled($data['isbn']) ? $this->isbns->normalize($data['isbn']) : null;
        }

        return array_map(fn ($value) => $value === '' ? null : $value, $data);
    }
}
