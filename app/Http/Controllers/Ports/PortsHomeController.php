<?php

namespace App\Http\Controllers\Ports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Port;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PortsHomeController extends Controller
{
    /**
     * Display the ports landing page.
     */
    public function index(): View
    {
        // Get all active categories with port counts
        $categories = Cache::remember('ports:home:categories', 3600, function () {
            return Category::where('is_active', true)
                ->withCount('ports')
                ->orderBy('display_order')
                ->orderBy('name')
                ->get();
        });

        // Get top 5 ports for each category (grouped by port number to avoid protocol duplicates)
        $categoriesWithPorts = Cache::remember('ports:home:top-ports', 3600, function () use ($categories) {
            return $categories->map(function ($category) {
                // Get top port numbers by joining with port_pages for view counts
                $topPortNumbers = $category->ports()
                    ->join('port_pages', 'ports.port_number', '=', 'port_pages.port_number')
                    ->select('ports.port_number', 'port_pages.view_count')
                    ->groupBy('ports.port_number', 'port_pages.view_count')
                    ->orderBy('port_pages.view_count', 'DESC')
                    ->limit(5)
                    ->pluck('ports.port_number');

                // Fetch all protocol variants for each port number and merge them
                $topPorts = collect($topPortNumbers)->map(function ($portNumber) {
                    return $this->mergePortProtocols($portNumber);
                })->filter();

                return [
                    'category' => $category,
                    'topPorts' => $topPorts,
                ];
            });
        });

        // Get overall most popular ports (grouped by port number, using port_pages view counts)
        $popularPorts = Cache::remember('ports:home:popular', 3600, function () {
            $topPortNumbers = Port::join('port_pages', 'ports.port_number', '=', 'port_pages.port_number')
                ->select('ports.port_number', 'port_pages.view_count')
                ->groupBy('ports.port_number', 'port_pages.view_count')
                ->orderBy('port_pages.view_count', 'DESC')
                ->limit(10)
                ->pluck('ports.port_number');

            // Fetch all protocol variants for each port number and merge them
            return collect($topPortNumbers)->map(function ($portNumber) {
                return $this->mergePortProtocols($portNumber);
            })->filter();
        });

        return view('ports.index', [
            'categories' => $categoriesWithPorts,
            'popularPorts' => $popularPorts,
        ]);
    }

    /**
     * Merge all protocol variants for a given port number into a single port object.
     * Similar to how the individual port page groups protocols together.
     */
    private function mergePortProtocols(int $portNumber): ?Port
    {
        $ports = Port::with(['categories', 'security'])
            ->where('port_number', $portNumber)
            ->orderBy('protocol')
            ->get();

        if ($ports->isEmpty()) {
            return null;
        }

        // Use the first port as base and merge protocols
        $primaryPort = $ports->first();
        $primaryPort->setAttribute(
            'protocols',
            $ports->pluck('protocol')
                ->map(fn ($p) => strtoupper($p))
                ->unique()
                ->values()
                ->all()
        );

        // If there's security data, use the one with the highest risk
        if ($ports->count() > 1) {
            $securityData = $ports->map(fn($p) => $p->security)->filter();
            if ($securityData->isNotEmpty()) {
                $primaryPort->setRelation('security', $securityData->sortByDesc('shodan_exposed_count')->first());
            }
        }

        return $primaryPort;
    }
}
