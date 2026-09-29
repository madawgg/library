<?php

namespace Tests\Feature\UsersAndRoles;

use App\Enums\Role;
use App\Exceptions\SuperAdminProtectedException;
use App\Models\User;
use App\Services\UserAccountService;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use RuntimeException;
use Tests\TestCase;

/**
 * Spec 001 - RF-06 (super administrator).
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.super_admin.name' => 'Super Admin',
            'auth.super_admin.email' => 'super@example.com',
            'auth.super_admin.password' => 'secret-password',
        ]);
    }

    public function test_seeder_creates_exactly_one_super_admin_with_configured_credentials(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $superAdmins = User::where('role', Role::SuperAdmin)->get();

        $this->assertCount(1, $superAdmins);
        $this->assertSame('super@example.com', $superAdmins->first()->email);
        $this->assertTrue(Hash::check('secret-password', $superAdmins->first()->password));
    }

    public function test_running_the_seeder_again_does_not_create_a_second_super_admin(): void
    {
        $this->seed(SuperAdminSeeder::class);

        config(['auth.super_admin.email' => 'another@example.com']);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::where('role', Role::SuperAdmin)->count());
        $this->assertDatabaseMissing('users', ['email' => 'another@example.com']);
    }

    public function test_seeder_fails_when_credentials_are_not_configured(): void
    {
        config(['auth.super_admin.email' => null, 'auth.super_admin.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed(SuperAdminSeeder::class);
    }

    public function test_super_admin_cannot_delete_own_account_from_profile(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin);

        Volt::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertForbidden();

        $this->assertNotNull($superAdmin->fresh());
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_profile_page_does_not_offer_account_deletion_to_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get('/settings/profile')
            ->assertOk()
            ->assertDontSeeLivewire('settings.delete-user-form');
    }

    public function test_account_service_refuses_to_delete_the_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        try {
            app(UserAccountService::class)->deleteAccount($superAdmin);
            $this->fail('The super admin should not be deletable.');
        } catch (SuperAdminProtectedException) {
            // Expected.
        }

        $this->assertNotNull($superAdmin->fresh());
    }

    public function test_regular_users_and_admins_can_delete_their_own_account(): void
    {
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user);

            Volt::test('settings.delete-user-form')
                ->set('password', 'password')
                ->call('deleteUser')
                ->assertHasNoErrors()
                ->assertRedirect('/');

            $this->assertNull($user->fresh());
            $this->assertGuest();
        }
    }
}
