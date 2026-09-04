# M7 CRM product specification

Status: milestone 0.9 sellable-release controls complete; public launch preparation is next and additional messaging work remains deferred until Meta publishing

M7 CRM is a multi-company SaaS CRM for Iraqi sales teams. It will bring customer accounts, leads, follow-ups, social conversations, and later ERP context into one workspace. “M7 CRM” is the working name and can be changed without changing the architecture.

## Users and roles

| Role | Scope | Initial responsibility |
|---|---|---|
| Super admin | All tenants | Operate the SaaS platform |
| Company admin | One tenant | Manage its workspace and team |
| Sales manager | One tenant | Manage sales activity and reporting |
| Salesperson | One tenant | Work assigned companies, leads, and tasks |

## Core security invariant

Customer data from one tenant must never be visible, editable, searchable, exportable, or inferable by another tenant. Tenant-owned records fail closed if no tenant is resolved. The server derives `tenant_id`; browsers and API clients never choose it.

## Delivery roadmap

### 0.1 — Foundation (complete)

- Email/password authentication, email verification, passkeys, and two-factor support
- SaaS company registration
- Tenant status and subscription-plan fields
- Four user roles
- Fail-closed tenant model scope and request middleware
- CRM companies: list and create
- Responsive dashboard
- Tenant isolation and authorization tests
- Coolify-ready container configuration

### 0.2 — Contacts and team administration (complete)

- Contacts linked to CRM companies, with tenant-isolated create, view, edit, and delete workflows (complete)
- Company-admin user invitations with expiring single-use links (complete)
- User activation/deactivation and role changes (complete)
- CSV import with preview, validation, duplicate handling, and audit record (complete)
- Private business-card scanning with queued Arabic, English, Kurdish Sorani, and Kurdish Kurmanji extraction plus human review (complete)

### 0.3 — Leads and pipeline (complete)

- Leads, configurable pipelines, and ordered stages (complete)
- Drag-and-drop pipeline board movement with an immutable activity trail and accessible form fallback (complete)
- Assignment, expected value, won/lost outcome, and loss reason (complete)

### 0.4 — Tasks, follow-ups, and reporting

- Tenant-local tasks, queued reminders, overdue work, notes, and immutable activity history (complete)
- Management dashboard and conversion reports with date-range, pipeline, owner, value, and task metrics (complete)
- First controlled pilots with two or three companies

### 0.5 — Facebook Lead Ads (complete)

- Company-admin connection setup inside the CRM, including encrypted Meta credentials and OAuth Page selection
- Signed, idempotent Facebook Lead Ads webhooks processed by the queue
- Configurable company, pipeline, stage, and owner destination for newly captured leads
- One independently editable CRM destination per Facebook Page, with multiple Pages sharing the Meta app's single webhook callback

### 0.6–0.7 — Communication channels

- Facebook Messenger text messages in a tenant-isolated unified inbox, including signed/idempotent webhook ingestion, queued replies, and paginated historical conversation import (complete)
- Instagram professional messaging in the unified inbox (deferred until publishing work resumes)
- WhatsApp Cloud API and approved message templates (deferred until publishing work resumes)

### 0.8–1.0 — Automation and sellable release

- Trigger/action automation with limits and audit history: lead-entered-stage → follow-up-task foundation (complete)
- AI conversation summaries, suggested replies, and manager insights
- Subscription status and configurable plan quota enforcement (complete)
- Administrator onboarding checklist and plan usage visibility (complete)
- Operational monitoring with scheduler/worker heartbeats, service probes, queue depth, and failure review (complete)
- Public launch

### Later services

- ERP, accounting, inventory, debt, and quotation integrations
- Custom reporting and data migration services
- AI voice receptionist and outbound calling, priced by usage

## V1 non-goals

- Native iOS or Android apps
- Locally hosted large language models
- Full accounting or inventory replacement
- A general-purpose workflow engine
- AI voice calling before CRM and messaging have paying users

## Product conventions

- Store money as integers in the currency's smallest unit and always store the ISO currency code.
- Keep currencies separate in reports. Define lead win rate as Won divided by Won plus Lost for leads created in the selected reporting period; open leads do not count as decisions.
- Store timestamps in UTC and render them in the tenant timezone, initially `Asia/Baghdad`.
- Preserve original external provider IDs and event payload references for idempotency and audits.
- Use soft deletion only where recovery or audit requirements justify it.
