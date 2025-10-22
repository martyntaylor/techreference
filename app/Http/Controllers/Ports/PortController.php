<?php

namespace App\Http\Controllers\Ports;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowPortRequest;
use App\Models\Port;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PortController extends Controller
{
    /**
     * Display the specified port (all protocols).
     */
    public function show(ShowPortRequest $request, Collection $portNumber): View
    {
        // Load relationships for all port protocol variants
        $portNumber->load([
            'software' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name');
            },
            'security',
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

        // Increment view count asynchronously for all protocol variants
        if (! $request->is('*/preview')) {
            $portIds = $portNumber->pluck('id')->toArray();
            dispatch(function () use ($portIds) {
                // Use query builder to avoid triggering model events
                Port::whereIn('id', $portIds)->increment('view_count');
            })->afterResponse();
        }

        // Get the first port for general metadata (port number is the same across protocols)
        $primaryPort = $portNumber->first();

        // Filter related ports to exclude same port number (different protocols shown separately)
        $filteredRelatedPorts = $primaryPort->relatedPorts->filter(
            fn($relatedPort) => $relatedPort->port_number !== $primaryPort->port_number
        );

        return view('ports.show', [
            'ports' => $portNumber, // Collection of all protocols
            'port' => $primaryPort, // Primary port for metadata
            'relatedPorts' => $filteredRelatedPorts, // Related ports (excluding protocol variants)
            'pageTitle' => sprintf('Port %d%s',
                $primaryPort->port_number,
                $primaryPort->service_name ? ' - ' . $primaryPort->service_name : ''
            ),
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
