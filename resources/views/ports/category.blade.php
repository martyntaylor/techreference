<x-layouts.app :pageTitle="$category->name . ' Ports'">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumbs -->
        <x-breadcrumbs :items="[
            ['name' => 'Ports', 'url' => route('ports.index')],
            ['name' => $category->name]
        ]" />

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ $category->name }} Ports</h1>
            @if($category->description)
                <p class="text-lg text-gray-600 dark:text-gray-400">{{ $category->description }}</p>
            @endif
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
            <form action="{{ route('ports.category', $category->slug) }}" method="GET" class="flex flex-wrap gap-4">
                <!-- Protocol Filter -->
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Protocol</label>
                    <select name="protocol" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">All</option>
                        @foreach($filterCounts['protocols'] ?? [] as $protocol => $count)
                            <option value="{{ $protocol }}" {{ request('protocol') === $protocol ? 'selected' : '' }}>
                                {{ $protocol }} ({{ $count }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Risk Level Filter -->
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Risk Level</label>
                    <select name="risk_level" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">All</option>
                        @foreach($filterCounts['risk_levels'] ?? [] as $level => $count)
                            <option value="{{ $level }}" {{ request('risk_level') === $level ? 'selected' : '' }}>
                                {{ $level }} ({{ $count }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Sort -->
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Sort By</label>
                    <select name="sort" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="port_number" {{ request('sort') === 'port_number' ? 'selected' : '' }}>Port Number</option>
                        <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name</option>
                        <option value="risk" {{ request('sort') === 'risk' ? 'selected' : '' }}>Risk Level</option>
                        <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>Most Popular</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Ports Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @forelse($ports as $port)
                <x-port-card :port="$port" />
            @empty
                <div class="col-span-3 text-center py-12 text-gray-500 dark:text-gray-400">
                    No ports found matching your filters.
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($ports->hasPages())
            <div class="mt-6">
                {{ $ports->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
