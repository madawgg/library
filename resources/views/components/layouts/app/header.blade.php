@inject('userDisplay', 'App\Services\UserDisplayService')
@inject('preferences', 'App\Services\UserPreferenceService')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $preferences->themeFor(auth()->user())->cssClass() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        <a href="#contenido-principal" class="skip-link">{{ __('Saltar al contenido') }}</a>

        <flux:header container class="sticky top-0 z-30 border-b border-zinc-200 bg-surface dark:border-zinc-700">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :aria-label="__('Abrir menú')" data-mobile-menu-open />

            <a href="{{ route('dashboard') }}" class="ms-2 me-6 font-serif text-2xl font-semibold text-ink lg:ms-0" wire:navigate>
                {{ config('app.name') }}
            </a>

            <flux:navbar class="-mb-px max-lg:hidden" :aria-label="__('Navegación principal')">
                @include('partials.main-navigation', ['variant' => 'navbar'])
            </flux:navbar>

            <flux:spacer />

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    class="cursor-pointer"
                    :initials="$userDisplay->initials(auth()->user())"
                    icon-trailing="chevron-down"
                    :aria-label="__('Menú de la cuenta')"
                />

                <flux:menu>
                    <div class="px-2 py-1.5 text-sm leading-tight">
                        <span class="block truncate font-semibold">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-ink-muted">{{ auth()->user()->email }}</span>
                    </div>

                    <flux:menu.separator />

                    @include('partials.account-navigation', ['variant' => 'menu'])
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <!-- Mobile menu -->
        {{-- Keyboard support for the mobile menu (spec 003, CA-07): focus moves into it when it opens,
             and Escape closes it and returns focus to its toggle. --}}
        <flux:sidebar
            stashable
            sticky
            class="border-e border-zinc-200 bg-surface lg:hidden dark:border-zinc-700"
            x-on:flux-sidebar-toggle.window="$nextTick(() => { if (! $el.hasAttribute('data-flux-sidebar-collapsed-mobile')) $el.querySelector('nav a, nav button')?.focus() })"
            x-on:keydown.escape.window="if (! $el.hasAttribute('data-flux-sidebar-collapsed-mobile')) { $dispatch('flux-sidebar-toggle'); document.querySelector('[data-mobile-menu-open]')?.focus() }"
        >
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" :aria-label="__('Cerrar menú')" />

            <span class="ms-1 font-serif text-xl font-semibold text-ink">{{ config('app.name') }}</span>

            <flux:navlist variant="outline" :aria-label="__('Navegación principal')">
                @include('partials.main-navigation', ['variant' => 'navlist'])
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline" :aria-label="__('Cuenta')">
                @include('partials.account-navigation', ['variant' => 'navlist'])
            </flux:navlist>
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
