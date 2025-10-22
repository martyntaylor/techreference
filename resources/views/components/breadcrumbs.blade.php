@props(['items' => []])

@php
// Build full breadcrumb path starting with home
$allItems = array_merge(
    [['name' => 'Home', 'url' => route('home')]],
    $items
);
@endphp

@if(count($allItems) > 1)
<nav aria-label="Breadcrumb" class="mb-6">
    <ol class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
        @foreach($allItems as $item)
            <li class="flex items-center">
                @if(!$loop->first)
                    <!-- Separator -->
                    <svg class="w-4 h-4 flex-shrink-0 mx-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                @endif

                @if($loop->last)
                    <!-- Last item (current page) - no link -->
                    <span class="font-medium text-gray-900 dark:text-white" aria-current="page">
                        {{ $item['name'] }}
                    </span>
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
                    <a href="{{ $item['url'] }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                        {{ $item['name'] }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

<!-- Schema.org BreadcrumbList -->
<x-schema-json
    type="BreadcrumbList"
    :data="['items' => $allItems]"
/>
@endif
