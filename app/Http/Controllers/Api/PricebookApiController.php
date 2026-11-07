<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\Pricebook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PricebookApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'products.view');

        $pricebooks = Pricebook::query()
            ->withCount('entries')
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderByDesc('is_standard')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json($pricebooks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'products.manage');

        $pricebook = Pricebook::create($request->validate($this->rules()));

        return response()->json($pricebook, 201);
    }

    public function show(Request $request, Pricebook $pricebook): JsonResponse
    {
        $this->guardPermission($request, 'products.view');

        return response()->json($pricebook->load('entries.product:id,sku,name,brand_id'));
    }

    public function update(Request $request, Pricebook $pricebook): JsonResponse
    {
        $this->guardPermission($request, 'products.manage');

        $pricebook->update($request->validate($this->rules(true)));

        return response()->json($pricebook->refresh());
    }

    public function destroy(Request $request, Pricebook $pricebook): Response
    {
        $this->guardPermission($request, 'products.manage');

        $pricebook->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'is_standard' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
