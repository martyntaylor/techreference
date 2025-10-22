<x-layouts.app
    :pageTitle="'Search Results for \"' . $query . '\"'"
    :metaDescription="'Search results for ' . $query . ' on TechReference - ports, protocols, and technical references'">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Search Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">
                Search Results
            </h1>
            <p class="text-lg text-gray-600 dark:text-gray-400">
                Showing results for "<span class="font-semibold">{{ $query }}</span>"
            </p>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <form method="GET" action="{{ route('search.index') }}" class="space-y-4" x-data="{ showFilters: false }">
                <input type="hidden" name="q" value="{{ $query }}">

                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filters</h3>
                    <button
                        type="button"
                        @click="showFilters = !showFilters"
                        class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 md:hidden"
                    >
                        <span x-show="!showFilters">Show Filters</span>
                        <span x-show="showFilters">Hide Filters</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" x-show="showFilters" x-cloak>
                    <!-- Protocol Filter -->
                    <div>
                        <label for="protocol" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Protocol
                        </label>
                        <select
                            name="protocol"
                            id="protocol"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Protocols</option>
                            <option value="TCP" {{ $currentFilters['protocol'] === 'TCP' ? 'selected' : '' }}>TCP</option>
                            <option value="UDP" {{ $currentFilters['protocol'] === 'UDP' ? 'selected' : '' }}>UDP</option>
                            <option value="SCTP" {{ $currentFilters['protocol'] === 'SCTP' ? 'selected' : '' }}>SCTP</option>
                        </select>
                    </div>

                    <!-- Risk Level Filter -->
                    <div>
                        <label for="risk_level" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Risk Level
                        </label>
                        <select
                            name="risk_level"
                            id="risk_level"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Risk Levels</option>
                            <option value="High" {{ $currentFilters['risk_level'] === 'High' ? 'selected' : '' }}>High</option>
                            <option value="Medium" {{ $currentFilters['risk_level'] === 'Medium' ? 'selected' : '' }}>Medium</option>
                            <option value="Low" {{ $currentFilters['risk_level'] === 'Low' ? 'selected' : '' }}>Low</option>
                        </select>
                    </div>

                    <!-- Type Filter -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Type
                        </label>
                        <select
                            name="type"
                            id="type"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="all" {{ $currentFilters['type'] === 'all' ? 'selected' : '' }}>All Types</option>
                            <option value="port" {{ $currentFilters['type'] === 'port' ? 'selected' : '' }}>Ports</option>
                            <option value="error" {{ $currentFilters['type'] === 'error' ? 'selected' : '' }}>Error Codes</option>
                            <option value="extension" {{ $currentFilters['type'] === 'extension' ? 'selected' : '' }}>File Extensions</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition"
                    >
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div class="space-y-8">
            <!-- Ports Results -->
            @if($results['ports']->isNotEmpty())
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            Ports
                            <span class="text-lg font-normal text-gray-500 dark:text-gray-400">
                                ({{ $results['ports']->total() }} results)
                            </span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($results['ports'] as $port)
                            <x-port-card :port="$port" />
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    @if($results['ports']->hasPages())
                        <div class="mt-6">
                            {{ $results['ports']->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            @endif

            <!-- Error Codes Results (Placeholder) -->
            @if($results['errors']->isNotEmpty())
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            Error Codes
                            <span class="text-lg font-normal text-gray-500 dark:text-gray-400">
                                ({{ $results['errors']->count() }} results)
                            </span>
                        </h2>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <p class="text-gray-600 dark:text-gray-400">Error codes search coming soon...</p>
                    </div>
                </div>
            @endif

            <!-- File Extensions Results (Placeholder) -->
            @if($results['extensions']->isNotEmpty())
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            File Extensions
                            <span class="text-lg font-normal text-gray-500 dark:text-gray-400">
                                ({{ $results['extensions']->count() }} results)
                            </span>
                        </h2>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <p class="text-gray-600 dark:text-gray-400">File extensions search coming soon...</p>
                    </div>
                </div>
            @endif

            <!-- No Results -->
            @if($results['ports']->isEmpty() && $results['errors']->isEmpty() && $results['extensions']->isEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No results found</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        We couldn't find any results for "{{ $query }}". Try adjusting your search or filters.
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('home') }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                            Return to homepage
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Search Tips -->
        <div class="mt-8 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-3">Search Tips</h3>
            <ul class="space-y-2 text-sm text-blue-800 dark:text-blue-200">
                <li>• Search by port number (e.g., "80", "443")</li>
                <li>• Search by service name (e.g., "HTTP", "SSH")</li>
                <li>• Search by protocol (e.g., "TCP", "UDP")</li>
                <li>• Use filters to narrow down results by protocol, risk level, or type</li>
            </ul>
        </div>
    </div>
</x-layouts.app>
