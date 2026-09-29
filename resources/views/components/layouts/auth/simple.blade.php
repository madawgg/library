@inject('preferences', 'App\Services\UserPreferenceService')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $preferences->themeFor(auth()->user())->cssClass() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        <a href="#contenido-principal" class="skip-link">{{ __('Saltar al contenido') }}</a>

        <main id="contenido-principal" tabindex="-1" class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-6">
                <p class="text-center font-serif text-3xl font-semibold text-ink">{{ config('app.name') }}</p>

                <div class="flex flex-col gap-6 rounded-lg border border-zinc-200 bg-surface p-6 shadow-sm dark:border-zinc-700">
                    {{ $slot }}
                </div>
            </div>
        </main>

        @fluxScripts
    </body>
</html>
