<?php

namespace App\Http\Controllers\Ports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $cveSeverity = $request->input('cve_severity');
        $minExposures = $request->input('min_exposures');
        $sort = $request->input('sort', 'port_number');
        $page = $request->input('page', 1);
        $perPage = 50;

        // Build cache key based on filters (without page)
        $cacheKey = sprintf(
            'category:%s:protocol:%s:risk:%s:cve:%s:exp:%s:sort:%s',
            $category->slug,
            $protocol ?? 'all',
            $riskLevel ?? 'all',
            $cveSeverity ?? 'all',
            $minExposures ?? 'all',
            $sort
        );

        // Cache the full collection for 1 hour (limit to 2000 items for memory safety)
        $categoryId = $category->id;
        $allPorts = Cache::tags(['category', "category:{$categoryId}"])->remember($cacheKey, 3600, function () use ($categoryId, $protocol, $riskLevel, $cveSeverity, $minExposures, $sort) {
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

            // CVE severity filter
            if ($cveSeverity) {
                $query->whereHas('security', function ($q) use ($cveSeverity) {
                    switch ($cveSeverity) {
                        case 'critical':
                            $q->where('cve_critical_count', '>', 0);
                            break;
                        case 'high':
                            $q->where('cve_high_count', '>', 0);
                            break;
                        case 'medium':
                            $q->where('cve_medium_count', '>', 0);
                            break;
                        case 'low':
                            $q->where('cve_low_count', '>', 0);
                            break;
                        case 'none':
                            $q->where('cve_count', 0);
                            break;
                    }
                });
            }

            // Minimum exposures filter
            if ($minExposures) {
                $query->whereHas('security', function ($q) use ($minExposures) {
                    $q->where('shodan_exposed_count', '>=', (int) $minExposures);
                });
            }

            // Apply sorting
            switch ($sort) {
                case 'name':
                    $query->orderBy('service_name');
                    break;
                case 'risk':
                    $query->orderByRaw("CASE risk_level WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 END");
                    break;
                case 'exposures':
                    $query->leftJoin('port_security', 'ports.port_number', '=', 'port_security.port_number')
                        ->orderByRaw('port_security.shodan_exposed_count DESC NULLS LAST')
                        ->select('ports.*');
                    break;
                case 'cves':
                    $query->leftJoin('port_security', 'ports.port_number', '=', 'port_security.port_number')
                        ->orderByRaw('port_security.cve_count DESC NULLS LAST')
                        ->select('ports.*');
                    break;
                case 'cvss':
                    $query->leftJoin('port_security', 'ports.port_number', '=', 'port_security.port_number')
                        ->orderByRaw('port_security.cve_avg_score DESC NULLS LAST')
                        ->select('ports.*');
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
        $filterCounts = Cache::tags(['category', "category:{$categoryId}"])->remember("category:{$category->slug}:filter-counts", 3600, function () use ($categoryId) {
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

        // Get category-level statistics
        $categoryStats = Cache::tags(['category', "category:{$categoryId}"])->remember("category:{$category->slug}:stats", 3600, function () use ($categoryId) {
            // Get security statistics from ports in this category
            // Use DB query builder to avoid BelongsToMany pivot column issues
            $securityStats = DB::table('ports')
                ->join('port_categories', 'ports.id', '=', 'port_categories.port_id')
                ->join('port_security', 'ports.port_number', '=', 'port_security.port_number')
                ->where('port_categories.category_id', $categoryId)
                ->selectRaw('
                    SUM(port_security.shodan_exposed_count) as total_exposures,
                    SUM(port_security.cve_count) as total_cves,
                    SUM(port_security.cve_critical_count) as total_critical_cves,
                    SUM(port_security.cve_high_count) as total_high_cves,
                    SUM(port_security.cve_medium_count) as total_medium_cves,
                    SUM(port_security.cve_low_count) as total_low_cves,
                    AVG(port_security.cve_avg_score) as avg_cvss_score,
                    MAX(port_security.shodan_exposed_count) as max_exposures,
                    MAX(port_security.cve_count) as max_cves
                ')
                ->first();

            // Get port with most exposures
            $mostExposedPort = DB::table('ports')
                ->join('port_categories', 'ports.id', '=', 'port_categories.port_id')
                ->join('port_security', 'ports.port_number', '=', 'port_security.port_number')
                ->where('port_categories.category_id', $categoryId)
                ->orderBy('port_security.shodan_exposed_count', 'desc')
                ->select('ports.port_number', 'ports.service_name', 'port_security.shodan_exposed_count')
                ->first();

            // Get port with most CVEs
            $mostVulnerablePort = DB::table('ports')
                ->join('port_categories', 'ports.id', '=', 'port_categories.port_id')
                ->join('port_security', 'ports.port_number', '=', 'port_security.port_number')
                ->where('port_categories.category_id', $categoryId)
                ->orderBy('port_security.cve_count', 'desc')
                ->select('ports.port_number', 'ports.service_name', 'port_security.cve_count')
                ->first();

            return [
                'total_exposures' => $securityStats->total_exposures ?? 0,
                'total_cves' => $securityStats->total_cves ?? 0,
                'total_critical_cves' => $securityStats->total_critical_cves ?? 0,
                'total_high_cves' => $securityStats->total_high_cves ?? 0,
                'total_medium_cves' => $securityStats->total_medium_cves ?? 0,
                'total_low_cves' => $securityStats->total_low_cves ?? 0,
                'avg_cvss_score' => isset($securityStats->avg_cvss_score)
                    ? round((float) $securityStats->avg_cvss_score, 1)
                    : null,
                'most_exposed_port' => $mostExposedPort,
                'most_vulnerable_port' => $mostVulnerablePort,
            ];
        });

        return view('ports.category', [
            'category' => $category,
            'ports' => $ports,
            'filterCounts' => $filterCounts,
            'categoryStats' => $categoryStats,
            'currentFilters' => [
                'protocol' => $protocol,
                'risk_level' => $riskLevel,
                'cve_severity' => $cveSeverity,
                'min_exposures' => $minExposures,
                'sort' => $sort,
            ],
        ]);
    }
}
