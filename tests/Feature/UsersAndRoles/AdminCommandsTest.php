<?php

namespace Tests\Feature\UsersAndRoles;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 001 - RF-07 (artisan commands for administrators).
 */
class AdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_promotes_a_user_to_admin(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->artisan('admin:grant', ['email' => 'ana@example.com'])->assertSuccessful();

        $this->assertSame(Role::Admin, $user->fresh()->role);
    }

    public function test_revoke_demotes_an_admin_to_user(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'ana@example.com']);

        $this->artisan('admin:revoke', ['email' => 'ana@example.com'])->assertSuccessful();

        $this->assertSame(Role::User, $admin->fresh()->role);
    }

    public function test_commands_fail_when_the_email_does_not_exist(): void
    {
        $this->artisan('admin:grant', ['email' => 'nobody@example.com'])
            ->expectsOutputToContain('No existe ningún usuario')
            ->assertFailed();

        $this->artisan('admin:revoke', ['email' => 'nobody@example.com'])
            ->expectsOutputToContain('No existe ningún usuario')
            ->assertFailed();
    }

    public function test_several_admins_can_coexist(): void
    {
        $firstAdmin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->artisan('admin:grant', ['email' => 'ana@example.com'])->assertSuccessful();

        $this->assertSame(Role::Admin, $firstAdmin->fresh()->role);
        $this->assertSame(Role::Admin, $user->fresh()->role);
    }

    public function test_commands_do_not_affect_the_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['email' => 'super@example.com']);

        $this->artisan('admin:revoke', ['email' => 'super@example.com'])->assertFailed();
        $this->artisan('admin:grant', ['email' => 'super@example.com'])->assertFailed();

        $this->assertSame(Role::SuperAdmin, $superAdmin->fresh()->role);
    }
}
