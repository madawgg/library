@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2 text-center">
    <h1 class="font-serif text-3xl font-semibold text-ink">{{ $title }}</h1>
    <p class="text-center text-sm text-ink-muted">{{ $description }}</p>
</div>
