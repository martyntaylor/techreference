<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortSearchRequest;
use App\Models\Port;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Display search results.
     */
    public function index(PortSearchRequest $request): View|JsonResponse
    {
        $query = $request->validated('q');
        $type = $request->validated('type', 'all'); // all, port, error, extension
        $protocol = $request->validated('protocol');
        $riskLevel = $request->validated('risk_level');

        // Build cache key
        $cacheKey = sprintf(
            'search:%s:type:%s:protocol:%s:risk:%s:page:%d',
            md5($query),
            $type,
            $protocol ?? 'all',
            $riskLevel ?? 'all',
            $request->input('page', 1)
        );

        // Cache search results for 15 minutes
        $results = Cache::remember($cacheKey, 900, function () use ($query, $type, $protocol, $riskLevel) {
            $portQuery = Port::searchRanked($query)
                ->with(['security', 'categories']);

            // Apply filters
            if ($protocol) {
                $portQuery->byProtocol($protocol);
            }

            if ($riskLevel) {
                $portQuery->byRisk($riskLevel);
            }

            $ports = $portQuery->paginate(20);

            // TODO: Add error codes and file extensions search when those modules are implemented
            return [
                'ports' => $ports,
                'errors' => collect([]), // Placeholder
                'extensions' => collect([]), // Placeholder
            ];
        });

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($results);
        }

        return view('search.index', [
            'query' => $query,
            'results' => $results,
            'currentFilters' => [
                'type' => $type,
                'protocol' => $protocol,
                'risk_level' => $riskLevel,
            ],
        ]);
    }
}
