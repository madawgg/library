<?php

use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\UserAccountService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Perfil')] class extends Component {
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(UserAccountService $accounts): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],
        ]);

        $accounts->updateProfile($user, $validated['name'], $validated['email']);

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(EmailVerificationService $emailVerification): void
    {
        if (! $emailVerification->sendVerificationLink(Auth::user())) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Perfil')" :subheading="__('Actualiza tu nombre y tu correo electrónico.')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" label="{{ __('Nombre') }}" type="text" name="name" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" label="{{ __('Correo electrónico') }}" type="email" name="email" required autocomplete="email" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                    <div>
                        <p class="mt-2 text-sm text-ink">
                            {{ __('Tu correo electrónico no está verificado.') }}

                            <button
                                wire:click.prevent="resendVerificationNotification"
                                class="rounded-md text-sm text-ink-muted underline hover:text-ink focus:outline-hidden focus:ring-2 focus:ring-english-green focus:ring-offset-2"
                            >
                                {{ __('Pulsa aquí para reenviar el correo de verificación.') }}
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-english-green">
                                {{ __('Te hemos enviado un nuevo enlace de verificación.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Guardar') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Guardado.') }}
                </x-action-message>
            </div>
        </form>

        @can('deleteOwnAccount', auth()->user())
            <livewire:settings.delete-user-form />
        @endcan
    </x-settings.layout>
</section>
