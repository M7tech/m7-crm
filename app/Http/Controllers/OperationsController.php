<?php

namespace App\Http\Controllers;

use App\Services\OperationsMonitor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class OperationsController extends Controller
{
    public function __invoke(OperationsMonitor $monitor): View
    {
        Gate::authorize('viewOperations');

        return view('operations.index', $monitor->snapshot());
    }
}
