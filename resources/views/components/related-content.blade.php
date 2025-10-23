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
                                @if(isset($item['relation_type']))
                                    @php
                                        $relationLabel = match($item['relation_type']) {
                                            'alternative' => 'Alternative',
                                            'secure_version' => 'Secure Version',
                                            'deprecated_by' => 'Deprecated By',
                                            'part_of_suite' => 'Part of Suite',
                                            'conflicts_with' => 'Conflicts With',
                                            'complementary' => 'Complementary',
                                            'associated_with' => 'Associated With',
                                            default => ucfirst(str_replace('_', ' ', $item['relation_type']))
                                        };
                                        $relationColor = match($item['relation_type']) {
                                            'alternative' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                            'secure_version' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                            'deprecated_by' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
                                            'part_of_suite' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                            'conflicts_with' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                            'complementary' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
                                            'associated_with' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $relationColor }}">
                                        {{ $relationLabel }}
                                    </span>
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

                        @if(isset($item['relation_description']))
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 italic">
                                {{ $item['relation_description'] }}
                            </p>
                        @elseif(isset($item['description']))
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
