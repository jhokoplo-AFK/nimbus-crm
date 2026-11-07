<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'users.manage');

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json($users);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->guardPermission($request, 'users.manage');

        return response()->json($user->load(['roles:id,name', 'ownedOpportunities:id,name,account_id,stage,amount']));
    }
}
