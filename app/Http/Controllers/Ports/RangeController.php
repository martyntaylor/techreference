<?php

namespace App\Http\Controllers\Ports;

use App\Http\Controllers\Controller;
use App\Http\Requests\RangeRequest;
use App\Models\Port;
use Illuminate\View\View;

class RangeController extends Controller
{
    /**
     * Display ports in the specified range.
     */
    public function show(RangeRequest $request, int $start, int $end): View
    {
        // Ensure validation is executed and silence static analyzers
        $request->validated();

        // Query ports in range
        $ports = Port::whereBetween('port_number', [$start, $end])
            ->with(['security', 'categories'])
            ->orderBy('port_number')
            ->paginate(100); // 100 ports per page

        // Calculate range statistics
        $stats = [
            'total_in_range' => Port::whereBetween('port_number', [$start, $end])->count(),
            'high_risk' => Port::whereBetween('port_number', [$start, $end])->byRisk('High')->count(),
            'medium_risk' => Port::whereBetween('port_number', [$start, $end])->byRisk('Medium')->count(),
            'low_risk' => Port::whereBetween('port_number', [$start, $end])->byRisk('Low')->count(),
        ];

        return view('ports.range', [
            'start' => $start,
            'end' => $end,
            'ports' => $ports,
            'stats' => $stats,
        ]);
    }
}
