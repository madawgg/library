<?php

namespace App\Services;

use App\Enums\BookView;
use App\Enums\Theme;
use App\Models\User;

/**
 * Interface preferences stored in the user's account (spec 003).
 */
class UserPreferenceService
{
    /**
     * Theme to render. Guests and new accounts always get the light theme.
     */
    public function themeFor(?User $user): Theme
    {
        return $user?->theme ?? Theme::Light;
    }

    public function updateTheme(User $user, Theme $theme): void
    {
        $user->theme = $theme;
        $user->save();
    }

    /**
     * View of the book listing. The shelf view needs a single library, so the global
     * listing falls back to the table.
     */
    public function bookViewFor(User $user, bool $singleLibrary): BookView
    {
        $view = $user->book_view ?? BookView::Grid;

        return $view === BookView::Shelf && ! $singleLibrary ? BookView::Table : $view;
    }

    public function updateBookView(User $user, BookView $view): void
    {
        $user->book_view = $view;
        $user->save();
    }
}
