@props([
    'title' => 'Related Content',
    'items' => [],
    'type' => 'ports', // ports, errors, extensions
])

@if(count($items) > 0)
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">
        {{ $title }}
    </h3>

    <div class="space-y-3">
        @foreach($items as $item)
            <a
                href="{{ $item['url'] ?? '#' }}"
                class="block p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-400 hover:shadow-md transition group"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            @if($type === 'ports')
                                <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                    Port {{ $item['number'] ?? $item['id'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $item['protocol'] ?? 'TCP' }}
                                </span>
                                @if(isset($item['risk_level']))
                                    <x-security-badge :level="$item['risk_level']" size="sm" />
                                @endif
                            @elseif($type === 'errors')
                                <span class="text-lg font-bold text-red-600 dark:text-red-400">
                                    {{ $item['code'] ?? $item['id'] }}
                                </span>
                            @elseif($type === 'extensions')
                                <span class="text-lg font-bold text-green-600 dark:text-green-400">
                                    .{{ $item['extension'] ?? $item['id'] }}
                                </span>
                            @endif
                        </div>

                        <h4 class="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">
                            {{ $item['name'] ?? $item['title'] }}
                        </h4>

                        @if(isset($item['description']))
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                {{ \Illuminate\Support\Str::limit($item['description'], 120) }}
                            </p>
                        @endif
                    </div>

                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition flex-shrink-0 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endif
