<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendPublicContactRequest;
use App\Mail\PublicContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PublicContactController extends Controller
{
    public function create(Request $request): View
    {
        $plan = $request->string('plan')->lower()->value();
        if (! array_key_exists($plan, config('plans.plans', []))) {
            $plan = null;
        }

        return view('contact', ['selectedPlan' => $plan]);
    }

    public function store(SendPublicContactRequest $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return to_route('contact.create')->with('status', 'Thank you. Your message was received.');
        }

        $details = $request->safe()->except('website');
        Mail::to(config('support.email'))->queue(new PublicContactMessage($details));

        return to_route('contact.create')->with('status', 'Thank you. Your message was sent to our team.');
    }
}
