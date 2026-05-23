# Durable Chat Relay

A resilience prototype for field teams who need chat actions to survive unreliable connectivity.

The system lets users keep sending chat events through a local helper while the central server is unavailable. When central returns, the helper retries pending events, Laravel accepts signed sync traffic, duplicates are ignored, conflicts are reported, and all clients converge on one official chat history.

This repository is the Laravel 12 and PostgreSQL central authority for the wider Durable Chat Relay prototype. The original project keeps the Vue client, Node.js helper, Socket.IO, IndexedDB recovery and peer-assisted WebRTC fallback. Laravel replaces only the central authority layer with a PHP 8.x OOP backend and durable PostgreSQL event store.

The value is the boundary:

```txt
local availability != central authority
```

The helper keeps local work recoverable. Laravel owns the authoritative event log, idempotent projection, recovery checksum validation, helper trust boundary and direct chat reconciliation.

## Smallest Demo

```txt
1. Start Laravel central.
2. Start the original helper and Vue client with npm run dev:laravel.
3. Send chat events through the helper.
4. Stop or disconnect central, keep sending locally, then bring central back.
5. Watch the helper retry pending events and Laravel converge to one official history.
```

Expected result:

```txt
pending helper events are retried
duplicate event ids are accepted once
conflicts are returned with structured codes
direct chat duplicates converge to one central chat id
read APIs show the official PostgreSQL-backed state
```

## System Map

```txt
Vue client :1234
   |
   | Socket.IO and helper API
   v
Original Node helper :3001
   |
   | signed HTTP sync
   v
Laravel 12 central API :8000
   |
   v
PostgreSQL
```

Laravel is designed to be used together with the original helper, not as a direct replacement for the browser/client runtime. It does not host the Socket.IO endpoint used by the Vue client.

## What this project proves

- A resilient chat prototype can separate local availability from central authority.
- Laravel can rebuild the central authority in another backend stack without changing the helper contract.
- Helper sync is signed with HMAC before Laravel accepts push or pull traffic.
- Helpers can retry events safely because event ids are idempotent.
- Missed central events are pulled by cursor without skipping paged results.
- Direct chat duplication is blocked by domain logic and PostgreSQL constraints.
- Several helpers can reconcile duplicate offline direct chats to one central chat id.
- Chat state is projected from durable events into read models.
- Event timestamps are stored as PostgreSQL `timestamptz`, not strings.
- Recovery import can be previewed with dry run before writing to the database.
- The API uses PHP 8.x OOP boundaries with services, DTOs, enums and repositories.

## Intentional Limits

This is not the browser facing chat server. It does not host the Socket.IO endpoint used by the Vue client and it does not implement the browser IndexedDB or WebRTC paths. Those belong to the original Durable Chat Relay project.

It is also not a production secure messaging platform. It has helper to central request signing, but it does not include full user authentication, per chat authorization, signed browser events, message encryption, production deployment or observability dashboards.

## Stack

| Area | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.x |
| Database | PostgreSQL |
| API | JSON REST |
| Helper authorization | HMAC signed helper sync requests |
| Domain design | Services, DTOs, enums, repositories, domain exceptions |
| Helper compatibility | Original Node.js helper from Durable Chat Relay |
| Tests | Laravel feature and unit tests |

## Quick start

Create a PostgreSQL database and configure `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=durable_chat
DB_USERNAME=durable_chat
DB_PASSWORD=postgres

DCR_CENTRAL_NODE_ID=laravel-central
DCR_HELPER_SHARED_SECRET=local-dev-helper-secret
DCR_TRUSTED_HELPER_IDS=helper-demo
DCR_HELPER_SIGNATURE_TOLERANCE_SECONDS=300
```

Install and run Laravel:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Check the central API:

```bash
curl http://127.0.0.1:8000/api/health
curl http://127.0.0.1:8000/api/readiness
```

## Run with the original helper

In the original Durable Chat Relay project, start the helper and client with the Laravel integration script:

```bash
npm run dev:laravel
```

Open the client through the helper:

```txt
http://localhost:1234?api=http://localhost:3001
```

Do not run `npm run dev` in the original project when testing Laravel integration. That starts the old Node central path. Do not point the Vue client directly to Laravel either. Laravel does not provide the Socket.IO endpoint used by the client.

## Demo scripts

Run Laravel first, then use the signed demo scripts:

```bash
scripts/demo-sync.sh
scripts/demo-duplicate-retry.sh
scripts/demo-recovery-dry-run.sh
```

Each script accepts `BASE_URL` and `DCR_HELPER_SHARED_SECRET` if your local values differ:

```bash
BASE_URL=http://127.0.0.1:8000 DCR_HELPER_SHARED_SECRET=local-dev-helper-secret scripts/demo-sync.sh
```

## Main endpoints

```http
GET  /api/health
GET  /api/readiness
GET  /api/config
GET  /api/users
GET  /api/chats
GET  /api/chats/{chatId}/messages
POST /api/events
POST /api/sync/events        signed helper request
GET  /api/sync/events        signed helper request
GET  /api/recovery/export    signed helper request
POST /api/recovery/import    signed helper request
```

## Documentation

- `docs/shared-integration-contract.md` explains the contract duplicated across both projects.
- `docs/architecture.md` explains Laravel's central role.
- `docs/api-contract.md` documents the HTTP API and signed sync contract.
- `docs/helper-central-auth.md` explains HMAC helper authorization.
- `docs/original-helper-integration.md` explains how to run this with the original helper.
- `docs/original-project-alignment.md` explains what belongs to the original project and what belongs here.
- `docs/demo-guide.md` gives repeatable demo scenarios.
- `docs/oop-design.md` explains the PHP 8.x OOP structure.

## Local verification

```bash
composer install
php artisan migrate:fresh --seed
php artisan test
```

The combined system should also be tested with the original helper using `npm run dev:laravel` from the original project.

## SHA-256 recovery checksum

Recovery exports include a SHA-256 checksum calculated from the canonical events payload. Recovery import verifies this checksum before accepting or previewing events, so truncated or manually corrupted dumps are rejected instead of being applied silently.
