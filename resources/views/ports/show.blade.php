@php
// $port is the primary port for metadata, $ports is the collection of all protocols
$protocolsList = $ports->pluck('protocol')->join(', ');

// Use PortPage data with intelligent fallbacks
$pageTitle = $portPage?->page_title ?? "Port {$port->port_number}" . ($port->service_name ? " - {$port->service_name}" : '');
$metaDescription = $portPage?->meta_description ?? ($port->description ?: "Technical reference for port {$port->port_number} ({$protocolsList}). Learn about services, security considerations, configuration examples, and common issues.");
$heading = $portPage?->heading ?? "Port {$port->port_number}" . ($port->service_name ? " - {$port->service_name}" : '');

// Helper function to get a specific content block by type
$getContentBlock = function($type) use ($portPage) {
    if (!$portPage || !$portPage->content_blocks) {
        return null;
    }
    return collect($portPage->content_blocks)->firstWhere('type', $type);
};

$breadcrumbs = [
    ['name' => 'Ports', 'url' => route('ports.index')]
];
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
    :keywords="collect(['port ' . $port->port_number, $port->protocol, $port->service_name, 'network ports'])->filter()->join(', ')"
    type="article"
/>

<x-layouts.app :pageTitle="$pageTitle">
    {{-- Breadcrumbs Slot --}}
    <x-slot name="breadcrumbs">
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot>

    {{-- Main Content --}}
    <div class="max-w-7xl mx-auto">
        
        {{-- Page Header --}}
        <h1 class="mb-20">
            {{ $heading }}
        </h1>

        {{-- Overview --}}
        @if($block = $getContentBlock('overview'))
            <x-content-block :title="$block['title']" :content="$block['content']" />
        @endif


        {{-- Quick Reference --}}
        <x-content-block title="Quick Reference">
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Port Number</dt>
                    <dd class="text-lg text-gray-900 dark:text-white">{{ $port->port_number }}</dd>
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
            </dl>

            @if($port->description)
                <div class="mt-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Description</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $port->description }}</dd>
                </div>
            @endif

            {{-- Protocol-Specific Information --}}
            @if($ports->count() > 1)
            <h3 class="mb-4 h3-mt">Protocol Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($ports as $protocolPort)
                <div class="border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex relative items-center justify-between mb-2">
                        <h4>{{ $protocolPort->protocol }}</h4>
                        @if($protocolPort->risk_level)
                            <x-security-badge :level="$protocolPort->risk_level" />
                        @endif
                    </div>
                    @if($protocolPort->description && $protocolPort->description !== $port->description)
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">{{ $protocolPort->description }}</p>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Encrypted by Default</dt>
                        <dd class="text-lg text-gray-900 dark:text-white">{{ $protocolPort->encrypted_default ? 'Yes' : 'No' }}</dd>
                    </div>
                    @if($protocolPort->common_uses)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Common Uses</dt>
                        <dd class="text-lg text-gray-900 dark:text-white">{{ \Illuminate\Support\Str::limit($protocolPort->common_uses, 50) }}</dd>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif

        </x-content-block>
        

        {{-- Common Uses Content Block --}}
        @if($port->software->isNotEmpty() || $getContentBlock('common_uses'))
        @php $contentTitle = "Using Port " . $port->port_number; @endphp
        <x-content-block :title="$contentTitle ">
            @if($block = $getContentBlock('common_uses'))
            <h3 class="mb-4">{{ $block['title'] }}</h3>
                {{ $block['content'] }}
            @endif

            {{-- Software Using This Port --}}
            @if($port->software->isNotEmpty())
            <h3 class="mb-4 h3-mt">Software Using Port {{ $port->port_number }}</h3>
            <ul class="list-none">
                @foreach($port->software as $software)
                    <li class="mb-4">
                        <h4>{{ $software->name }}</h4>
                        @if($software->category)
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $software->category }}</p>
                        @endif
                        @if($software->description)
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $software->description }}</p>
                        @endif
                    </li>
                @endforeach
            </div>
            @endif



        </x-content-block>
        @endif

        <!-- Best Practices Content Block -->
        @if($block = $getContentBlock('best_practices'))
        <x-content-block :title="$block['title']" :content="$block['content']" />
        @endif

        <!-- Configuration Examples -->
        @if($port->configs->isNotEmpty())
            @php $contentTitle = "Configuring Port " . $port->port_number; @endphp
            <x-content-block :title="$contentTitle">
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

                $configTabs[\Illuminate\Support\Str::slug($platform)] = [
                    'label' => $platform,
                    'content' => $content
                ];
            }
            @endphp

            <x-tabs
                :tabs="$configTabs"
                storageKey="port-config-tab"
            />
            </x-content-block>
        @endif

        <!-- Troubleshooting Content Block -->
        @if($block = $getContentBlock('troubleshooting'))
            <x-content-block :title="$block['title']" :content="$block['content']" />
        @endif

        <!-- Common Issues -->
        @if($port->verifiedIssues->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4" id="common-issues">Common Issues</h2>
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



        {{-- Security Content Block --}}
        <x-content-block title="Security">

            @if($block = $getContentBlock('security'))
                <h3 class="mb-4">{{ $block['title'] }}</h4>
                {{ $block['content'] }}
            @endif

            <!-- Security Assessment -->
            @if($port->security)
                <h3 class="mb-4 h3-mt">Security Assessment</h4>
                <!-- Key Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Internet Exposures</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($port->security->shodan_exposed_count) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Shodan detected
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Known CVEs</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($port->security->cve_count) }}
                        </div>
                        @if($port->security->latest_cve)
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Latest: {{ $port->security->latest_cve }}
                            </div>
                        @endif
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
                        <div class="text-sm text-gray-500 dark:text-gray-400">Risk Level</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $port->risk_level }}
                        </div>
                    </div>
                </div>

                <!-- CVE Severity Breakdown -->
                @if($port->security->cve_count > 0)
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">CVE Severity Breakdown</h3>
                    <x-cve-severity-badges :security="$port->security" />
                </div>
                @endif

                <!-- Security Recommendations -->
                @if($port->security->security_recommendations)
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4">
                        <div class="text-sm text-yellow-700 dark:text-yellow-300">
                            {!! nl2br(e($port->security->security_recommendations)) !!}
                        </div>
                    </div>
                @endif

                <!-- Internet Exposure Analysis -->
                @if($port->security->shodan_exposed_count > 0 && ($port->security->top_countries || $port->security->top_products || $port->security->top_operating_systems || $port->security->top_organizations))

                    <h3 class="mb-4 h3-mt">Internet Exposure Analysis</h3>
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-6 space-y-2">
                        <p>
                            This data comes from <a href="https://www.shodan.io" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:underline">Shodan</a>, a search engine that continuously scans the internet for publicly accessible services. The statistics below show real-world exposure patterns for port {{ $port->port_number }}, revealing where and how this port is actively being used across the internet.
                        </p>
                        <p>
                            Understanding these exposure patterns is critical for security planning. If your organization uses this port, you can compare your configuration against common deployments, identify potential risks, and implement appropriate security measures based on real-world attack patterns.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <x-top-list
                            title="Top Countries"
                            :items="$port->security->top_countries"
                            icon="🌍"
                            description="Geographic distribution of exposed instances. High concentrations may indicate regional hosting preferences or targeted deployment patterns."
                        />

                        <x-top-list
                            title="Top Products Detected"
                            :items="$port->security->top_products"
                            icon="📦"
                            description="Most commonly detected software applications using this port. Helps identify which implementations are widely deployed and potentially targeted by attackers."
                        />

                        @if($port->security->top_asns)
                        <x-top-list
                            title="Top Autonomous Systems (ASNs)"
                            :items="$port->security->top_asns"
                            icon="🌐"
                            description="Network infrastructure providers hosting exposed services. ASN data helps identify hosting patterns and potential network-level security concerns."
                        />
                        @endif

                        <x-top-list
                            title="Top Organizations"
                            :items="$port->security->top_organizations"
                            icon="🏢"
                            description="Organizations with the most exposed instances. Often includes cloud providers, ISPs, and major hosting companies."
                        />

                        <x-top-list
                            title="Top Operating Systems"
                            :items="$port->security->top_operating_systems"
                            icon="💻"
                            description="Operating systems running services on this port. Reveals platform-specific vulnerabilities and deployment environments to consider."
                        />
                    </div>
                @endif
            @endif

            <!-- CVE Details -->
            @if($port->cves && $port->cves->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">
                    Known Vulnerabilities
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(showing 5 of {{ $port->cves->count() }})</span>
                </h2>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Port-specific CVEs from the National Vulnerability Database that explicitly mention port {{ $port->port_number }}.
                </p>

                <!-- Recent CVEs (Top 5) -->
                <div class="space-y-3">
                    @foreach($port->cves->take(5) as $cve)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-sm transition">
                            <div class="flex items-start justify-between mb-2">
                                <a href="https://nvd.nist.gov/vuln/detail/{{ $cve->cve_id }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400 hover:underline">
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
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold {{ $severityClass }}">
                                            {{ $cve->severity }}
                                        </span>
                                    @endif
                                    @if($cve->cvss_score)
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            {{ number_format($cve->cvss_score, 1) }} CVSS
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                {{ \Illuminate\Support\Str::limit($cve->description, 200) }}
                                @if(strlen($cve->description) > 200)
                                    <a href="{{ route('port.vulnerabilities', $port->port_number) }}#{{ $cve->cve_id }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        Read more →
                                    </a>
                                @endif
                            </p>

                            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                <span>Published: {{ $cve->published_date->format('M d, Y') }}</span>
                                @if($cve->weakness_types && count($cve->weakness_types) > 0)
                                    <span>CWE: {{ implode(', ', array_slice($cve->weakness_types, 0, 3)) }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('port.vulnerabilities', $port->port_number) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                        View All {{ $port->cves->count() }} Vulnerabilities →
                    </a>
                </div>
            </div>
            @endif

        </x-content-block>


        <!-- FAQs from PortPage -->
        @if($portPage && $portPage->faqs)
        <x-content-block title="Frequently Asked Questions">
            <div class="space-y-6">
                @foreach(collect($portPage->faqs)->sortBy('order') as $faq)
                    <h4 class="mb-4">{{ $faq['question'] }}</h3>
                    <p class="text-gray-700 dark:text-gray-300">{{ $faq['answer'] }}</p>
                @endforeach
            </div>
        </x-content-block>
        @endif

        <!-- Videos from PortPage -->
        @if($portPage && $portPage->video_urls)
        <x-content-block title="Video Resources">

            <div class="mt-8 grid grid-cols-1 gap-8 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($portPage->video_urls as $video)
                    <div @mouseenter="playing = true; $refs.video.play()" @mouseleave="playing = false; $refs.video.pause(); $refs.video.currentTime = 0" class="group relative">
                      <img src="https://assets.tailwindcss.com/templates/compass/nietzsche-thumbnail.png" alt="" class="aspect-video w-full rounded-lg bg-gray-950 object-cover group-hover:hidden dark:bg-gray-900">
                      <video x-ref="video" src="#" muted loop playsinline preload="auto" class="hidden aspect-video w-full rounded-lg bg-gray-950 object-cover group-hover:block dark:bg-gray-900"></video>                    
                    @if(isset($video['description']))
                    <p class="mt-4 text-sm/6 font-semibold text-gray-950 dark:text-white">{{ $video['description'] }}</p>
                    @endif
                    </div>
                @endforeach
            </div>
  
        </x-content-block>
        @endif

        {{-- Related Ports --}}
        @if($relatedPorts->isNotEmpty())
        @php
            // Group related ports by relation type
            $portsByRelationType = $relatedPorts->groupBy(fn($port) => $port->pivot->relation_type ?? 'associated_with');

            // Define relation type order and labels
            $relationTypes = [
                'secure_version' => ['label' => 'Secure Version', 'color' => 'green'],
                'alternative' => ['label' => 'Alternative', 'color' => 'purple'],
                'complementary' => ['label' => 'Complementary', 'color' => 'teal'],
                'part_of_suite' => ['label' => 'Part of Suite', 'color' => 'blue'],
                'deprecated_by' => ['label' => 'Deprecated By', 'color' => 'orange'],
                'conflicts_with' => ['label' => 'Conflicts With', 'color' => 'red'],
                'associated_with' => ['label' => 'Associated With', 'color' => 'gray'],
            ];

            // Determine default tab (first available in priority order)
            $defaultTab = collect(['secure_version', 'alternative', 'complementary', 'part_of_suite', 'deprecated_by', 'conflicts_with', 'associated_with'])
                ->first(fn($type) => $portsByRelationType->has($type));
        @endphp

        <x-content-block title="Related Ports">
            <!-- Relation Type Tabs -->
            <div x-data="{ activeTab: '{{ $defaultTab }}' }" class="space-y-4">
                <!-- Tab Headers -->
                <div class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                    @foreach($relationTypes as $type => $config)
                        @if($portsByRelationType->has($type))
                            @php
                                $count = $portsByRelationType->get($type)->count();
                                $colorClass = match($config['color']) {
                                    'green' => 'border-green-500 text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30',
                                    'purple' => 'border-purple-500 text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/30',
                                    'teal' => 'border-teal-500 text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-900/30',
                                    'blue' => 'border-blue-500 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30',
                                    'orange' => 'border-orange-500 text-orange-700 dark:text-orange-300 bg-orange-50 dark:bg-orange-900/30',
                                    'red' => 'border-red-500 text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/30',
                                    default => 'border-gray-500 text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700',
                                };
                                $inactiveClass = 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:border-gray-300 dark:hover:border-gray-600';
                            @endphp
                            <button
                                @click="activeTab = '{{ $type }}'"
                                :class="activeTab === '{{ $type }}' ? '{{ $colorClass }}' : '{{ $inactiveClass }}'"
                                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors focus:outline-none"
                            >
                                {{ $config['label'] }}
                                <span class="ml-1.5 px-2 py-0.5 rounded-full bg-white dark:bg-gray-800 text-xs font-semibold">
                                    {{ $count }}
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>

                <!-- Tab Content -->
                @foreach($relationTypes as $type => $config)
                    @if($portsByRelationType->has($type))
                        <div x-show="activeTab === '{{ $type }}'" x-cloak class="space-y-3">
                            @foreach($portsByRelationType->get($type) as $relatedPort)
                                <a
                                    href="{{ route('port.show', $relatedPort->port_number) }}"
                                    class="block p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-400 hover:shadow-md transition group"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                                    Port {{ $relatedPort->port_number }}
                                                </span>
                                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $relatedPort->protocol }}
                                                </span>
                                                @if($relatedPort->risk_level)
                                                    <x-security-badge :level="$relatedPort->risk_level" size="sm" />
                                                @endif
                                            </div>

                                            <h4 class="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">
                                                {{ $relatedPort->service_name }}
                                            </h4>

                                            @if($relatedPort->pivot->description)
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 italic">
                                                    {{ $relatedPort->pivot->description }}
                                                </p>
                                            @elseif($relatedPort->description)
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                                    {{ \Illuminate\Support\Str::limit($relatedPort->description, 120) }}
                                                </p>
                                            @endif
                                        </div>

                                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition flex-shrink-0 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </x-content-block>
        @endif

        {{-- Data Sources --}}
        @php
            ob_start();
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600 dark:text-gray-400">
            <div>
                <div class="font-medium text-gray-900 dark:text-white mb-1">IANA Registry</div>
                <div class="text-xs">
                    @if($port->iana_updated_at)
                        Last updated: {{ $port->iana_updated_at->format('M d, Y') }}
                    @else
                        Official port assignments
                    @endif
                </div>
            </div>
            @if($port->security)
                @if($port->security->shodan_updated_at)
                <div>
                    <div class="font-medium text-gray-900 dark:text-white mb-1">Shodan</div>
                    <div class="text-xs">
                        Last updated: {{ $port->security->shodan_updated_at->format('M d, Y') }}
                    </div>
                </div>
                @endif
                @if($port->security->cve_updated_at)
                <div>
                    <div class="font-medium text-gray-900 dark:text-white mb-1">NVD CVE Database</div>
                    <div class="text-xs">
                        Last updated: {{ $port->security->cve_updated_at->format('M d, Y') }}
                    </div>
                </div>
                @endif
            @endif
        </div>
        @php
            $dataSourcesContent = ob_get_clean();
        @endphp
        <x-content-block title="Data Sources">
            {!! $dataSourcesContent !!}
        </x-content-block>




    

    <!-- Sidebar Slot -->
    <x-slot name="sidebar">
        <x-table-of-contents />
    </x-slot>

    <!-- Schema.org Structured Data - One per protocol -->
    @foreach($ports as $protocolPort)
        <x-schema-json
            type="TechArticle"
            :data="[
                'headline' => 'Port ' . $protocolPort->port_number . ' (' . $protocolPort->protocol . ')' . ($protocolPort->service_name ? ' - ' . $protocolPort->service_name : ''),
                'description' => $protocolPort->description ?: $metaDescription,
                'datePublished' => $protocolPort->created_at->toIso8601String(),
                'dateModified' => $protocolPort->updated_at->toIso8601String(),
            ]"
        />
    @endforeach

    @php
        // Combine FAQs from both PortPage and verified issues for schema
        $schemaFaqs = collect();

        // Add PortPage FAQs first (higher priority)
        if ($portPage && $portPage->faqs) {
            $schemaFaqs = $schemaFaqs->concat(
                collect($portPage->faqs)->map(fn($faq) => [
                    'question' => $faq['question'],
                    'answer' => $faq['answer']
                ])
            );
        }

        // Add verified issues as FAQs
        if ($port->verifiedIssues->isNotEmpty()) {
            $schemaFaqs = $schemaFaqs->concat(
                $port->verifiedIssues->map(fn($issue) => [
                    'question' => $issue->issue_title,
                    'answer' => strip_tags($issue->solution)
                ])
            );
        }
    @endphp

    @if($schemaFaqs->isNotEmpty())
        <!-- FAQ Schema -->
        <x-schema-json
            type="FAQPage"
            :data="[
                'questions' => $schemaFaqs->toArray()
            ]"
        />
    @endif

    <style>
        [x-cloak] { display: none !important; }
        html {
        scroll-behavior: smooth;
        }
    </style>

</x-layouts.app>
