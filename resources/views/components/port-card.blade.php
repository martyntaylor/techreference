@props(['port'])

<a href="{{ route('port.show', $port->port_number) }}" {{ $attributes->merge(['class' => 'block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition']) }}>
    <div class="flex items-start justify-between mb-2">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Port {{ $port->port_number }}</h3>
            @if($port->service_name)
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $port->service_name }}</p>
            @endif
        </div>
        <x-security-badge :level="$port->risk_level" />
    </div>

    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
        <span>{{ $port->protocol }}</span>
        @if($port->software_count ?? false)
            <span>{{ $port->software_count }} apps</span>
        @endif
        @if($port->security && $port->security->shodan_exposed_count > 0)
            <span>{{ number_format($port->security->shodan_exposed_count) }} exposures</span>
        @endif
    </div>
</a>
