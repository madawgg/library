<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;

/**
 * ISBN-10 / ISBN-13 validation and duplicate detection (spec 002, RF-04).
 */
class IsbnService
{
    /**
     * Remove hyphens and spaces; the check character "x" is stored uppercase.
     */
    public function normalize(string $isbn): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $isbn));
    }

    public function isValid(string $isbn): bool
    {
        $isbn = $this->normalize($isbn);

        return match (strlen($isbn)) {
            10 => $this->isValidIsbn10($isbn),
            13 => $this->isValidIsbn13($isbn),
            default => false,
        };
    }

    /**
     * Whether the ISBN is already used by another book of the same library.
     */
    public function existsInLibrary(User $owner, string $isbn, ?Book $except = null): bool
    {
        return $owner->books()
            ->where('isbn', $this->normalize($isbn))
            ->when($except?->exists, fn ($query) => $query->whereKeyNot($except->id))
            ->exists();
    }

    private function isValidIsbn10(string $isbn): bool
    {
        if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 10; $index++) {
            $digit = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];
            $sum += $digit * (10 - $index);
        }

        return $sum % 11 === 0;
    }

    private function isValidIsbn13(string $isbn): bool
    {
        if (! preg_match('/^\d{13}$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 13; $index++) {
            $sum += (int) $isbn[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }
}
