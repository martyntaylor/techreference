@props(['level', 'size' => 'md'])

@php
    $colors = [
        'High' => ' text-red-800  dark:text-red-900 dark:border-red-900 border',
        'Medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'Low' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    ];
    $normalizedLevel = ucfirst(strtolower((string) $level));
    $color = $colors[$normalizedLevel] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';

    $sizes = [
        'sm' => 'px-2 py-0.5',
        'md' => 'px-3 py-1',
        'lg' => 'px-4 py-1.5',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => " items-center absolute text-xs top-[-17] right-[-17] {$color} {$sizeClass}"]) }}
      aria-label="Security level: {{ $normalizedLevel }} risk">
    {{ $normalizedLevel }} Risk
</span>
