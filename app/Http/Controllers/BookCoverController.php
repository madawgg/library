<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\BookCoverService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookCoverController extends Controller
{
    /**
     * Serve a private cover to people who can see the book (authorized by the route middleware).
     */
    public function __invoke(Book $book, BookCoverService $covers): StreamedResponse
    {
        abort_unless($covers->hasCover($book), 404);

        return $covers->response($book);
    }
}
