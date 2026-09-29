<?php

use App\Services\AuthenticationService;
use App\Services\EmailVerificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Verificar correo electrónico')] class extends Component {
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(EmailVerificationService $emailVerification): void
    {
        if (! $emailVerification->sendVerificationLink(Auth::user())) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(AuthenticationService $authentication): void
    {
        $authentication->logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="mt-4 flex flex-col gap-6">
    <div class="text-center text-sm text-ink-muted">
        {{ __('Verifica tu correo electrónico con el enlace que te acabamos de enviar.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="font-medium text-center text-sm text-english-green">
            {{ __('Te hemos enviado un nuevo enlace de verificación al correo electrónico con el que te registraste.') }}
        </div>
    @endif

    <div class="flex flex-col items-center justify-between space-y-3">
        <flux:button wire:click="sendVerification" variant="primary" class="w-full">
            {{ __('Reenviar correo de verificación') }}
        </flux:button>

        <button
            wire:click="logout"
            type="submit"
            class="rounded-md text-sm text-ink-muted underline hover:text-ink focus:outline-hidden focus:ring-2 focus:ring-english-green focus:ring-offset-2"
        >
            {{ __('Cerrar sesión') }}
        </button>
    </div>
</div>
