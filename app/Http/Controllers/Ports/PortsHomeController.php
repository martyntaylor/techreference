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

        // Get top 5 ports for each category
        $categoriesWithPorts = Cache::remember('ports:home:top-ports', 3600, function () use ($categories) {
            return $categories->map(function ($category) {
                $topPorts = $category->ports()
                    ->orderBy('view_count', 'desc')
                    ->limit(5)
                    ->get();

                return [
                    'category' => $category,
                    'topPorts' => $topPorts,
                ];
            });
        });

        // Get overall most popular ports
        $popularPorts = Cache::remember('ports:home:popular', 3600, function () {
            return Port::orderBy('view_count', 'desc')
                ->limit(10)
                ->get();
        });

        return view('ports.index', [
            'categories' => $categoriesWithPorts,
            'popularPorts' => $popularPorts,
        ]);
    }
}
