<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Opportunity::class);

        $search = trim((string) $request->query('search', ''));

        $contacts = Contact::query()
            ->search($search)
            ->with('account:id,name')
            ->orderByDesc('is_primary')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Contact $contact): array => [
                'id' => $contact->getKey(),
                'name' => $contact->full_name,
                'title' => $contact->title,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_primary' => $contact->is_primary,
                'account' => $contact->account?->only(['id', 'name']),
            ]);

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => ['search' => $search],
        ]);
    }
}
