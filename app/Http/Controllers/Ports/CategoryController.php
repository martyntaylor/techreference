<?php

namespace App\Http\Controllers\Ports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Display ports in the specified category.
     */
    public function show(Request $request, Category $slug): View
    {
        // Route model binding provides the Category via 'slug' parameter
        $category = $slug;

        // Get filters from request
        $protocol = $request->input('protocol');
        $riskLevel = $request->input('risk_level');
        $sort = $request->input('sort', 'port_number'); // port_number, name, risk
        $page = $request->input('page', 1);
        $perPage = 50;

        // Build cache key based on filters (without page)
        $cacheKey = sprintf(
            'category:%s:protocol:%s:risk:%s:sort:%s',
            $category->slug,
            $protocol ?? 'all',
            $riskLevel ?? 'all',
            $sort
        );

        // Cache the full collection for 1 hour (limit to 2000 items for memory safety)
        $categoryId = $category->id;
        $allPorts = Cache::remember($cacheKey, 3600, function () use ($categoryId, $protocol, $riskLevel, $sort) {
            // Start with Port model to use query scopes
            $query = Port::query()
                ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))
                ->with(['security', 'categories']);

            // Apply filters using query scopes
            if ($protocol) {
                $query->byProtocol($protocol);
            }

            if ($riskLevel) {
                $query->byRisk($riskLevel);
            }

            // Apply sorting
            switch ($sort) {
                case 'name':
                    $query->orderBy('service_name');
                    break;
                case 'risk':
                    $query->orderByRaw("CASE risk_level WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 END");
                    break;
                case 'popular':
                    $query->orderBy('view_count', 'desc');
                    break;
                default:
                    $query->orderBy('port_number');
            }

            // Get all matching ports
            $ports = $query->limit(2000)->get();

            // Group by port_number and keep only the first entry per port
            // (unless protocol filter is active, then show all matching protocols)
            if (!$protocol) {
                $ports = $ports->groupBy('port_number')->map(function ($portGroup) {
                    // Return the first port, but add protocols array for display
                    $firstPort = $portGroup->first();
                    $firstPort->setAttribute('protocols', $portGroup->pluck('protocol')->toArray());
                    return $firstPort;
                })->values();
            }

            return $ports;
        });

        // Paginate the cached collection in-memory
        $ports = new LengthAwarePaginator(
            $allPorts->forPage($page, $perPage),
            $allPorts->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Get filter counts for UI
        $filterCounts = Cache::remember("category:{$category->slug}:filter-counts", 3600, function () use ($categoryId) {
            $category = Category::findOrFail($categoryId);
            return [
                'protocols' => $category->ports()
                    ->selectRaw('protocol, COUNT(*) as count')
                    ->groupBy('protocol')
                    ->pluck('count', 'protocol'),
                'risk_levels' => $category->ports()
                    ->selectRaw('risk_level, COUNT(*) as count')
                    ->groupBy('risk_level')
                    ->pluck('count', 'risk_level'),
            ];
        });

        return view('ports.category', [
            'category' => $category,
            'ports' => $ports,
            'filterCounts' => $filterCounts,
            'currentFilters' => [
                'protocol' => $protocol,
                'risk_level' => $riskLevel,
                'sort' => $sort,
            ],
        ]);
    }
}
