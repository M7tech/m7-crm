<h1>New {{ config('app.name') }} contact request</h1>

<p><strong>Name:</strong> {{ $details['name'] }}</p>
<p><strong>Email:</strong> {{ $details['email'] }}</p>
<p><strong>Company:</strong> {{ filled($details['company'] ?? null) ? $details['company'] : 'Not provided' }}</p>
<p><strong>Topic:</strong> {{ ucfirst($details['topic']) }}</p>
<p><strong>Plan:</strong> {{ filled($details['plan'] ?? null) ? config('plans.plans.'.$details['plan'].'.label', $details['plan']) : 'Not specified' }}</p>

<h2>Message</h2>
<p>{!! nl2br(e($details['message'])) !!}</p>

<p><small>Reply directly to this email to answer the visitor.</small></p>
