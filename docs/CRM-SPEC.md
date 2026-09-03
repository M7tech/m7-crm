# M7 CRM product specification

Status: leads and pipeline milestone complete

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

### 0.3 — Leads and pipeline (complete)

- Leads, configurable pipelines, and ordered stages (complete)
- Pipeline board movement with an immutable activity trail (complete)
- Assignment, expected value, won/lost outcome, and loss reason (complete)

### 0.4 — Tasks, follow-ups, and reporting

- Tasks, reminders, overdue work, notes, and activity history
- Management dashboard and conversion reports
- First controlled pilots with two or three companies

### 0.5–0.7 — Communication channels

- Facebook Lead Ads webhooks
- Facebook Messenger and Instagram professional messaging
- Unified inbox
- WhatsApp Cloud API and approved message templates

### 0.8–1.0 — Automation and sellable release

- Trigger/action automation with limits and audit history
- AI conversation summaries, suggested replies, and manager insights
- Subscription enforcement, onboarding, operational monitoring, and public launch

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
- Store timestamps in UTC and render them in the tenant timezone, initially `Asia/Baghdad`.
- Preserve original external provider IDs and event payload references for idempotency and audits.
- Use soft deletion only where recovery or audit requirements justify it.
