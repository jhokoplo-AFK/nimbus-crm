<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProductApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'products.view');

        $products = Product::query()
            ->search($request->string('search')->value())
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->integer('brand_id')))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->with(['brand:id,name', 'pricebookEntries.pricebook:id,name'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'products.manage');

        $product = Product::create($request->validate($this->rules()));

        return response()->json($product->load('brand:id,name'), 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->guardPermission($request, 'products.view');

        return response()->json($product->load(['brand:id,name', 'pricebookEntries.pricebook:id,name']));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->guardPermission($request, 'products.manage');

        $product->update($request->validate($this->rules($product)));

        return response()->json($product->refresh()->load('brand:id,name'));
    }

    public function destroy(Request $request, Product $product): Response
    {
        $this->guardPermission($request, 'products.manage');

        $product->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Product $product = null): array
    {
        $presence = $product === null ? 'required' : 'sometimes';

        return [
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'sku' => [$presence, 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product)],
            'name' => [$presence, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
