@php
// $port is the primary port for metadata, $ports is the collection of all protocols
$protocolsList = $ports->pluck('protocol')->join(', ');
$pageTitle = "Port {$port->port_number} - Vulnerabilities";
$metaDescription = "Complete list of known CVE vulnerabilities for port {$port->port_number} ({$protocolsList}). Security analysis, CVSS scores, and mitigation strategies.";
$breadcrumbs = [
    ['name' => 'Ports', 'url' => route('ports.index')]
];
if($port->categories->isNotEmpty()) {
    $breadcrumbs[] = [
        'name' => $port->categories->first()->name,
        'url' => route('ports.category', $port->categories->first()->slug)
    ];
}
$breadcrumbs[] = ['name' => "Port {$port->port_number}", 'url' => route('port.show', $port->port_number)];
$breadcrumbs[] = ['name' => 'Vulnerabilities'];
@endphp

<x-meta-tags
    :title="$pageTitle"
    :description="$metaDescription"
    :keywords="collect(['port ' . $port->port_number, 'vulnerabilities', 'CVE', 'security', $port->service_name])->filter()->join(', ')"
    type="article"
/>

<x-layouts.app :pageTitle="$pageTitle">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumbs -->
        <x-breadcrumbs :items="$breadcrumbs" />

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">
                Port {{ $port->port_number }} - Known Vulnerabilities
            </h1>
            <p class="text-lg text-gray-600 dark:text-gray-400">
                @if($port->service_name)
                    {{ $port->service_name }} -
                @endif
                {{ $port->cves->count() }} CVEs from the National Vulnerability Database
            </p>
        </div>

        <!-- Security Overview -->
        @if($port->security)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Security Overview</h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total CVEs</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($port->security->cve_count) }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Avg CVSS Score</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $port->security->cve_avg_score ? number_format($port->security->cve_avg_score, 1) : 'N/A' }}
                    </div>
                    @if($port->security->cve_avg_score)
                        @php
                            $scoreLabel = match(true) {
                                $port->security->cve_avg_score >= 9.0 => 'Critical',
                                $port->security->cve_avg_score >= 7.0 => 'High',
                                $port->security->cve_avg_score >= 4.0 => 'Medium',
                                default => 'Low'
                            };
                        @endphp
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ $scoreLabel }} severity
                        </div>
                    @endif
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Latest CVE</div>
                    <div class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $port->security->latest_cve ?? 'N/A' }}
                    </div>
                    @if($port->cves->first())
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ $port->cves->first()->published_date->format('M d, Y') }}
                        </div>
                    @endif
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Internet Exposures</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($port->security->shodan_exposed_count) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Shodan detected
                    </div>
                </div>
            </div>

            <!-- CVE Severity Breakdown -->
            @if($port->security->cve_count > 0)
            <div>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Severity Breakdown</h3>
                <x-cve-severity-badges :security="$port->security" />
            </div>
            @endif
        </div>
        @endif

        <!-- Introduction -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 p-6 mb-8">
            <h2 class="text-lg font-semibold text-blue-900 dark:text-blue-300 mb-2">About These Vulnerabilities</h2>
            <p class="text-sm text-blue-800 dark:text-blue-200">
                The vulnerabilities listed below are CVEs (Common Vulnerabilities and Exposures) from the National Vulnerability Database (NVD)
                that explicitly mention port {{ $port->port_number }} in their descriptions. These are port-specific security issues rather than
                general product vulnerabilities. Each CVE includes a CVSS score (0-10 scale), severity rating, and detailed description.
            </p>
            <p class="text-sm text-blue-800 dark:text-blue-200 mt-2">
                <strong>Important:</strong> The presence of CVEs doesn't necessarily mean your service is vulnerable - it depends on the specific
                software version, configuration, and security measures in place. Always consult the official security advisories for your specific software.
            </p>
        </div>

        <!-- All CVEs -->
        @if($port->cves->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">
                All Known Vulnerabilities ({{ $port->cves->count() }})
            </h2>

            <div class="space-y-4">
                @foreach($port->cves as $cve)
                    <div id="{{ $cve->cve_id }}" class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:shadow-md transition scroll-mt-8">
                        <div class="flex items-start justify-between mb-3">
                            <a href="https://nvd.nist.gov/vuln/detail/{{ $cve->cve_id }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="font-mono text-lg font-bold text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $cve->cve_id }}
                            </a>

                            <div class="flex items-center gap-2">
                                @if($cve->severity)
                                    @php
                                        $severityClass = match(strtoupper($cve->severity)) {
                                            'CRITICAL' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                            'HIGH' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
                                            'MEDIUM' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                            'LOW' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-3 py-1 rounded text-sm font-bold {{ $severityClass }}">
                                        {{ $cve->severity }}
                                    </span>
                                @endif
                                @if($cve->cvss_score)
                                    <span class="inline-flex items-center px-3 py-1 rounded text-sm font-bold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        {{ number_format($cve->cvss_score, 1) }} CVSS
                                    </span>
                                @endif
                            </div>
                        </div>

                        <p class="text-gray-700 dark:text-gray-300 mb-4 leading-relaxed">
                            {{ $cve->description }}
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Published Date</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ $cve->published_date->format('F d, Y') }}</dd>
                            </div>
                            @if($cve->weakness_types && count($cve->weakness_types) > 0)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Common Weakness Types (CWE)</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ implode(', ', $cve->weakness_types) }}</dd>
                            </div>
                            @endif
                        </div>

                        @if($cve->references && count($cve->references) > 0)
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">References</dt>
                            <dd class="text-sm space-y-1">
                                @foreach(array_slice($cve->references, 0, 5) as $reference)
                                    <a href="{{ $reference }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="block text-blue-600 dark:text-blue-400 hover:underline truncate">
                                        {{ $reference }}
                                    </a>
                                @endforeach
                                @if(count($cve->references) > 5)
                                    <div class="text-gray-500 dark:text-gray-400 text-xs">
                                        + {{ count($cve->references) - 5 }} more references
                                    </div>
                                @endif
                            </dd>
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @else
        <!-- No CVEs Found -->
        <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-400 p-6">
            <h3 class="text-lg font-semibold text-green-900 dark:text-green-300 mb-2">No Vulnerabilities Found</h3>
            <p class="text-sm text-green-800 dark:text-green-200">
                No port-specific CVEs have been found in the National Vulnerability Database for port {{ $port->port_number }}.
                This doesn't guarantee the port is secure - always follow security best practices and keep your software updated.
            </p>
        </div>
        @endif

        <!-- Back to Port -->
        <div class="mt-8">
            <a href="{{ route('port.show', $port->port_number) }}"
               class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                ← Back to Port {{ $port->port_number }}
            </a>
        </div>

        <!-- External Resources -->
        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Additional Resources</h3>
            <div class="space-y-2 text-sm">
                <a href="https://nvd.nist.gov/vuln/search/results?form_type=Advanced&results_type=overview&search_type=all&query=port+{{ $port->port_number }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="block text-blue-600 dark:text-blue-400 hover:underline">
                    Search NVD for more CVEs mentioning port {{ $port->port_number }} →
                </a>
                <a href="https://cve.mitre.org/cgi-bin/cvekey.cgi?keyword=port+{{ $port->port_number }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="block text-blue-600 dark:text-blue-400 hover:underline">
                    Search MITRE CVE Database →
                </a>
            </div>
        </div>

        <!-- Data Sources -->
        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Data Sources</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600 dark:text-gray-400">
                @if($port->security && $port->security->cve_updated_at)
                <div>
                    <div class="font-medium text-gray-900 dark:text-white mb-1">NVD CVE Database</div>
                    <div class="text-xs">
                        Last updated: {{ $port->security->cve_updated_at->format('M d, Y') }}
                    </div>
                </div>
                @endif
                @if($port->security && $port->security->shodan_updated_at)
                <div>
                    <div class="font-medium text-gray-900 dark:text-white mb-1">Shodan Exposure Data</div>
                    <div class="text-xs">
                        Last updated: {{ $port->security->shodan_updated_at->format('M d, Y') }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Schema.org Structured Data -->
    <x-schema-json
        type="TechArticle"
        :data="[
            'headline' => 'Port ' . $port->port_number . ' - Known Vulnerabilities and CVEs',
            'description' => $metaDescription,
            'datePublished' => $port->created_at->toIso8601String(),
            'dateModified' => $port->security && $port->security->cve_updated_at
                ? $port->security->cve_updated_at->toIso8601String()
                : $port->updated_at->toIso8601String(),
        ]"
    />
</x-layouts.app>
