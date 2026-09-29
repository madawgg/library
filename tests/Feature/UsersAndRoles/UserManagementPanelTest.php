<?php

namespace Tests\Feature\UsersAndRoles;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 001 - RF-08 (user management panel) and the super admin protections of RF-06.
 */
class UserManagementPanelTest extends TestCase
{
    use RefreshDatabase;

    // Access (CA-22, CA-23)

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_regular_user_gets_403_on_the_panel(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
    }

    public function test_admin_and_super_admin_see_the_user_list(): void
    {
        $listed = User::factory()->create(['name' => 'Ana Lectora']);

        foreach ([User::factory()->admin()->create(), User::factory()->superAdmin()->create()] as $manager) {
            $this->actingAs($manager)
                ->get('/admin/users')
                ->assertOk()
                ->assertSee('Ana Lectora')
                ->assertSee($listed->email);
        }
    }

    // Creation (CA-24, CA-24b, CA-24c, CA-17)

    public function test_admin_creates_an_account_with_user_role_and_the_given_password(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('admin.users.create')
            ->set('name', 'Nuevo Lector')
            ->set('email', 'nuevo@example.com')
            ->set('password', 'una-clave-segura')
            ->set('password_confirmation', 'una-clave-segura')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'nuevo@example.com')->firstOrFail();

        $this->assertSame(Role::User, $created->role);
        $this->assertTrue(Hash::check('una-clave-segura', $created->password));
    }

    public function test_creation_is_rejected_when_password_confirmation_does_not_match(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('admin.users.create')
            ->set('name', 'Nuevo Lector')
            ->set('email', 'nuevo@example.com')
            ->set('password', 'una-clave-segura')
            ->set('password_confirmation', 'otra-clave')
            ->call('save')
            ->assertHasErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_admin_cannot_create_an_administrator(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('admin.users.create')
            ->set('name', 'Nuevo Admin')
            ->set('email', 'nuevo@example.com')
            ->set('password', 'una-clave-segura')
            ->set('password_confirmation', 'una-clave-segura')
            ->set('role', Role::Admin->value)
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_super_admin_can_create_an_administrator(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Volt::test('admin.users.create')
            ->set('name', 'Nuevo Admin')
            ->set('email', 'nuevo@example.com')
            ->set('password', 'una-clave-segura')
            ->set('password_confirmation', 'una-clave-segura')
            ->set('role', Role::Admin->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(Role::Admin, User::where('email', 'nuevo@example.com')->firstOrFail()->role);
    }

    public function test_nobody_can_create_a_second_super_admin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Volt::test('admin.users.create')
            ->set('name', 'Otro Super')
            ->set('email', 'nuevo@example.com')
            ->set('password', 'una-clave-segura')
            ->set('password_confirmation', 'una-clave-segura')
            ->set('role', Role::SuperAdmin->value)
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertSame(1, User::where('role', Role::SuperAdmin)->count());
    }

    // Admin managing accounts (CA-25, CA-26, CA-15, CA-29)

    public function test_admin_edits_and_deletes_accounts_with_user_role(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();

        Volt::test('admin.users.edit', ['user' => $user])
            ->set('name', 'Nombre Nuevo')
            ->set('email', 'nuevo@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nombre Nuevo', $user->fresh()->name);
        $this->assertSame('nuevo@example.com', $user->fresh()->email);

        Volt::test('admin.users.index')->call('delete', $user->id)->assertHasNoErrors();

        $this->assertNull($user->fresh());
    }

    public function test_admin_cannot_edit_or_delete_another_admin_or_the_super_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach ([User::factory()->admin()->create(), User::factory()->superAdmin()->create()] as $protected) {
            $this->get("/admin/users/{$protected->id}/edit")->assertForbidden();

            Volt::test('admin.users.index')->call('delete', $protected->id)->assertForbidden();

            $this->assertNotNull($protected->fresh());
        }
    }

    public function test_admin_cannot_change_roles(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();

        Volt::test('admin.users.edit', ['user' => $user])
            ->set('role', Role::Admin->value)
            ->call('save')
            ->assertForbidden();

        $this->assertSame(Role::User, $user->fresh()->role);
    }

    // Super admin managing accounts (CA-27, CA-28, CA-17)

    public function test_super_admin_edits_and_deletes_users_and_admins(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $account) {
            Volt::test('admin.users.edit', ['user' => $account])
                ->set('name', 'Editado')
                ->call('save')
                ->assertHasNoErrors();

            $this->assertSame('Editado', $account->fresh()->name);

            Volt::test('admin.users.index')->call('delete', $account->id)->assertHasNoErrors();

            $this->assertNull($account->fresh());
        }
    }

    public function test_super_admin_changes_roles_between_user_and_admin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $user = User::factory()->create();

        Volt::test('admin.users.edit', ['user' => $user])
            ->set('role', Role::Admin->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(Role::Admin, $user->fresh()->role);

        Volt::test('admin.users.edit', ['user' => $user->fresh()])
            ->set('role', Role::User->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(Role::User, $user->fresh()->role);
    }

    public function test_super_admin_role_cannot_be_assigned_from_the_panel(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $user = User::factory()->create();

        Volt::test('admin.users.edit', ['user' => $user])
            ->set('role', Role::SuperAdmin->value)
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertSame(Role::User, $user->fresh()->role);
    }

    public function test_super_admin_account_is_not_editable_from_the_panel(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $this->get("/admin/users/{$superAdmin->id}/edit")->assertForbidden();
    }
}
