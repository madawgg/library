<?php

namespace App\Services;

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
}
