# Database design

Production uses PostgreSQL. SQLite is retained only for fast local development and automated tests.

## Current schema

| Table | Ownership | Purpose |
|---|---|---|
| `tenants` | Platform | SaaS customer companies and workspace settings |
| `users` | Tenant or platform | Authenticated people and their role; super admins have no tenant |
| `companies` | Tenant | Customer business accounts inside the CRM |
| `contacts` | Tenant | People linked to CRM customer companies |
| `invitations` | Tenant | Expiring, single-use invitations for workspace team members |
| `contact_imports` | Tenant | Audit summaries for previewed and completed contact CSV imports |
| `sessions` | User | Web sessions |
| `password_reset_tokens` | User | Password recovery |
| `passkeys` | User | WebAuthn credentials |
| `cache`, `jobs`, `job_batches`, `failed_jobs` | Platform | Framework cache and background work |

## Tenant enforcement

`ResolveTenant` obtains the authenticated user's tenant and places it in the request-scoped `CurrentTenant` service. Models using `BelongsToTenant` receive `TenantScope`, which filters all ordinary queries and automatically assigns `tenant_id` when creating records. If no tenant or explicit super-admin context exists, the query returns no rows.

This application layer must be backed by tests for every tenant-owned module. PostgreSQL row-level security may be added later as defense in depth, but it does not replace application authorization.

The `contacts` table carries both `tenant_id` and `company_id`. Contact form validation requires the selected company to belong to the authenticated tenant, so a cross-tenant company ID cannot create an invalid association.

The `invitations` table stores only a SHA-256 hash of each 384-bit random acceptance token. Invitations expire after seven days and record acceptance. Guest token acceptance is the documented exception to ordinary tenant-scoped lookup: the high-entropy token authorizes only its matching unexpired invitation, and user creation plus acceptance are committed in one transaction.

The `contact_imports` table records the source filename, importer, selected duplicate strategy, row counts, validation failures, and completion time. Preview rows are retained in tenant/user-bound cache for 30 minutes and are never stored in the audit table. Import execution locks the audit record and writes contacts in one transaction to prevent replay or partial imports.

## Planned tables

The next milestones will add `pipelines`, `pipeline_stages`, `leads`, `tasks`, `activities`, `notes`, `conversations`, `messages`, `integrations`, `webhook_events`, and `automation_runs`. Each tenant-owned table will carry `tenant_id` directly, including child records, so isolation does not depend on multi-table joins.
