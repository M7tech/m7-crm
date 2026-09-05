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

The default scanner runs in the user's browser with self-hosted Tesseract.js, WebAssembly, and English, Arabic, Kurdish Sorani, and Kurdish Kurmanji language files. No external AI account, API key, or CDN is required. The card photo and raw OCR stay on the device; only fields reviewed by the user are submitted to the normal contact endpoint. The first scan loads the selected language data and subsequent scans can reuse the browser cache.

The production asset build runs `scripts/prepare-browser-ocr.mjs` and copies the worker, WebAssembly core, and language files to `/public/ocr/v7-1`. After deployment, confirm `/ocr/v7-1/worker.min.js` is reachable and test a card from a mobile browser. Review the extracted fields, select the CRM company, save, and verify the contact belongs to that company. Cancelling, navigating away, or saving releases the browser's temporary image; the user's original gallery photo remains unchanged.

The production image retains Tesseract and ImageMagick for the explicit **Device cannot scan? → Use server scanner** fallback. The app, worker, and scheduler services share `private_storage` for that fallback. Saving deletes its private card image and temporary extraction immediately; abandoned server scans expire after 24 hours.
