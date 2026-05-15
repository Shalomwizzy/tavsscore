<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StatsController;
use Illuminate\View\View;

class StatsAdminController extends Controller
{
    public function index(): View
    {
        // Reuse the same data-building logic as the public stats page
        // but render inside the admin layout
        $public = app(StatsController::class);
        $view   = $public->index();

        // Extract the view data and render with admin layout
        $data = $view->getData();

        return view('admin.stats.index', $data);
    }
}
