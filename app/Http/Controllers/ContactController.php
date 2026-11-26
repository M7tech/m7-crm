<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Task;
use App\Support\CurrentTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Contact::class);

        return view('contacts.index', [
            'contacts' => Contact::query()->with('company')->latest()->paginate(20),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $contact = Contact::create($request->validated());

        return to_route('contacts.show', $contact)->with('status', 'Contact added successfully.');
    }

    public function show(Request $request, int $contact, CurrentTenant $currentTenant): View
    {
        $contactModel = Contact::query()->with('company')->findOrFail($contact);
        $this->authorize('view', $contactModel);
        $user = $request->user();

        return view('contacts.show', [
            'contact' => $contactModel,
            'conversations' => $user->can('viewAny', Conversation::class)
                ? Conversation::query()
                    ->with(['assignedTo:id,name', 'integration:id,tenant_id,external_account_name'])
                    ->where('contact_id', $contactModel->id)
                    ->latest('last_message_at')
                    ->limit(20)
                    ->get()
                : collect(),
            'tasks' => Task::query()
                ->visibleTo($user)
                ->with('assignedTo:id,name')
                ->where('contact_id', $contactModel->id)
                ->orderByRaw("case when status = 'completed' then 1 else 0 end")
                ->orderBy('due_at')
                ->limit(20)
                ->get(),
            'timezone' => $currentTenant->tenant()->timezone ?? 'Asia/Baghdad',
        ]);
    }

    public function edit(int $contact): View
    {
        $contactModel = Contact::query()->findOrFail($contact);
        $this->authorize('update', $contactModel);

        return view('contacts.edit', [
            'contact' => $contactModel,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateContactRequest $request, int $contact): RedirectResponse
    {
        $contactModel = Contact::query()->findOrFail($contact);
        $contactModel->update($request->validated());

        return to_route('contacts.show', $contactModel)->with('status', 'Contact updated successfully.');
    }

    public function destroy(int $contact): RedirectResponse
    {
        $contactModel = Contact::query()->findOrFail($contact);
        $this->authorize('delete', $contactModel);
        $contactModel->delete();

        return to_route('contacts.index')->with('status', 'Contact deleted successfully.');
    }
}
