@props(['title', 'items', 'icon' => null])

@if($items && count($items) > 0)
<div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
        @if($icon)
            <span class="text-gray-500 dark:text-gray-400">{{ $icon }}</span>
        @endif
        {{ $title }}
    </h3>
    <ul class="space-y-2">
        @foreach(array_slice($items, 0, 10) as $item)
            <li class="flex items-center justify-between text-sm">
                <span class="text-gray-700 dark:text-gray-300 truncate flex-1">
                    {{ is_array($item) ? ($item['name'] ?? $item['value'] ?? 'Unknown') : $item }}
                </span>
                @if(is_array($item) && isset($item['count']))
                    <span class="ml-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ number_format($item['count']) }}
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endif
