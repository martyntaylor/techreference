@php
$pageTitle = "Port {$port->port_number} ({$port->protocol})" . ($port->service_name ? " - {$port->service_name}" : '');
$metaDescription = $port->description ?: "Technical reference for port {$port->port_number} ({$port->protocol}). Learn about services, security considerations, configuration examples, and common issues.";
$breadcrumbs = [];
if($port->categories->isNotEmpty()) {
    $breadcrumbs[] = [
        'name' => $port->categories->first()->name,
        'url' => route('ports.category', $port->categories->first()->slug)
    ];
}
$breadcrumbs[] = ['name' => "Port {$port->port_number}"];
@endphp

<x-meta-tags
    :title="$pageTitle"
    :description="$metaDescription"
    :keywords="'port ' . $port->port_number . ', ' . $port->protocol . ', ' . $port->service_name . ', network ports'"
    type="article"
/>

<x-layouts.app :pageTitle="$pageTitle">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumbs -->
        <x-breadcrumbs :items="$breadcrumbs" />

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">
                Port {{ $port->port_number }} ({{ $port->protocol }})
                @if($port->service_name)
                    - {{ $port->service_name }}
                @endif
            </h1>

            <!-- Risk Badge -->
            @if($port->risk_level)
                <x-security-badge :level="$port->risk_level" />
            @endif
        </div>

        <!-- Quick Reference -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Quick Reference</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Port Number</dt>
                    <dd class="text-lg text-gray-900 dark:text-white">{{ $port->port_number }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Protocol</dt>
                    <dd class="text-lg text-gray-900 dark:text-white">{{ $port->protocol }}</dd>
                </div>
                @if($port->service_name)
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Service</dt>
                    <dd class="text-lg text-gray-900 dark:text-white">{{ $port->service_name }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IANA Status</dt>
                    <dd class="text-lg text-gray-900 dark:text-white">
                        {{ $port->iana_official ? 'Official' : 'Unofficial' }}
                        @if($port->iana_status)
                            ({{ $port->iana_status }})
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Encrypted by Default</dt>
                    <dd class="text-lg text-gray-900 dark:text-white">{{ $port->encrypted_default ? 'Yes' : 'No' }}</dd>
                </div>
            </dl>

            @if($port->description)
                <div class="mt-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Description</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $port->description }}</dd>
                </div>
            @endif
        </div>

        <!-- Software Using This Port -->
        @if($port->software->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Software Using This Port</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($port->software as $software)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $software->name }}</h3>
                        @if($software->category)
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $software->category }}</p>
                        @endif
                        @if($software->description)
                            <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">{{ $software->description }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Security Assessment -->
        @if($port->security)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Security Assessment</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Shodan Exposures</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($port->security->shodan_exposed_count) }}
                    </div>
                    @if($port->security->shodan_updated_at)
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Updated {{ $port->security->shodan_updated_at->diffForHumans() }}
                        </div>
                    @endif
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Known CVEs</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $port->security->cve_count }}
                    </div>
                    @if($port->security->latest_cve)
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Latest: {{ $port->security->latest_cve }}
                        </div>
                    @endif
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Risk Level</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $port->risk_level }}
                    </div>
                </div>
            </div>

            @if($port->security->security_recommendations)
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4">
                    <p class="text-sm text-yellow-700 dark:text-yellow-300">
                        {{ $port->security->security_recommendations }}
                    </p>
                </div>
            @endif
        </div>
        @endif

        <!-- Configuration Examples -->
        @if($port->configs->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Configuration & Access</h2>

            @php
            $configTabs = [];
            foreach($port->configs->groupBy('platform') as $platform => $configs) {
                $content = '<div class="space-y-4">';
                foreach($configs as $config) {
                    $content .= view('components.code-snippet', [
                        'title' => $config->title,
                        'code' => $config->code_snippet,
                        'language' => $config->language,
                        'explanation' => $config->explanation,
                    ])->render();
                }
                $content .= '</div>';

                $configTabs[Str::slug($platform)] = [
                    'label' => $platform,
                    'content' => $content
                ];
            }
            @endphp

            <x-tabs
                :tabs="$configTabs"
                storageKey="port-config-tab"
            />
        </div>
        @endif

        <!-- Common Issues -->
        @if($port->verifiedIssues->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Common Issues</h2>
            <div class="space-y-4">
                @foreach($port->verifiedIssues as $issue)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $issue->issue_title }}</h3>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Symptoms:</span>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $issue->symptoms }}</p>
                        </div>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Solution:</span>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $issue->solution }}</p>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                            @if($issue->platform)
                                <span>Platform: {{ $issue->platform }}</span>
                            @endif
                            <span>👍 {{ $issue->upvotes }} helpful</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Related Ports -->
        @if($port->relatedPorts->isNotEmpty())
            <x-related-content
                title="Related Ports"
                type="ports"
                :items="$port->relatedPorts->map(fn($relatedPort) => [
                    'number' => $relatedPort->port_number,
                    'protocol' => $relatedPort->protocol,
                    'name' => $relatedPort->service_name,
                    'description' => $relatedPort->description,
                    'risk_level' => $relatedPort->risk_level,
                    'url' => route('port.show', $relatedPort->port_number)
                ])->toArray()"
            />
        @endif
    </div>

    <!-- Schema.org Structured Data -->
    <x-schema-json
        type="TechArticle"
        :data="[
            'headline' => $pageTitle,
            'description' => $metaDescription,
            'datePublished' => $port->created_at->toIso8601String(),
            'dateModified' => $port->updated_at->toIso8601String(),
        ]"
    />

    @if($port->verifiedIssues->isNotEmpty())
        <!-- FAQ Schema for Common Issues -->
        <x-schema-json
            type="FAQPage"
            :data="[
                'questions' => $port->verifiedIssues->map(fn($issue) => [
                    'question' => $issue->issue_title,
                    'answer' => strip_tags($issue->solution)
                ])->toArray()
            ]"
        />
    @endif
</x-layouts.app>
