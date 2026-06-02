# LipaBO — Backoffice Web App

Backoffice administration UI for the **KomoPay / LipaBO** payments platform. It is a
Laravel + Livewire web application that consumes the upstream **Backoffice REST API**
and gives operations, supervision and compliance staff a single place to manage
customers, agents, merchants, KYC, transactions, wallets, cards, terminals, treasury,
reconciliation and more.

This app holds **no business data of its own** — it is a thin, authenticated UI layer
on top of the Backoffice API. The only local persistence is for the framework itself
(sessions, cache, queue) and backoffice user accounts/authentication.

## Features

- **Authentication & account security**
  - Email/password login with mandatory first-login password setup.
  - TOTP (authenticator app) multi-factor authentication:
    - Mandatory enrollment for `ADMIN` / `SUPER_ADMIN`.
    - Voluntary setup/management for other roles under *Account security*.
- **Role-based access** — UI and actions adapt to the signed-in user's role:
  `OPERATOR`, `SUPERVISOR`, `COMPLIANCE`, `ADMIN`, `SUPER_ADMIN`.
- **Operational modules**
  - **Customers / Agents / Merchants** — listings and KYC review (with document viewing).
  - **Transactions** & **Wallets** — monitoring with cursor pagination.
  - **Approvals** — maker/checker style approval queues.
  - **Bill payments**, **Service providers**, **Cards**, **Terminals**.
  - **Treasury**, **Reconciliation**, **Rules & limits**, **Reports**, **Audit**.
  - **Users** — backoffice user management.
- **API-backed** — all domain data is fetched live from the Backoffice API via a
  typed client (`App\Services\Api\HttpBackofficeApi`).

## Tech stack

- **PHP** ^8.2
- **Laravel** ^11.31
- **Livewire** ^4.3
- **Tailwind CSS** 4 + **Vite** 6
- **SQLite** by default (sessions, cache, queue, users)

## Requirements

- PHP 8.2+ with the usual Laravel extensions
- Composer
- Node.js 18+ and npm
- A running **Backoffice API** to point the app at (see configuration below)

## Getting started

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env          # on Windows: copy .env.example .env
php artisan key:generate

# 3. Database (SQLite by default — create the file, then migrate)
#    On *nix: touch database/database.sqlite
#    On Windows PowerShell: New-Item database/database.sqlite -ItemType File
php artisan migrate

# 4. Run everything (server + queue + logs + Vite) in one command
composer run dev
```

`composer run dev` starts the PHP dev server, the queue worker, log tailing (Pail) and
the Vite dev server concurrently. Alternatively run them separately:

```bash
php artisan serve
npm run dev
```

The app is then available at the URL shown by `artisan serve` (default
`http://localhost:8000`).

## Configuration

The app talks to the upstream Backoffice API. Configure it in `.env`
(see [`config/komopay.php`](config/komopay.php)):

| Variable                 | Description                                          | Default                     |
| ------------------------ | ---------------------------------------------------- | --------------------------- |
| `KOMOPAY_API_BASE_URL`   | Base URL of the Backoffice API                       | `http://localhost:8080`     |
| `KOMOPAY_API_PREFIX`     | Path prefix prepended to every backoffice endpoint   | `/api/v1/backoffice`        |
| `KOMOPAY_API_VERSION`    | Version segment for shared (non-backoffice) endpoints | `api/v1`                   |
| `KOMOPAY_API_TOKEN`      | Optional bearer token for the upstream API           | *(empty)*                   |
| `KOMOPAY_API_TIMEOUT`    | HTTP timeout in seconds                              | `15`                        |

Set `KOMOPAY_API_BASE_URL` (and `KOMOPAY_API_TOKEN` in production) to point at your
Backoffice API instance.

## Project structure

```
app/
  Enums/Backoffice/      Domain enums mirrored from the Backoffice API
  Http/
    Controllers/         Auth, MFA and the Backoffice page controllers
    Middleware/          BackofficeAuth (session/role gating)
  Livewire/Concerns/     Shared Livewire traits (API access, cursor pagination)
  Services/Api/          Typed Backoffice API client + contract
resources/views/
  livewire/              Livewire components per module (customers, agents, …)
  components/            Reusable UI components (badge, amount, pagination, …)
  layouts/               App and auth layouts
routes/web.php           All routes (auth, MFA, backoffice modules)
config/komopay.php       Backoffice API configuration
```

## Testing

```bash
php artisan test
```

## Code style

This project uses [Laravel Pint](https://laravel.com/docs/pint):

```bash
./vendor/bin/pint
```
