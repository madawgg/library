@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-english-green']) }}>
        {{ $status }}
    </div>
@endif
