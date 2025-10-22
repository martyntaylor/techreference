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
    public function show(Request $request, Port $portNumber): View
    {
        // Load relationships if not already loaded
        if (!$portNumber->relationLoaded('software')) {
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
        }

        // Increment view count asynchronously (don't invalidate cache)
        if (! $request->is('*/preview')) {
            $portId = $portNumber->id;
            dispatch(function () use ($portId) {
                \App\Models\Port::find($portId)?->increment('view_count');
            })->afterResponse();
        }

        return view('ports.show', [
            'port' => $portNumber,
            'pageTitle' => sprintf('Port %d (%s)%s',
                $portNumber->port_number,
                $portNumber->protocol,
                $portNumber->service_name ? ' - ' . $portNumber->service_name : ''
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
