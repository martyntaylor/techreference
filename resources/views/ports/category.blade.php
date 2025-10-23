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

        <!-- Category Statistics -->
        @if($categoryStats && ($categoryStats['total_exposures'] > 0 || $categoryStats['total_cves'] > 0))
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Security Overview</h2>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Exposures</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($categoryStats['total_exposures']) }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total CVEs</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($categoryStats['total_cves']) }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Critical CVEs</div>
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                        {{ number_format($categoryStats['total_critical_cves']) }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Avg CVSS Score</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $categoryStats['avg_cvss_score'] ? number_format($categoryStats['avg_cvss_score'], 1) : 'N/A' }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                @if($categoryStats['most_exposed_port'])
                <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                    <div>
                        <div class="text-gray-600 dark:text-gray-400 mb-1">Most Exposed Port</div>
                        <a href="{{ route('port.show', $categoryStats['most_exposed_port']->port_number) }}"
                           class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                            Port {{ $categoryStats['most_exposed_port']->port_number }}
                            @if($categoryStats['most_exposed_port']->service_name)
                                ({{ $categoryStats['most_exposed_port']->service_name }})
                            @endif
                        </a>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ number_format($categoryStats['most_exposed_port']->shodan_exposed_count) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">exposures</div>
                    </div>
                </div>
                @endif

                @if($categoryStats['most_vulnerable_port'])
                <div class="flex items-center justify-between bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                    <div>
                        <div class="text-gray-600 dark:text-gray-400 mb-1">Most Vulnerable Port</div>
                        <a href="{{ route('port.show', $categoryStats['most_vulnerable_port']->port_number) }}"
                           class="font-semibold text-red-600 dark:text-red-400 hover:underline">
                            Port {{ $categoryStats['most_vulnerable_port']->port_number }}
                            @if($categoryStats['most_vulnerable_port']->service_name)
                                ({{ $categoryStats['most_vulnerable_port']->service_name }})
                            @endif
                        </a>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                            {{ number_format($categoryStats['most_vulnerable_port']->cve_count) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">CVEs</div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

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
                        <option value="exposures" {{ request('sort') === 'exposures' ? 'selected' : '' }}>Most Exposed</option>
                        <option value="cves" {{ request('sort') === 'cves' ? 'selected' : '' }}>Most CVEs</option>
                        <option value="cvss" {{ request('sort') === 'cvss' ? 'selected' : '' }}>Highest CVSS</option>
                        <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>Most Popular</option>
                    </select>
                </div>

                <!-- CVE Severity Filter -->
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">CVE Severity</label>
                    <select name="cve_severity" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">All</option>
                        <option value="critical" {{ request('cve_severity') === 'critical' ? 'selected' : '' }}>Critical</option>
                        <option value="high" {{ request('cve_severity') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('cve_severity') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('cve_severity') === 'low' ? 'selected' : '' }}>Low</option>
                        <option value="none" {{ request('cve_severity') === 'none' ? 'selected' : '' }}>No CVEs</option>
                    </select>
                </div>

                <!-- Exposure Threshold -->
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Min Exposures</label>
                    <select name="min_exposures" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">Any</option>
                        <option value="1000" {{ request('min_exposures') === '1000' ? 'selected' : '' }}>&gt; 1,000</option>
                        <option value="10000" {{ request('min_exposures') === '10000' ? 'selected' : '' }}>&gt; 10,000</option>
                        <option value="100000" {{ request('min_exposures') === '100000' ? 'selected' : '' }}>&gt; 100,000</option>
                        <option value="1000000" {{ request('min_exposures') === '1000000' ? 'selected' : '' }}>&gt; 1,000,000</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Apply Filters
                    </button>
                    @if(request()->hasAny(['protocol', 'risk_level', 'sort', 'cve_severity', 'min_exposures']))
                    <a href="{{ route('ports.category', $category->slug) }}"
                       class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">
                        Clear
                    </a>
                    @endif
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
