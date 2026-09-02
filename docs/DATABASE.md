# Database design

Production uses PostgreSQL. SQLite is retained only for fast local development and automated tests.

## Current schema

| Table | Ownership | Purpose |
|---|---|---|
| `tenants` | Platform | SaaS customer companies and workspace settings |
| `users` | Tenant or platform | Authenticated people and their role; super admins have no tenant |
| `companies` | Tenant | Customer business accounts inside the CRM |
| `sessions` | User | Web sessions |
| `password_reset_tokens` | User | Password recovery |
| `passkeys` | User | WebAuthn credentials |
| `cache`, `jobs`, `job_batches`, `failed_jobs` | Platform | Framework cache and background work |

## Tenant enforcement

`ResolveTenant` obtains the authenticated user's tenant and places it in the request-scoped `CurrentTenant` service. Models using `BelongsToTenant` receive `TenantScope`, which filters all ordinary queries and automatically assigns `tenant_id` when creating records. If no tenant or explicit super-admin context exists, the query returns no rows.

This application layer must be backed by tests for every tenant-owned module. PostgreSQL row-level security may be added later as defense in depth, but it does not replace application authorization.

## Planned tables

The next milestones will add `contacts`, `invitations`, `pipelines`, `pipeline_stages`, `leads`, `tasks`, `activities`, `notes`, `conversations`, `messages`, `integrations`, `webhook_events`, and `automation_runs`. Each tenant-owned table will carry `tenant_id` directly, including child records, so isolation does not depend on multi-table joins.
