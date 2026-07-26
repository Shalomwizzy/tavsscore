<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GoalscorerPicksController;
use Illuminate\View\View;

class GoalscorerPicksAdminController extends Controller
{
    public function index(GoalscorerPicksController $public): View
    {
        // Reuse the public pick-building logic, render inside the admin layout.
        return view('admin.goalscorer-picks.index', $public->buildPicks(40));
    }
}
