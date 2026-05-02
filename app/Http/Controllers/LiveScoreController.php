<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LiveScoreController extends Controller
{
    public function index(): View
    {
        return view('live.index');
    }
}
