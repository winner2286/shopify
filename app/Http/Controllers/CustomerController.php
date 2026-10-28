<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * E-04 : Fichier clients de la boutique
     */
    public function index(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        $query = Customer::withCount('orders')->latest('last_order_at');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(20)->withQueryString();
        $totalCustomers = Customer::count();

        return view('customers.index', compact('shop', 'customers', 'totalCustomers', 'search'));
    }
}
