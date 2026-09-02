# Codex on Windows

Use the ChatGPT desktop app for Windows with WSL2 for this project. The application runs on Linux in Coolify, so WSL2 keeps local paths, permissions, and command behavior close to production.

## Recommended setup

1. Install the ChatGPT desktop app for Windows and sign in.
2. Enable WSL2 and install Ubuntu 24.04 from an administrator PowerShell window:

   ```powershell
   wsl --install -d Ubuntu-24.04
   ```

3. In Ubuntu, install Git, PHP 8.3 or newer with Laravel's required extensions, Composer 2, and Node.js 22 or newer.
4. Extract the project inside the Linux filesystem, for example `~/projects/m7-crm`, rather than under `C:\`.
5. In the desktop app, choose **On my computer**, select the project folder, and configure Codex to use WSL2.

## First project run

Run these commands in the WSL2 terminal from the project folder:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
composer test
composer dev
```

Open the local URL shown by Laravel. SQLite is the local default, so PostgreSQL and Redis are not required for the first run. Production continues to use PostgreSQL and Redis through Coolify.

## Safe workflow

- Create a Git commit after each completed feature.
- Let only one coding agent edit a feature at a time.
- Ask Claude Code to review committed work without editing, then give any findings back to Codex.
- Never put production passwords or API tokens in prompts, commits, or `.env.example` files.
