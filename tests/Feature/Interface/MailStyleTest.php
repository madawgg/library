<?php

namespace Tests\Feature\Interface;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 003 - RF-11 (emails with the application's style, readable, with visible links).
 */
class MailStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_email_uses_the_brand_the_palette_and_offers_the_link_as_text(): void
    {
        $user = User::factory()->create();
        $mail = (new ResetPassword('token-de-prueba'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertStringContainsString('Biblioteca Personal', $html);
        $this->assertStringContainsString('#7b2d26', $html);
        $this->assertStringContainsString('#f6efe0', $html);
        $this->assertStringContainsString('Restablecer contraseña', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'token-de-prueba'), 'Button and plain-text link');
        $this->assertStringContainsString('copia y pega', $html);
        $this->assertStringNotContainsString('Laravel', strip_tags($html));
    }

    public function test_verification_email_is_in_spanish_with_the_same_style(): void
    {
        $html = (string) (new VerifyEmail)->toMail(User::factory()->unverified()->create())->render();

        $this->assertStringContainsString('Verificar correo electrónico', $html);
        $this->assertStringContainsString('#7b2d26', $html);
    }
}
