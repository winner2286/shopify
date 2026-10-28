<?php

namespace App\Http\Controllers;

use App\Models\Plan;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home', ['plans' => Plan::where('active', true)->orderBy('max_products')->get()]);
    }
}
