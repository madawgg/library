<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\UserAccountService;
use App\Services\UserRoleService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public User $user;

    public string $name = '';
    public string $email = '';
    public string $role = '';

    public function mount(User $user): void
    {
        $this->authorize('update', $user);

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
    }

    public function save(UserAccountService $accounts, UserRoleService $roles): void
    {
        $this->authorize('update', $this->user);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user->id)],
            'role' => ['required', Rule::in(array_column(Role::assignable(), 'value'))],
        ]);

        $role = Role::from($validated['role']);

        if ($role !== $this->user->role) {
            $this->authorize('assignRole', [User::class, $role]);
        }

        $accounts->updateProfile($this->user, $validated['name'], $validated['email']);

        if ($role !== $this->user->role) {
            $roles->changeRole($this->user, $role);
        }

        $this->redirectRoute('admin.users.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-lg space-y-6">
    <flux:heading size="xl" level="1">{{ __('Editar usuario') }}</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Nombre')" type="text" required autocomplete="off" />
        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />

        @can('assignRole', [App\Models\User::class, App\Enums\Role::Admin])
            <flux:select wire:model="role" :label="__('Rol')">
                @foreach (App\Enums\Role::assignable() as $assignableRole)
                    <flux:select.option value="{{ $assignableRole->value }}">{{ $assignableRole->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        @endcan

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('Guardar') }}</flux:button>
            <flux:button :href="route('admin.users.index')" wire:navigate>{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</section>
