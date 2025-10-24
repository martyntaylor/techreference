@props(['items' => []])

@php
// Build full breadcrumb path starting with home
$allItems = array_merge(
    [['name' => 'Home', 'url' => route('home')]],
    $items
);
@endphp

@if(count($allItems) > 1)

    <nav aria-label="Breadcrumb" class="flex items-center gap-x-2 text-sm/6 mb-8 px-8 md:px-4">
        @foreach($allItems as $item)
                @if(!$loop->first)
                    <span class="text-gray-950/25 dark:text-white/25">/</span>
                @endif

                @if($loop->last)
                    <!-- Last item (current page) - no link -->
                    <span class="min-w-0 truncate text-gray-950 last:text-gray-600 dark:last:text-gray-400"  aria-current="page">{{ $item['name'] }}</span>
                @elseif($loop->first)
                    <!-- Home icon -->
                    <a href="{{ $item['url'] }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition" aria-label="Home">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                        </svg>
                        <span class="sr-only">{{ $item['name'] }}</span>
                    </a>
                @else
                    <!-- Linked items -->
                    <a href="{{ $item['url'] }}" class="min-w-0 shrink-0 text-gray-950 dark:text-white">{{ $item['name'] }}</a>
                @endif
        @endforeach
    </nav>

<!-- Schema.org BreadcrumbList -->
<x-schema-json
    type="BreadcrumbList"
    :data="['items' => $allItems]"
/>
@endif
