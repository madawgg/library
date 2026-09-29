<?php

use App\Services\AuthenticationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Iniciar sesión')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(AuthenticationService $authentication): void
    {
        $this->validate();

        $authentication->login($this->email, $this->password, $this->remember, request()->ip());

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Iniciar sesión')" :description="__('Introduce tu correo electrónico y tu contraseña para entrar.')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <!-- Email Address -->
        <flux:input wire:model="email" label="{{ __('Correo electrónico') }}" type="email" name="email" required autofocus autocomplete="email" placeholder="email@example.com" />

        <!-- Password -->
        <div class="relative">
            <flux:input
                wire:model="password"
                label="{{ __('Contraseña') }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                :placeholder="__('Contraseña')"
            />

            @if (Route::has('password.request'))
                <x-text-link class="absolute right-0 top-0" href="{{ route('password.request') }}">
                    {{ __('¿Has olvidado tu contraseña?') }}
                </x-text-link>
            @endif
        </div>

        <!-- Remember Me -->
        <flux:checkbox wire:model="remember" label="{{ __('Recordarme') }}" />

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Entrar') }}</flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('¿No tienes cuenta?') }}
        <x-text-link href="{{ route('register') }}">{{ __('Crear cuenta') }}</x-text-link>
    </div>
</div>
