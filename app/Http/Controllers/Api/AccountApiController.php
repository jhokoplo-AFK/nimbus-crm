<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'accounts.view');

        $accounts = Account::query()
            ->search($request->string('search')->value())
            ->withIndustry($request->string('industry')->value() ?: null)
            ->withCount(['contacts', 'opportunities', 'contracts'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'accounts.manage');

        $account = Account::create($request->validate($this->rules()));

        return response()->json($account, 201);
    }

    public function show(Request $request, Account $account): JsonResponse
    {
        $this->guardPermission($request, 'accounts.view');

        return response()->json($account->load(['contacts', 'opportunities'])->loadCount('contracts'));
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $this->guardPermission($request, 'accounts.manage');

        $account->update($request->validate($this->rules(true)));

        return response()->json($account->refresh());
    }

    public function destroy(Request $request, Account $account): Response
    {
        $this->guardPermission($request, 'accounts.manage');

        $account->delete();

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
            'industry' => ['nullable', 'string', 'max:255'],
            'annual_revenue' => ['nullable', 'numeric', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'balance_due' => ['nullable', 'numeric', 'min:0'],
            'overdue_balance' => ['nullable', 'numeric', 'min:0'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
