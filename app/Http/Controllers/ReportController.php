<?php

namespace App\Http\Controllers;

use App\Services\SalesReport;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $currentTenant, SalesReport $report): View
    {
        Gate::authorize('viewReports');
        $period = in_array($request->query('period'), ['30', '90', '365', 'all'], true)
            ? (string) $request->query('period')
            : '30';
        $timezone = $currentTenant->tenant()?->timezone ?? 'Asia/Baghdad';
        $start = $period === 'all'
            ? null
            : CarbonImmutable::now($timezone)->subDays((int) $period - 1)->startOfDay()->utc();

        return view('reports.index', [
            ...$report->build($start, $request->user()),
            'period' => $period,
            'periodLabel' => $period === 'all' ? 'All time' : 'Last '.$period.' days',
        ]);
    }
}
