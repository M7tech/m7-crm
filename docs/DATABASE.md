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
| `business_card_scans` | Tenant | Short-lived private card images and reviewed multilingual extraction state for the server fallback |
| `pipelines` | Tenant | Named sales processes, including the default pipeline |
| `pipeline_stages` | Tenant | Ordered open, won, and lost stages within a pipeline |
| `leads` | Tenant | Sales opportunities linked to companies, contacts, owners, and stages |
| `lead_activities` | Tenant | Immutable audit trail for lead creation, edits, and stage movement |
| `tasks` | Tenant | Assigned follow-ups with due times, priorities, reminders, and completion state |
| `task_activities` | Tenant | Immutable audit trail for task creation, edits, completion, and reopening |
| `integrations` | Tenant | Encrypted provider credentials, external account details, and CRM routing configuration |
| `webhook_events` | Tenant | Idempotent provider event ledger, processing status, and retained payload metadata |
| `conversations` | Tenant | Channel threads linked to an integration, CRM company, and optional contact |
| `messages` | Tenant | Inbound and outbound channel messages with provider delivery state and retained payload metadata |
| `automation_rules` | Tenant | Active or paused lead-stage rules that create follow-up tasks |
| `automation_runs` | Tenant | Idempotent execution audit linking a rule and lead activity to its created task |
| `system_health_checks` | Platform | Latest scheduler and queue-worker heartbeats for operational monitoring |
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

Default business-card scanning happens on the user's device. It creates no `business_card_scans` record and uploads no image or raw OCR. Reviewed fields are submitted to a scanner-specific contact endpoint, which authorizes creation, validates company ownership, and derives the tenant on the server. The user can create a new CRM client company together with its first contact in one transaction; the tenant is locked while plan capacity is checked. A CRM client company remains a tenant-owned customer record and receives no user account or tenant workspace. Browser state is discarded after saving or leaving the scanner; only language models are cached locally.

The optional server fallback uploads cards to tenant-partitioned private storage and processes them in a queued worker. Tesseract and ImageMagick run inside that worker. Deterministic parsing proposes contact fields for human review; OCR output is never written directly to `contacts`. The final save validates the selected company and every contact field again, then creates the contact and clears the extracted result in one transaction. Card images are deleted immediately after a successful save, while every unsaved scan and its private image expires after 24 hours. App, worker, and scheduler containers share only the private storage volume required for this fallback workflow. Existing scans and their cleanup remain supported.

Every tenant receives a default sales pipeline with New, Qualified, Proposal, Won, and Lost stages. Additional pipelines can define one to ten ordered open stages; terminal Won and Lost stages are created automatically. Lead validation requires the company, optional contact, pipeline, stage, and optional assignee to belong to the active tenant. It also verifies that a contact belongs to the selected company and a stage belongs to the selected pipeline.

Lead expected values are stored in each currency's smallest unit: cents for USD and fils for IQD. Moving a lead to Won or Lost sets `closed_at`; Lost also requires a reason. Reopening a lead clears both outcome fields. Each workflow writes an append-only `lead_activities` record in the same database transaction.

Tasks may link to a lead and are assigned to an active user in the same tenant. Due and reminder inputs are interpreted in the tenant timezone and stored in UTC. Salespeople see tasks assigned to or created by them; company admins and sales managers see their tenant's team tasks. Completion and reopening write append-only `task_activities` records. The scheduler claims due reminders once using `reminder_sent_at`, then queues email delivery for the worker.

Meta Lead Ads credentials and access tokens are stored using Laravel's encrypted cast. Each integration maps one Facebook Page to an independently editable company, pipeline, stage, and owner destination; provider credentials are never rendered back into a form. Connections in the same tenant that use the same Meta App ID share one random webhook UUID and verification token because Meta permits one Page-object callback per app. Signed webhook entries are routed to the matching integration by Page ID before tenant-owned events are written. Resolving the webhook UUID is the documented global-scope exception for incoming provider webhooks; after resolution, the request sets the integration's tenant before any tenant-owned event query or write.

`webhook_events` has a unique integration, event-type, and external-ID key. Incoming requests create the ledger record and enqueue processing in one transaction. The worker locks the ledger row before creating a contact, lead, and immutable lead activity, so webhook retries do not create duplicate opportunities.

Facebook Messenger events reuse the signed Meta Page webhook. A valid message ID is recorded in `webhook_events` before queue dispatch, and the worker creates or finds a conversation using the integration, channel, and Page-scoped participant ID. Outbound replies are written with a queued status in the same transaction that dispatches delivery; the queue worker uses the encrypted Page token and records Meta's returned message ID. Both `conversations` and `messages` carry `tenant_id` directly and use the normal fail-closed tenant scope.

Company administrators can queue a historical Messenger import for an active Page connection. The importer traverses Meta conversation and message cursors in bounded queue jobs, resolves each thread to its Page-scoped participant, and writes inbound and outbound messages transactionally. The existing unique conversation/message provider keys make repeated imports idempotent. Meta remains the authority on which historical conversations and message details are available.

Automation rules are managed by company administrators and sales managers. The first bounded rule type listens to immutable lead creation and stage-change activities and queues a follow-up task when the lead enters the configured stage. A unique automation-rule and lead-activity pair makes job retries idempotent. Task creation, task activity creation, and successful run completion share one transaction; failed executions retain a bounded error for operational review. Deleting a rule is soft deletion so its historical runs remain attributable.

The tenant `status` is enforced on every tenant workspace request; suspended tenants cannot access CRM data. The tenant `plan` resolves through `config/plans.php`, which centrally defines creation limits for active team seats (including pending invitations), customer companies, automation rules, and Meta Page connections. Limit checks always calculate usage for the authenticated tenant, never from request input. Reaching a limit blocks only additional creation and never removes or hides existing customer data. Company administrators see the same plan usage plus a five-step onboarding checklist on the dashboard.

`system_health_checks` is a platform-owned exception to tenant scoping. The scheduler updates its heartbeat every minute and dispatches a second queued heartbeat; freshness of both records distinguishes scheduler failure from queue-worker failure. Only explicit super admins can view the operations dashboard, which combines these heartbeats with database and Redis probes, queue depth, and bounded summaries of failed jobs, webhooks, and automation runs. Provider payloads and credentials are never displayed.

## Planned tables

The next milestones will add broader `activities` and `notes`. Each tenant-owned table will carry `tenant_id` directly, including child records, so isolation does not depend on multi-table joins.
