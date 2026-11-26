# M7 CRM product specification

Status: milestone 0.9 sellable-release controls complete; milestone 0.7 Facebook team-inbox collaboration and the shared task board are implemented (2026-11-26) and awaiting sandbox verification; Instagram and WhatsApp remain deferred until Meta publishing

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
- Business-card scanning on the user's device with first-side and optional reverse-side capture, automatic orientation and dark-background correction, Arabic, English, Kurdish Sorani, and Kurdish Kurmanji language choices, human review, and atomic creation of a new client company with its contact; optional queued server fallback (complete)

### 0.3 — Leads and pipeline (complete)

- Leads, configurable pipelines, and ordered stages (complete)
- Drag-and-drop pipeline board movement with an immutable activity trail and accessible form fallback (complete)
- Assignment, expected value, won/lost outcome, and loss reason (complete)

### 0.4 — Tasks, follow-ups, and reporting

- Tenant-local tasks, queued reminders, overdue work, notes, and immutable activity history (complete)
- My Tasks, team task list, and a Kanban board over the same task records with To do, In progress, and Done columns, drag-and-drop plus an accessible per-card status control, and optional company, contact, and conversation links (complete 2026-11-26)
- Management dashboard and conversion reports with date-range, pipeline, owner, value, and task metrics (complete)
- Statistical agent attribution on opportunities with won/lost, conversion, and separate-currency value reporting; agents receive no CRM access (complete)
- English, Arabic, and Kurdish Sorani interface selection with persistent session preference and RTL layouts for Arabic-script locales (complete)
- First controlled pilots with two or three companies

### 0.5 — Facebook Lead Ads (complete)

- Company-admin connection setup inside the CRM, including encrypted Meta credentials and OAuth Page selection
- Signed, idempotent Facebook Lead Ads webhooks processed by the queue
- Configurable company, pipeline, stage, and owner destination for newly captured leads
- One independently editable CRM destination per Facebook Page, with multiple Pages sharing the Meta app's single webhook callback

### 0.6–0.7 — Communication channels

- Facebook Messenger text messages in a tenant-isolated unified inbox, including signed/idempotent webhook ingestion, queued replies, and paginated historical conversation import (complete)
- Facebook Page comments in the tenant-isolated unified inbox with signed/idempotent `feed` webhook ingestion and queued public replies (complete)
- Reviewed Inbox contact capture with deterministic phone, city, organization, and customer-category suggestions from recent inbound messages and comments (complete)
- Open conversations update every two seconds; incoming messages/comments remain compatible with existing default-queue workers, and the Compose configuration also drains the legacy messaging queue (complete)
- Team-inbox collaboration on Facebook (complete 2026-11-26, pending sandbox verification): shared queue filtered by status, channel, Page, assignee, and per-user unread state; assignment and reassignment; open, pending, and resolved status; internal notes; explicit public/private/internal composer labels; delivery status and classified send failures with the draft preserved; duplicate-reply guard and advisory presence; linking an existing contact or confirming a new one; creating a follow-up task from the conversation; linked conversations and tasks on the contact record; queue refresh every five seconds without a page reload; an immutable conversation audit trail; and Page disconnection that keeps customer data
- Facebook private replies to comments and Page mentions (next Facebook increment; see capability matrix)
- Instagram professional messaging in the unified inbox (deferred until publishing work resumes)
- WhatsApp Cloud API and approved message templates (deferred until publishing work resumes)

### 0.8–1.0 — Automation and sellable release

- Trigger/action automation with limits and audit history: lead-entered-stage → follow-up-task foundation (complete)
- AI conversation summaries, suggested replies, and manager insights
- Subscription status and configurable plan quota enforcement (complete)
- Administrator onboarding checklist and plan usage visibility (complete)
- Operational monitoring with scheduler/worker heartbeats, service probes, queue depth, and failure review (complete)
- Public Privacy Policy, Terms of Service, and user-data deletion instructions with Meta setup URLs (complete)
- Responsive public product homepage with registration, feature, security, and plan-capacity information (complete)
- Public sitemap and crawler rules plus baseline browser security headers (complete)
- Validated, rate-limited public sales and support contact form delivered through the queue (complete)
- Branded recovery pages for authorization, missing-page, rate-limit, server, and maintenance errors (complete)
- Public launch

### Later services

- ERP, accounting, inventory, debt, and quotation integrations
- Custom reporting and data migration services
- AI voice receptionist and outbound calling, priced by usage

## Facebook channel capabilities

Checked against Meta's official Messenger Platform, Page webhook, comments, private-replies, webhook, rate-limit, and error-code documentation on 2026-11-26. Re-check before App Review.

