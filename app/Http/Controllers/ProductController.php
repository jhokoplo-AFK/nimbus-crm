<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Pricebook;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Opportunity::class);

        $search = trim((string) $request->query('search', ''));

        $products = Product::query()
            ->search($search)
            ->with(['pricebookEntries.pricebook:id,name,is_active'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->getKey(),
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'is_active' => $product->is_active,
                'prices' => $product->pricebookEntries->map(fn ($entry): array => [
                    'pricebook' => $entry->pricebook?->name,
                    'is_active' => (bool) $entry->pricebook?->is_active,
                    'list_price' => (float) $entry->list_price,
                ])->values()->all(),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search],
            'pricebooks' => Pricebook::query()
                ->withCount('entries')
                ->orderByDesc('is_standard')
                ->orderBy('name')
                ->get()
                ->map(fn (Pricebook $pricebook): array => [
                    'id' => $pricebook->getKey(),
                    'name' => $pricebook->name,
                    'is_standard' => $pricebook->is_standard,
                    'is_active' => $pricebook->is_active,
                    'entries_count' => $pricebook->entries_count,
                ])
                ->all(),
        ]);
    }
}
