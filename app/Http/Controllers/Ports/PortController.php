<?php

namespace App\Http\Controllers\Ports;

use App\Http\Controllers\Controller;
use App\Models\Port;
use App\Models\PortPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PortController extends Controller
{
    /**
     * Display the specified port (all protocols).
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\Port> $ports
     */
    public function show(Request $request, Collection $ports): View
    {
        // Abort if binding returned no ports
        if ($ports->isEmpty()) {
            abort(404);
        }

        // Load relationships for all port protocol variants
        $ports->load([
            'software' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name');
            },
            'security',
            'cves' => function ($query) {
                $query->orderBy('published_date', 'desc')
                    ->limit(20); // Load top 20 most recent CVEs
            },
            'configs' => function ($query) {
                $query->where('verified', true)
                    ->orderBy('platform');
            },
            'verifiedIssues' => function ($query) {
                $query->orderBy('upvotes', 'desc')
                    ->limit(10);
            },
            'relatedPorts' => function ($query) {
                $query->orderBy('port_number');
            },
            'categories',
        ]);

        // Get the first port for general metadata (port number is the same across protocols)
        $primaryPort = $ports->first();

        // Increment view count for all protocol variants
        if (! $request->is('*/preview')) {
            Port::where('port_number', $primaryPort->port_number)->increment('view_count');
        }

        // Filter related ports to exclude same port number (different protocols shown separately)
        $filteredRelatedPorts = $primaryPort->relatedPorts->filter(
            fn($relatedPort) => $relatedPort->port_number !== $primaryPort->port_number
        );

        // Load port page content if available
        $portPage = PortPage::where('port_number', $primaryPort->port_number)->first();

        return view('ports.show', [
            'ports' => $ports, // Collection of all protocols
            'port' => $primaryPort, // Primary port for metadata
            'portPage' => $portPage, // Port page content (may be null)
            'relatedPorts' => $filteredRelatedPorts, // Related ports (excluding protocol variants)
            'pageTitle' => sprintf('Port %d%s',
                $primaryPort->port_number,
                $primaryPort->service_name ? ' - ' . $primaryPort->service_name : ''
            ),
        ]);
    }

    /**
     * Display all vulnerabilities for a specific port.
     */
    public function vulnerabilities(int $port): View
    {
        $cacheKey = "port:{$port}:vulnerabilities:v1";

        [$ports, $primaryPort] = Cache::remember($cacheKey, 3600, function () use ($port) {
            $ports = Port::where('port_number', $port)
                ->with(['categories', 'security'])
                ->get();

            if ($ports->isEmpty()) {
                return [collect(), null];
            }

            $primary = $ports->first();
            $primary->load([
                'cves' => fn ($q) => $q->orderBy('published_date', 'desc'),
            ]);

            return [$ports, $primary];
        });

        if ($ports->isEmpty() || !$primaryPort) {
            abort(404, "Port {$port} not found");
        }

        return view('ports.vulnerabilities', [
            'port' => $primaryPort,
            'ports' => $ports,
        ]);
    }

    /**
     * Handle 404 errors with suggestions for similar ports.
     */
    public function handleNotFound(int $portNumber): View
    {
        // Find similar ports (nearby port numbers)
        $suggestions = Port::whereBetween('port_number', [
            max(1, $portNumber - 10),
            min(65535, $portNumber + 10)
        ])
            ->where('port_number', '!=', $portNumber)
            ->limit(5)
            ->get();

        // Also suggest popular ports if no nearby ports
        if ($suggestions->isEmpty()) {
            $suggestions = Port::popular(5)->get();
        }

        return view('errors.port-not-found', [
            'portNumber' => $portNumber,
            'suggestions' => $suggestions,
        ]);
    }
}
