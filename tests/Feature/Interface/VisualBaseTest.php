<?php

namespace Tests\Feature\Interface;

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 003 - visual base: themes, navigation, titles, language and accessibility helpers.
 */
class VisualBaseTest extends TestCase
{
    use RefreshDatabase;

    private const DARK_HTML = '/<html[^>]*class="[^"]*\bdark\b/';

    // Themes (RF-03: CA-01, CA-02, CA-04)

    public function test_new_user_sees_the_light_theme_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Theme::Light, $user->fresh()->theme);

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(self::DARK_HTML, $html);
    }

    public function test_guest_pages_use_the_light_theme(): void
    {
        $this->assertDoesNotMatchRegularExpression(self::DARK_HTML, $this->get('/login')->getContent());
    }

    public function test_theme_choice_is_saved_in_the_account_and_applied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('settings.appearance')
            ->set('theme', 'dark')
            ->assertHasNoErrors();

        $this->assertSame(Theme::Dark, $user->fresh()->theme);

        $this->assertMatchesRegularExpression(self::DARK_HTML, $this->get('/dashboard')->getContent());
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('settings.appearance')
            ->set('theme', 'system')
            ->assertHasErrors(['theme']);

        $this->assertSame(Theme::Light, $user->fresh()->theme);
    }

    public function test_appearance_settings_only_offer_light_and_dark(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/appearance')
            ->assertOk()
            ->assertSee('Claro')
            ->assertSee('Oscuro')
            ->assertDontSee('Sistema')
            ->assertDontSee('System');
    }

    // Navigation and brand (RF-04: CA-05, CA-06, CA-08)

    public function test_user_navigation_shows_brand_and_account_menu_but_not_admin_links(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertSee('Biblioteca Personal')
            ->assertSee('Inicio')
            ->assertSee('Perfil')
            ->assertSee('Contraseña')
            ->assertSee('Apariencia')
            ->assertSee('Cerrar sesión')
            ->assertDontSee(route('admin.users.index'))
            ->assertDontSee('Repository')
            ->assertDontSee('Documentation');
    }

    public function test_admin_navigation_includes_users(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertSee(route('admin.users.index'))
            ->assertSee('Usuarios');
    }

    public function test_page_titles_follow_the_page_and_brand_format(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/dashboard')->assertSee('<title>Inicio · Biblioteca Personal</title>', false);
        $this->get('/admin/users')->assertSee('<title>Usuarios · Biblioteca Personal</title>', false);
        $this->get('/settings/profile')->assertSee('<title>Perfil · Biblioteca Personal</title>', false);
    }

    public function test_guest_page_titles_follow_the_same_format(): void
    {
        $this->get('/login')->assertSee('<title>Iniciar sesión · Biblioteca Personal</title>', false);
        $this->get('/register')->assertSee('<title>Crear cuenta · Biblioteca Personal</title>', false);
    }

    public function test_home_redirects_to_login_or_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
    }

    // Language (RF-09: CA-17, CA-19)

    public function test_documents_are_in_spanish(): void
    {
        $this->get('/login')->assertSee('<html lang="es"', false);

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('<html lang="es"', false);
    }

    public function test_starter_kit_screens_are_translated(): void
    {
        $this->get('/login')
            ->assertSee('Correo electrónico')
            ->assertSee('¿Has olvidado tu contraseña?')
            ->assertDontSee('Log in to your account')
            ->assertDontSee('Remember me');

        $this->get('/register')
            ->assertSee('Crear cuenta')
            ->assertDontSee('Create an account')
            ->assertDontSee('Already have an account?');

        $this->get('/forgot-password')->assertDontSee('Forgot password');
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        $component = Volt::test('auth.register')
            ->set('name', '')
            ->call('register')
            ->assertHasErrors(['name']);

        $this->assertStringContainsString('obligatorio', $component->errors()->first('name'));
    }

    public function test_password_reset_email_is_in_spanish(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Volt::test('auth.forgot-password')->set('email', $user->email)->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            return $notification->toMail($user)->subject === 'Restablecer contraseña';
        });
    }

    public function test_error_pages_are_in_spanish(): void
    {
        $this->get('/pagina-que-no-existe')->assertNotFound()->assertSee('Página no encontrada');
    }

    // Fonts and accessibility helpers (RF-01, RF-10: CA-18, CA-20)

    public function test_fonts_are_not_requested_from_external_domains(): void
    {
        foreach (['/login', '/register'] as $page) {
            $this->get($page)
                ->assertDontSee('fonts.bunny.net')
                ->assertDontSee('fonts.googleapis.com');
        }

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertDontSee('fonts.bunny.net')
            ->assertDontSee('fonts.googleapis.com');
    }

    public function test_pages_start_with_a_skip_to_content_link(): void
    {
        $this->get('/login')
            ->assertSeeInOrder(['<body', 'Saltar al contenido', 'id="contenido-principal"'], false);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertSeeInOrder(['<body', 'Saltar al contenido', 'id="contenido-principal"'], false);
    }
}
