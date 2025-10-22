<x-meta-tags
    title="Network Ports Reference - Complete Port Database"
    description="Comprehensive database of network ports, protocols, and services. Search and explore ports by category including web services, databases, email, gaming, and more."
    keywords="network ports, TCP ports, UDP ports, port numbers, protocol reference, port database"
/>

<x-layouts.app pageTitle="Network Ports Reference">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumbs -->
        <x-breadcrumbs :items="[
            ['name' => 'Ports']
        ]" />

        <!-- Hero Section -->
        <div class="text-center mb-12">
            <h1 class="text-5xl font-bold text-gray-900 dark:text-white mb-4">
                Network Ports Reference
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto">
                Network ports are virtual endpoints in an operating system that help identify specific processes or network services.
                They enable multiple services to run simultaneously on a single IP address, with each service listening on its designated port number.
            </p>
        </div>

        <!-- Search Bar -->
        <div class="mb-12">
            <form action="{{ route('search') }}" method="GET" class="max-w-2xl mx-auto">
                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        placeholder="Search ports by number, service name, or protocol..."
                        class="w-full px-6 py-4 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 text-lg"
                        required
                    >
                    <button
                        type="submit"
                        class="absolute right-2 top-1/2 transform -translate-y-1/2 px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition"
                    >
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 text-center">
                <div class="text-4xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                    {{ number_format($popularPorts->sum(fn($p) => 1)) }}+
                </div>
                <div class="text-gray-600 dark:text-gray-400">Documented Ports</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 text-center">
                <div class="text-4xl font-bold text-green-600 dark:text-green-400 mb-2">
                    {{ $categories->count() }}
                </div>
                <div class="text-gray-600 dark:text-gray-400">Categories</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 text-center">
                <div class="text-4xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                    65,535
                </div>
                <div class="text-gray-600 dark:text-gray-400">Total Port Range</div>
            </div>
        </div>

        <!-- Most Popular Ports -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Most Popular Ports</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                @foreach($popularPorts as $port)
                    <x-port-card :port="$port" />
                @endforeach
            </div>
        </div>

        <!-- Categories with Top Ports -->
        <div class="space-y-8">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Browse by Category</h2>

            @foreach($categories as $item)
                @php
                    $category = $item['category'];
                    $topPorts = $item['topPorts'];
                @endphp

                @if($topPorts->isNotEmpty())
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ $category->name }}
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $category->description }}
                                </p>
                            </div>
                            <a
                                href="{{ route('ports.category', $category->slug) }}"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition whitespace-nowrap"
                            >
                                View All {{ $category->ports_count }}
                            </a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                            @foreach($topPorts as $port)
                                <x-port-card :port="$port" />
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <!-- Info Section -->
        <div class="mt-12 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-8">
            <h2 class="text-2xl font-bold text-blue-900 dark:text-blue-100 mb-4">
                Understanding Network Ports
            </h2>
            <div class="prose prose-blue dark:prose-invert max-w-none">
                <p class="text-blue-800 dark:text-blue-200">
                    Network ports are numbered from 0 to 65535 and are divided into three ranges:
                </p>
                <ul class="text-blue-800 dark:text-blue-200 space-y-2">
                    <li>
                        <strong>Well-Known Ports (0-1023):</strong> Reserved for system services and commonly used protocols like HTTP (80), HTTPS (443), and SSH (22).
                    </li>
                    <li>
                        <strong>Registered Ports (1024-49151):</strong> Assigned by IANA for specific services and applications upon request.
                    </li>
                    <li>
                        <strong>Dynamic/Private Ports (49152-65535):</strong> Available for temporary or private use, often assigned dynamically by operating systems.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
