<?php

namespace App\Http\Controllers\Ports;

use App\Http\Controllers\Controller;
use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PortController extends Controller
{
    /**
     * Display the specified port.
     */
    public function show(Request $request, int $portNumber): View
    {
        // Cache key for this port
        $cacheKey = "port:{$portNumber}";

        // Try to get from cache (1 hour TTL)
        $port = Cache::remember($cacheKey, 3600, function () use ($portNumber) {
            return Port::where('port_number', $portNumber)
                ->with([
                    'software' => function ($query) {
                        $query->where('is_active', true)
                            ->orderBy('name');
                    },
                    'security',
                    'configs' => function ($query) {
                        $query->where('verified', true)
                            ->orderBy('platform')
                            ->orderBy('upvotes', 'desc');
                    },
                    'verifiedIssues' => function ($query) {
                        $query->orderBy('upvotes', 'desc')
                            ->limit(10);
                    },
                    'relatedPorts' => function ($query) {
                        $query->orderBy('port_number');
                    },
                    'categories' => function ($query) {
                        $query->where('is_active', true)
                            ->orderBy('display_order');
                    },
                ])
                ->firstOrFail();
        });

        // Increment view count asynchronously (don't invalidate cache)
        if (! $request->is('*/preview')) {
            dispatch(function () use ($port) {
                $port->incrementViewCount();
            })->afterResponse();
        }

        // Get suggested ports if 404 would occur
        // This is now handled by firstOrFail() which throws 404

        return view('ports.show', [
            'port' => $port,
            'pageTitle' => sprintf('Port %d (%s)%s',
                $port->port_number,
                $port->protocol,
                $port->service_name ? ' - ' . $port->service_name : ''
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
