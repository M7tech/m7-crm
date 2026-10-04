<x-layouts::public title="User Data Deletion">
    <article class="space-y-8">
        <header class="space-y-3">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400">Privacy</p>
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">User Data Deletion</h1>
            <p class="max-w-3xl text-lg leading-8 text-zinc-700 dark:text-zinc-300">You can request deletion of account, CRM, Facebook Page, lead, or Messenger data processed by {{ config('app.name') }}.</p>
        </header>

        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950/30">
            <h2 class="text-xl font-semibold">Send a deletion request</h2>
            <ol class="mt-3 list-decimal space-y-2 ps-6 leading-7">
                <li>Email <a href="mailto:{{ config('legal.contact_email') }}?subject={{ rawurlencode(config('app.name').' data deletion request') }}" class="font-medium text-emerald-700 underline dark:text-emerald-400">{{ config('legal.contact_email') }}</a> with the subject “{{ config('app.name') }} data deletion request”.</li>
                <li>Include the email address used for the CRM, the workspace name, and what data you want deleted.</li>
                <li>For Meta data, include the Facebook Page name or Page ID and, if known, the CRM connection name. Do not send passwords, access tokens, or an App Secret.</li>
                <li>Complete any reasonable identity or workspace-authority verification we request. We will confirm the result or explain any information that must be retained for security, legal, or dispute purposes.</li>
            </ol>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Disconnecting Meta</h2>
            <p>A workspace administrator can delete the Page connection from <strong>Integrations</strong> in the CRM. You can also remove the app from Facebook or Meta Business Integrations. Disconnecting stops future API access, but a deletion request is still needed if you want previously received CRM records removed.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">What deletion covers</h2>
            <p>Depending on the verified request, deletion can cover the user's account, imported or manually entered CRM records, stored Meta credentials, webhook records, conversations, and messages. Deleting an entire business workspace affects every authorized user and customer record in it, so we require confirmation from an authorized workspace administrator.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Business-card photos</h2>
            <p>Photos processed by the default on-device scanner are not uploaded. The optional server scanner deletes its private image immediately after a successful save, or automatically after 24 hours if the scan is abandoned.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Need help?</h2>
            <p>Contact <a href="mailto:{{ config('legal.contact_email') }}" class="text-emerald-700 underline dark:text-emerald-400">{{ config('legal.contact_email') }}</a>. See the <a href="{{ route('legal.privacy') }}" class="text-emerald-700 underline dark:text-emerald-400">Privacy Policy</a> for more information about processing and retention.</p>
        </section>
    </article>
</x-layouts::public>
