<?php

namespace App\Http\Controllers\Ports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
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

        // Build cache key based on filters
        $cacheKey = sprintf(
            'category:%s:protocol:%s:risk:%s:sort:%s:page:%d',
            $category->slug,
            $protocol ?? 'all',
            $riskLevel ?? 'all',
            $sort,
            $request->input('page', 1)
        );

        // Cache for 1 hour
        $categoryId = $category->id;
        $ports = Cache::remember($cacheKey, 3600, function () use ($categoryId, $protocol, $riskLevel, $sort) {
            $category = Category::findOrFail($categoryId);
            $query = $category->ports()
                ->with(['security', 'categories']);

            // Apply filters
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

            return $query->paginate(50);
        });

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
