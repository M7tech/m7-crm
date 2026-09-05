<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

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

    public function store(StoreContactRequest $request): RedirectResponse|JsonResponse
    {
        $contact = Contact::create($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('contacts.show', $contact)], 201);
        }

        return to_route('contacts.show', $contact)->with('status', 'Contact added successfully.');
    }

    public function show(int $contact): View
    {
        $contactModel = Contact::query()->with('company')->findOrFail($contact);
        $this->authorize('view', $contactModel);

        return view('contacts.show', ['contact' => $contactModel]);
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
