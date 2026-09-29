<?php

namespace Tests\Feature\UsersAndRoles;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 001 - RF-01 (public registration) and RF-04 (email verification not enforced).
 */
class RegistrationRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_account_has_user_role_and_is_logged_in(): void
    {
        Volt::test('auth.register')
            ->set('name', 'Ana Lectora')
            ->set('email', 'ana@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('email', 'ana@example.com')->firstOrFail();

        $this->assertSame(Role::User, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_is_rejected_when_email_already_exists(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        Volt::test('auth.register')
            ->set('name', 'Otra Ana')
            ->set('email', 'ana@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors(['email']);

        $this->assertSame(1, User::where('email', 'ana@example.com')->count());
        $this->assertGuest();
    }

    public function test_user_with_unverified_email_can_use_protected_areas(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
