<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\PricebookEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PricebookEntryApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'products.view');

        $entries = PricebookEntry::query()
            ->when($request->filled('pricebook_id'), fn ($query) => $query->where('pricebook_id', $request->integer('pricebook_id')))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->with(['pricebook:id,name', 'product:id,sku,name'])
            ->paginate($this->perPage($request));

        return response()->json($entries);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'products.manage');

        $entry = PricebookEntry::create($request->validate([
            'pricebook_id' => ['required', 'integer', 'exists:pricebooks,id'],
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                Rule::unique('pricebook_entries')->where('pricebook_id', $request->integer('pricebook_id')),
            ],
            'list_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ]));

        return response()->json($entry->load(['pricebook:id,name', 'product:id,sku,name']), 201);
    }

    public function show(Request $request, PricebookEntry $pricebookEntry): JsonResponse
    {
        $this->guardPermission($request, 'products.view');

        return response()->json($pricebookEntry->load(['pricebook:id,name', 'product:id,sku,name']));
    }

    public function update(Request $request, PricebookEntry $pricebookEntry): JsonResponse
    {
        $this->guardPermission($request, 'products.manage');

        $pricebookEntry->update($request->validate([
            'list_price' => ['sometimes', 'numeric', 'min:0', 'max:999999999.99'],
        ]));

        return response()->json($pricebookEntry->refresh()->load(['pricebook:id,name', 'product:id,sku,name']));
    }

    public function destroy(Request $request, PricebookEntry $pricebookEntry): Response
    {
        $this->guardPermission($request, 'products.manage');

        $pricebookEntry->delete();

        return response()->noContent();
    }
}
