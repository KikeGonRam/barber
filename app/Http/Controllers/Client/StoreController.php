<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $categoria = trim((string) $request->query('categoria', ''));

        $products = Product::query()
            ->availableForSale()
            ->when($search !== '', fn ($q) => $q->where('nombre', 'like', "%{$search}%"))
            ->when($categoria !== '', fn ($q) => $q->where('categoria', $categoria))
            ->orderBy('nombre')
            ->get();

        $categorias = Product::query()
            ->availableForSale()
            ->get(['categoria'])
            ->pluck('categoria')
            ->filter()
            ->unique()
            ->values();

        return view('client.store.index', compact('products', 'categorias', 'search', 'categoria'));
    }
}
