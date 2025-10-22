@props([
    'tabs' => [],
    'defaultTab' => null,
    'storageKey' => null, // localStorage key for persistence
])

@php
$defaultTab = $defaultTab ?? (count($tabs) > 0 ? array_key_first($tabs) : null);
@endphp

<div
    x-data="{
        activeTab: '{{ $defaultTab }}',
        storageKey: '{{ $storageKey }}',
        init() {
            if (this.storageKey) {
                const saved = localStorage.getItem(this.storageKey);
                if (saved && {{ json_encode(array_keys($tabs)) }}.includes(saved)) {
                    this.activeTab = saved;
                }
            }
        },
        switchTab(tab) {
            this.activeTab = tab;
            if (this.storageKey) {
                localStorage.setItem(this.storageKey, tab);
            }
        }
    }"
    {{ $attributes->merge(['class' => 'w-full']) }}
>
    <!-- Tab Headers -->
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex space-x-4 overflow-x-auto" role="tablist">
            @foreach($tabs as $key => $tab)
                <button
                    type="button"
                    @click="switchTab('{{ $key }}')"
                    :class="activeTab === '{{ $key }}' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition"
                    role="tab"
                    :aria-selected="activeTab === '{{ $key }}'"
                >
                    @if(isset($tab['icon']))
                        <span class="mr-2">{!! $tab['icon'] !!}</span>
                    @endif
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Tab Panels -->
    <div class="mt-4">
        @foreach($tabs as $key => $tab)
            <div
                x-show="activeTab === '{{ $key }}'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform translate-y-1"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                role="tabpanel"
                :aria-hidden="activeTab !== '{{ $key }}'"
            >
                {!! $tab['content'] !!}
            </div>
        @endforeach
    </div>
</div>