| Action | Meta API | Permissions and review | CRM status |
|---|---|---|---|
| Receive direct messages | `messages` Page webhook field | `pages_messaging`; `pages_manage_metadata` for webhook delivery | Supported |
| Reply to direct messages | `POST /{page-id}/messages`, `messaging_type=RESPONSE` | `pages_messaging` needs App Review before non-role users can be messaged (error 200/2018028) | Supported inside the 24-hour standard window |
| Replies after 24 hours | `HUMAN_AGENT` message tag (7 days) | Separate approval; exact requirement not confirmed in the fetched docs | Not supported; reply in Meta Business Suite |
| Receive Page comments | `feed` Page webhook field | `pages_manage_metadata`; reading comment content requires the Page read permissions requested at connection | Supported |
| Public reply to comments | `POST /{comment-id}/comments` | `pages_manage_engagement`; Page token from a person with the MODERATE task | Supported |
| Private reply to a comment | `POST /{page-id}/messages` with `recipient.comment_id` | `pages_messaging`; one message within 7 days; Standard Access only reaches app-role users | Not built; shown as unsupported with a link to the post |
| Page mentions | `mention` Page webhook field | Some IDs are withheld for privacy or missing permissions; replying via the API is unverified | Not built; handled on Facebook |
| Delivery and read receipts | `message_deliveries`, `message_reads` | Messenger only | Not subscribed; CRM shows queued, sending, sent, or failed |

Send failures are classified from Graph errors: code 190 marks the connection `reconnect_required`; codes 4, 17, 32, 613, 80001, 80006 and HTTP 429 release the job using `X-Business-Use-Case-Usage` when present; code 10 with subcode 2018278 or 2534022 is a closed messaging window; 551 is an unavailable recipient; other 10/200 errors indicate missing permission or review. Meta retries undelivered webhooks for up to 36 hours, so every event is deduplicated by provider ID before processing.

## Team inbox and follow-up rules

- Every active tenant user can read the shared queue. Company admins and sales managers can assign, reassign, change status, and reply on any conversation. A salesperson can claim an unassigned conversation, hand off or release one they own, and reply only when it is unassigned or theirs. Anyone on the team can add internal notes.
- Replying to an unassigned conversation assigns it to the sender. A reply is refused, with the draft kept, when another outbound message was sent after the composer loaded; the sender may confirm and send anyway. Identical replies from the same person within one minute are sent once.
- A new inbound customer message reopens a pending or resolved conversation. Imported history updates the last-inbound time but never reopens it.
- Resolved conversations and conversations on disconnected Pages cannot be replied to until reopened or reconnected.
- Disconnecting a Page removes stored Page and user tokens, pauses replies, and stops ingestion while keeping conversations, messages, leads, and webhook history. Deleting a connection remains a separate, explicitly confirmed action that removes its inbox data.
- Tasks have three statuses: `pending` (To do), `in_progress`, and `completed` (Done). Both open statuses count toward due-today, overdue, dashboards, reports, and reminders.
- Inbox lists refresh by polling (five seconds for the queue, two seconds for an open conversation). WebSocket broadcasting is deferred until polling load requires it.

## V1 non-goals

- Native iOS or Android apps
- Locally hosted large language models
- Full accounting or inventory replacement
- A general-purpose workflow engine
- AI voice calling before CRM and messaging have paying users

## Product conventions

- Business cards are processed in a browser worker by default using self-hosted Tesseract.js and language assets. The user can supply one or both card sides; the scanner checks likely rotations, corrects dark backgrounds, merges the readable text, and then proposes fields for review. Photos and raw OCR stay in the current page; only reviewed contact fields are submitted. The user may select an existing CRM client company or create a client company and its first contact atomically; client companies do not receive a login or workspace. The server validates ownership, permissions, and plan capacity. Saving, cancellation, and navigation release both photos and temporary review data; the original gallery photos are unaffected. Language assets may be cached locally. Scans require an open page and results must be checked, particularly for mixed scripts. Sorani uses the Arabic-script LSTM model because Tesseract's Kurdish model is legacy-only. The explicit server fallback retains its private upload and 24-hour cleanup workflow.
- Store money as integers in the currency's smallest unit and always store the ISO currency code.
- Keep currencies separate in reports. Define lead win rate as Won divided by Won plus Lost for leads created in the selected reporting period; open leads do not count as decisions.
- Store timestamps in UTC and render them in the tenant timezone, initially `Asia/Baghdad`.
- Preserve original external provider IDs and event payload references for idempotency and audits.
- Use soft deletion only where recovery or audit requirements justify it.
