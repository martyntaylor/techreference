<x-layouts.app :pageTitle="'Ports ' . $start . '-' . $end">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">
                Ports {{ $start }} - {{ $end }}
            </h1>
            <p class="text-lg text-gray-600 dark:text-gray-400">
                Port range information and statistics
            </p>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Ports</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['total_in_range'] }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <div class="text-sm text-gray-500 dark:text-gray-400">High Risk</div>
                <div class="text-3xl font-bold text-red-600">{{ $stats['high_risk'] }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <div class="text-sm text-gray-500 dark:text-gray-400">Medium Risk</div>
                <div class="text-3xl font-bold text-yellow-600">{{ $stats['medium_risk'] }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <div class="text-sm text-gray-500 dark:text-gray-400">Low Risk</div>
                <div class="text-3xl font-bold text-green-600">{{ $stats['low_risk'] }}</div>
            </div>
        </div>

        <!-- Ports Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Port
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Protocol
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Service
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Risk
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($ports as $port)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('port.show', $port->port_number) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                                    {{ $port->port_number }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $port->protocol }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                {{ $port->service_name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-security-badge :level="$port->risk_level" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                No ports found in this range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($ports->hasPages())
            <div class="mt-6">
                {{ $ports->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
