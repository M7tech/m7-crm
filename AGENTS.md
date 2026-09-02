# M7 CRM development rules

Read `docs/CRM-SPEC.md`, `docs/DATABASE.md`, and this file before changing the project.

## Architecture

- Laravel 13, PHP 8.3+, Livewire 4, PostgreSQL, Redis, and Tailwind CSS.
- One application and one database serve many customer companies.
- `tenants` are SaaS customers. `companies` are CRM customer accounts owned by a tenant.
- Every tenant-owned table must include a non-null `tenant_id`, a foreign key, and tenant-aware indexes.
- Every tenant-owned model must use `App\Models\Concerns\BelongsToTenant` unless a documented exception is approved.
- Never accept `tenant_id` from request input. Resolve it from the authenticated user's workspace.
- Super-admin access is explicit through `UserRole::SuperAdmin`; do not bypass tenant scopes ad hoc.

## Security and quality

- Add policies or gates for every write operation.
- Validate input with Form Requests or Livewire validation.
- Use database transactions for workflows that create or update multiple records.
- Queue external API and webhook processing. Webhook handlers must be idempotent.
- Never commit secrets, `.env`, database dumps, customer data, tokens, or credentials.
- Do not add a package unless the standard framework cannot reasonably solve the requirement.
- Add tests for tenant isolation, authorization, validation, and the main successful path.
- Before handing off: run `composer test` and `npm run build`.

## Product boundaries

- Build one milestone at a time in the order documented in `docs/CRM-SPEC.md`.
- Do not add Meta, WhatsApp, AI, voice, ERP, billing, or broad automation until its milestone starts.
- Keep the interface responsive and ready for English, Arabic, and Kurdish. Do not hard-code layout assumptions that prevent RTL support.
- Update the specification and database documentation when a product or schema decision changes.
