# Coolify deployment

The included `docker-compose.coolify.yml` runs four services from one codebase: the web application, queue worker, scheduler, and PostgreSQL/Redis data services. Your current 4-core, 7.7 GB server is appropriate for the first release.

## Before the first deployment

1. Push this repository to a private GitHub repository.
2. In Coolify, create a Docker Compose resource from `docker-compose.coolify.yml`.
3. Add every variable from `.env.coolify.example` to Coolify's environment settings, replacing all placeholder secrets.
4. Generate `APP_KEY` locally with `php artisan key:generate --show`; do not invent or reuse a key from another application.
5. Attach the chosen CRM domain to the `app` service on port 80.
6. Deploy, then run `php artisan migrate --force` once from the application terminal.

Do not expose PostgreSQL or Redis publicly. Configure daily encrypted PostgreSQL backups to storage outside this VPS before onboarding a real customer.

## Deployment checks

- `/up` returns HTTP 200.
- Registration creates a tenant and company-admin user.
- The queue worker and scheduler show as healthy/running.
- Email verification and password reset work with the production mail provider.
- A tenant-isolation smoke test is completed using two test companies.

## Meta Lead Ads

Meta App IDs, secrets, and Page access tokens are managed from **CRM → Integrations → Meta Lead Ads** and stored encrypted in the database; they are not Coolify environment variables. A company admin creates the connection, copies the OAuth redirect URI and Page webhook callback/verify token shown by the CRM into the Meta developer app, and subscribes the `leadgen` webhook field. In **Facebook Login for Business → Configurations**, create a user-access-token configuration for Pages with `pages_show_list`, `pages_manage_metadata`, `pages_read_engagement`, and `leads_retrieval`, then save its Configuration ID in the CRM before using **Connect Facebook** to authorize and choose a Page.

Keep the queue worker running: signed webhook deliveries are accepted immediately and the worker retrieves the submitted lead details from Meta before creating the CRM contact, lead, and activity.

## Business-card scanner

Add an OpenAI project API key as `OPENAI_API_KEY` in Coolify. The optional `OPENAI_BUSINESS_CARD_MODEL` defaults to `gpt-5.6-luna`. Redeploy all Compose services after adding the variables, then run `php artisan migrate --force` in the app container. The app, worker, and scheduler services mount the same `private_storage` volume so a private image uploaded by the web container can be read by the queued scanner and purged after use.

Confirm the queue worker and scheduler remain running. Scan one test card from **Contacts → Scan business card**, review the extracted fields, save it, and verify that the new contact belongs to the selected CRM company.
