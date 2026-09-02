# Coolify deployment

The included `docker-compose.coolify.yml` runs four services from one codebase: the web application, queue worker, scheduler, and PostgreSQL/Redis data services. Your current 4-core, 7.7 GB server is appropriate for the first release.

## Before the first deployment

1. Push this repository to a private GitHub repository.
2. In Coolify, create a Docker Compose resource from `docker-compose.coolify.yml`.
3. Add every variable from `.env.coolify.example` to Coolify's environment settings, replacing all placeholder secrets.
4. Generate `APP_KEY` locally with `php artisan key:generate --show`; do not invent or reuse a key from another application.
5. Attach the chosen CRM domain to the `app` service on port 8080.
6. Deploy, then run `php artisan migrate --force` once from the application terminal.

Do not expose PostgreSQL or Redis publicly. Configure daily encrypted PostgreSQL backups to storage outside this VPS before onboarding a real customer.

## Deployment checks

- `/up` returns HTTP 200.
- Registration creates a tenant and company-admin user.
- The queue worker and scheduler show as healthy/running.
- Email verification and password reset work with the production mail provider.
- A tenant-isolation smoke test is completed using two test companies.
