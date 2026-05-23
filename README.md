# Durable Chat Laravel Central Server

A Laravel 12 and PostgreSQL central authority for Durable Chat Relay.

This project adds a PHP 8.x backend to the original Durable Chat Relay prototype. The original project keeps the Vue client, Node helper, Socket.IO runtime, IndexedDB recovery and peer-assisted WebRTC fallback. This Laravel server takes over the central authority role, storing durable events in PostgreSQL and enforcing signed helper sync.

The server accepts event batches from trusted helpers, deduplicates by `eventId`, projects chat state, verifies recovery checksums, and reconciles direct-chat duplicates created by several helpers.

It is designed to work with the original helper, not to replace the browser-facing realtime layer.

```txt
Vue client -> Node helper -> Laravel central API -> PostgreSQL
```

## Why this exists

The original project already proves the browser/helper resilience idea. This Laravel project proves that the central authority can be rebuilt as a clean PHP backend without changing the core sync contract.

That makes the architecture easier to discuss from two angles:

- the original project shows local resilience, Socket.IO, IndexedDB and peer fallback
- this project shows Laravel, PostgreSQL, PHP OOP boundaries, signed sync and durable central reconciliation

## What this server owns

- signed helper-to-central sync verification
- durable event storage in PostgreSQL
- idempotent event acceptance by `eventId`
- chat, member, message and read-model projection
- direct-chat duplicate reconciliation between helpers
- recovery export/import with SHA-256 checksum validation
- read APIs for users, chats and messages

Laravel does not host the browser Socket.IO endpoint. The Vue client should talk to the original Node helper, and the helper should send signed HTTP sync requests to Laravel.

## Quick start

Configure PostgreSQL in `.env`:

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

Run Laravel:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Check:

```bash
curl http://127.0.0.1:8000/api/health
curl http://127.0.0.1:8000/api/readiness
```

## Run with the original helper

In the original Durable Chat Relay project:

```bash
npm install
npm run dev:laravel
```

Open:

```txt
http://localhost:1234?api=http://localhost:3001
```

The simplest thing to demonstrate is central outage recovery: stop Laravel, keep sending messages through the helper, restart Laravel, then watch the helper sign and sync pending events. Laravel accepts each event once and projects the official history into PostgreSQL.

For the full walkthrough, use [`docs/demo-guide.md`](docs/demo-guide.md).

## Documentation

- [`docs/demo-guide.md`](docs/demo-guide.md) shows the practical demo flows.
- [`docs/architecture.md`](docs/architecture.md) explains the central authority role.
- [`docs/oop-design.md`](docs/oop-design.md) explains the PHP OOP boundaries.
- [`docs/api-contract.md`](docs/api-contract.md) documents the HTTP API.
- [`docs/helper-central-auth.md`](docs/helper-central-auth.md) explains HMAC helper authorization.
- [`docs/original-helper-integration.md`](docs/original-helper-integration.md) shows how this works with the original helper.
- [`docs/shared-integration-contract.md`](docs/shared-integration-contract.md) mirrors the integration contract shared with the original project.

## Scope

This is not a production-secure messaging platform. It includes signed helper-to-central sync, checksum validation, database constraints and idempotent projection, but it does not yet include full user authentication, per-chat authorization, signed browser events, message encryption, production deployment, observability dashboards or load testing.
