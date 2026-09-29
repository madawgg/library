<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'user';

    public function mount(): void
    {
        $this->authorize('create', User::class);
    }

    public function save(UserAccountService $accounts): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::in(array_column(Role::assignable(), 'value'))],
        ]);

        $role = Role::from($validated['role']);

        $this->authorize('assignRole', [User::class, $role]);

        $accounts->createAccount($validated['name'], $validated['email'], $validated['password'], $role);

        $this->redirectRoute('admin.users.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-lg space-y-6">
    <flux:heading size="xl" level="1">{{ __('Nuevo usuario') }}</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Nombre')" type="text" required autocomplete="off" />
        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />
        <flux:input wire:model="password" :label="__('Contraseña')" type="password" required autocomplete="new-password" />
        <flux:input wire:model="password_confirmation" :label="__('Confirmar contraseña')" type="password" required autocomplete="new-password" />

        @can('assignRole', [App\Models\User::class, App\Enums\Role::Admin])
            <flux:select wire:model="role" :label="__('Rol')">
                @foreach (App\Enums\Role::assignable() as $assignableRole)
                    <flux:select.option value="{{ $assignableRole->value }}">{{ $assignableRole->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        @endcan

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('Crear usuario') }}</flux:button>
            <flux:button :href="route('admin.users.index')" wire:navigate>{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</section>
