@props(['title', 'code', 'language' => 'bash', 'explanation' => null])

<div {{ $attributes->merge(['class' => 'mb-4']) }}>
    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-gray-200 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-700">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $title }}</span>
            <button
                onclick="navigator.clipboard.writeText(this.closest('.bg-gray-50').querySelector('pre').textContent)"
                class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                Copy
            </button>
        </div>
        <pre class="p-4 overflow-x-auto text-sm text-gray-800 dark:text-gray-200"><code class="language-{{ $language }}">{{ $code }}</code></pre>
    </div>

    @if($explanation)
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $explanation }}</p>
    @endif
</div>
