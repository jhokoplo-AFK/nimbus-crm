<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BrandApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'products.view');

        $brands = Brand::query()
            ->withCount('products')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json($brands);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'products.manage');

        $brand = Brand::create($request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')],
        ]));

        return response()->json($brand, 201);
    }

    public function show(Request $request, Brand $brand): JsonResponse
    {
        $this->guardPermission($request, 'products.view');

        return response()->json($brand->load('products:id,brand_id,sku,name,is_active'));
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $this->guardPermission($request, 'products.manage');

        $brand->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brand)],
        ]));

        return response()->json($brand->refresh());
    }

    public function destroy(Request $request, Brand $brand): Response
    {
        $this->guardPermission($request, 'products.manage');

        $brand->delete();

        return response()->noContent();
    }
}
