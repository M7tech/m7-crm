<x-layouts::public title="Privacy Policy">
    <article class="space-y-8">
        <header class="space-y-3">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400">Legal</p>
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Privacy Policy</h1>
            <p class="text-zinc-600 dark:text-zinc-400">Effective {{ config('legal.effective_date') }}</p>
            <p class="max-w-3xl text-lg leading-8 text-zinc-700 dark:text-zinc-300">This policy explains how {{ config('legal.operator_name') }} collects, uses, stores, and deletes information when businesses use {{ config('app.name') }}.</p>
        </header>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Who operates the service</h2>
            <p>{{ config('legal.operator_name') }} operates {{ config('app.name') }}. Privacy and data requests can be sent to <a href="mailto:{{ config('legal.contact_email') }}" class="text-emerald-700 underline dark:text-emerald-400">{{ config('legal.contact_email') }}</a>.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Information we process</h2>
            <ul class="list-disc space-y-2 ps-6 leading-7">
                <li><strong>Account and workspace data:</strong> names, email addresses, authentication data, roles, tenant name, plan, and security settings.</li>
                <li><strong>CRM data:</strong> companies, contacts, leads, tasks, notes, assignments, pipeline activity, imports, and other information users choose to enter.</li>
                <li><strong>Meta integration data:</strong> connected Page identifiers and names, encrypted access credentials, lead-form submissions, Page-scoped participant identifiers, Messenger text messages, public Facebook comments and replies, delivery status, and webhook audit information.</li>
                <li><strong>Technical data:</strong> sessions, timestamps, operational logs, queue and service health, and security events needed to run and protect the service.</li>
                <li><strong>Support requests:</strong> the name, email, company, topic, plan interest, and message a visitor chooses to submit through the contact form.</li>
                <li><strong>Business-card scans:</strong> the default scanner processes card photos and raw recognized text in the user's browser. Only fields the user reviews are submitted. The optional server scanner temporarily stores a private image and deletes it after saving or after 24 hours if abandoned.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">How we use information</h2>
            <p>We use information to provide and secure the CRM, authenticate users, route leads and messages to the selected customer account, send authorized replies and reminders, enforce plan limits, diagnose failures, prevent duplicate webhook processing, and respond to support or deletion requests. We do not sell CRM or Meta Platform data.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Meta and other service providers</h2>
            <p>When a workspace administrator connects Meta, the service communicates with Meta's APIs at that administrator's direction. Meta separately processes information under its own terms and privacy policy. We may use hosting, database, email-delivery, monitoring, and security providers that process information only as needed to operate the service.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Storage, security, and tenant separation</h2>
            <p>Each customer workspace is logically separated. The server derives workspace ownership rather than accepting it from browser input. Integration credentials and access tokens are encrypted at rest. Access is limited by authenticated roles, and external webhook deliveries are verified and processed idempotently. No internet service can guarantee absolute security.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Retention and deletion</h2>
            <p>Workspace and CRM records are retained while needed to provide the service and meet legitimate security, audit, or legal obligations. Deleting a Meta connection removes its stored credentials, webhook history, and linked conversation records from the active database through database relationships. Removing access in Meta stops future access but does not by itself delete information already received by the CRM.</p>
            <p>To request access, correction, export, or deletion, follow the <a href="{{ route('legal.data-deletion') }}" class="text-emerald-700 underline dark:text-emerald-400">data deletion instructions</a>. We may need to verify identity and workspace authority before acting.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Children</h2>
            <p>{{ config('app.name') }} is a business service and is not directed to children.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Policy changes</h2>
            <p>We may update this policy when the product or legal requirements change. The effective date at the top identifies the current version.</p>
        </section>
    </article>
</x-layouts::public>
