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

    @if($port->description)
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
            {{ $port->description }}
        </p>
    @endif

    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 mb-2">
        @if(isset($port->protocols) && is_array($port->protocols))
            <span>{{ implode(', ', $port->protocols) }}</span>
        @else
            <span>{{ $port->protocol }}</span>
        @endif
        @if($port->software_count ?? false)
            <span>{{ $port->software_count }} apps</span>
        @endif
    </div>

    @if($port->security)
    <div class="flex items-center gap-2 flex-wrap">
        @if($port->security->shodan_exposed_count > 0)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                {{ number_format($port->security->shodan_exposed_count) }} exposures
            </span>
        @endif
        @if($port->security->cve_count > 0)
            @php
                $cveClass = match(true) {
                    $port->security->cve_critical_count > 0 => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                    $port->security->cve_high_count > 0 => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
                    $port->security->cve_medium_count > 0 => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                };
            @endphp
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cveClass }}">
                {{ $port->security->cve_count }} CVE{{ $port->security->cve_count !== 1 ? 's' : '' }}
            </span>
        @endif
        @if($port->security->cve_avg_score)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                CVSS {{ number_format($port->security->cve_avg_score, 1) }}
            </span>
        @endif
    </div>
    @endif
</a>
