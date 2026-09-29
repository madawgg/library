<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private storage of book covers (spec 002, RF-03).
 *
 * A processed cover is first kept as a pending file while the user reviews it in the form,
 * and only becomes the book's cover when the book is saved.
 */
class BookCoverService
{
    public function __construct(private CoverImageService $images) {}

    /**
     * Process an uploaded picture and keep the result as a pending cover.
     *
     * @return array{path: string, preview: string, compressed: bool}
     */
    public function preparePending(string $uploadedPath): array
    {
        $processed = $this->images->process($uploadedPath);
        $path = $this->directory().'/pending/'.Str::uuid().'.webp';

        $this->disk()->put($path, $processed['webp']);

        return [
            'path' => $path,
            'preview' => 'data:image/webp;base64,'.base64_encode($processed['webp']),
            'compressed' => $processed['compressed'],
        ];
    }

    public function discardPending(?string $pendingPath): void
    {
        if ($pendingPath && str_starts_with($pendingPath, $this->directory().'/pending/')) {
            $this->disk()->delete($pendingPath);
        }
    }

    /**
     * Turn a pending cover into the book's cover, deleting the previous one.
     */
    public function attachPending(Book $book, string $pendingPath): void
    {
        $path = $this->directory().'/'.Str::uuid().'.webp';
        $this->disk()->move($pendingPath, $path);

        $this->remove($book);

        $book->forceFill(['cover_path' => $path])->save();
    }

    public function remove(Book $book): void
    {
        if ($book->cover_path) {
            $this->disk()->delete($book->cover_path);
            $book->forceFill(['cover_path' => null])->save();
        }
    }

    /**
     * Delete the cover files of every book of the user (used before deleting the account).
     */
    public function removeAllOf(User $owner): void
    {
        $paths = $owner->books()->whereNotNull('cover_path')->pluck('cover_path')->all();

        if ($paths !== []) {
            $this->disk()->delete($paths);
        }
    }

    public function hasCover(Book $book): bool
    {
        return $book->cover_path !== null && $this->disk()->exists($book->cover_path);
    }

    public function response(Book $book): StreamedResponse
    {
        return $this->disk()->response($book->cover_path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('books.cover_disk'));
    }

    private function directory(): string
    {
        return config('books.cover_directory');
    }
}
