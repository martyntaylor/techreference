<x-layouts.app
    :pageTitle="'Search Results for \"' . $query . '\"'"
    :metaDescription="'Search results for ' . $query . ' on TechReference'">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-4xl font-bold mb-4">Search Results</h1>
        <p class="text-lg mb-8">Showing results for "{{ $query }}"</p>

        @if($results['ports']->isNotEmpty())
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4">Ports ({{ $results['ports']->total() }} results)</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($results['ports'] as $port)
                        <x-port-card :port="$port" />
                    @endforeach
                </div>
                {{ $results['ports']->appends(request()->query())->links() }}
            </div>
        @endif

        @if($results['ports']->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-600">No results found for "{{ $query }}"</p>
            </div>
        @endif
    </div>
</x-layouts.app>
