<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesApiRequests;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContactApiController extends Controller
{
    use AuthorizesApiRequests;

    public function index(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'contacts.view');

        $contacts = Contact::query()
            ->search($request->string('search')->value())
            ->when($request->filled('account_id'), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->with('account:id,name')
            ->orderByDesc('is_primary')
            ->orderBy('last_name')
            ->paginate($this->perPage($request));

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardPermission($request, 'contacts.manage');

        $contact = Contact::create($request->validate($this->rules()));

        if ($request->boolean('is_primary')) {
            $contact->makePrimary();
        }

        return response()->json($contact->refresh()->load('account:id,name'), 201);
    }

    public function show(Request $request, Contact $contact): JsonResponse
    {
        $this->guardPermission($request, 'contacts.view');

        return response()->json($contact->load(['account', 'opportunities']));
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $this->guardPermission($request, 'contacts.manage');

        $contact->update($request->validate($this->rules(true)));

        if ($request->boolean('is_primary')) {
            $contact->makePrimary();
        }

        return response()->json($contact->refresh()->load('account:id,name'));
    }

    public function destroy(Request $request, Contact $contact): Response
    {
        $this->guardPermission($request, 'contacts.manage');

        $contact->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'account_id' => [$presence, 'integer', 'exists:accounts,id'],
            'first_name' => [$presence, 'string', 'max:100'],
            'last_name' => [$presence, 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
