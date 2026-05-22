# Durable Chat Relay Laravel Central Server

PHP 8.x and Laravel 12 central server for Durable Chat Relay.

This project replaces the original Node.js central backend with a Laravel and PostgreSQL authority while keeping the original Node.js helper as the local resilience layer.

```txt
Vue client
   ↓ Socket.IO / local API
Original Node.js helper
   ↓ HTTP sync batches
Laravel 12 central API
   ↓
PostgreSQL durable event log
```

## Project goals

- Provide a Laravel 12 central API for Durable Chat Relay
- Store the authoritative event log in PostgreSQL
- Preserve compatibility with the existing Node.js helper sync flow
- Keep HTTP controllers thin and move behaviour into application services
- Use PHP 8.x OOP structure with DTOs, enums, repository interfaces and domain services
- Enforce central idempotency for repeated helper sync attempts
- Return structured conflict metadata for rejected events
- Apply causal ordering checks for sync batches
- Project event data into read models for chats, members, messages and read state
- Support bounded pull sync and checksum-verified recovery imports
- Keep local resilience concerns outside Laravel and inside the original helper

## Architecture boundary

Laravel is always the central server. It does not run in helper mode and does not expose a configurable node role.

The original helper remains responsible for local Socket.IO communication, local SQLite durability, retries, peer assisted relay and client recovery behaviour.

Laravel owns the server side rules:

- event validation
- idempotent event storage
- direct chat duplicate protection
- chat membership rules
- message projection
- recovery import validation
- recovery checksum verification
- PostgreSQL persistence

## Design highlights

- Transactional event acceptance with database-backed idempotency
- Partial unique index for direct chat pair protection
- Per-device logical clock checks for causal ordering
- Structured sync conflicts with stable codes, categories and retryability
- Bounded pull sync for catch-up without unbounded event-log responses
- `timestamptz` storage with UTC ISO output for event timestamps
- Checksum-verified recovery imports
- Read models for chat lists, members, messages and read state

## Development Setup

Create the PostgreSQL database and user first:

```txt
Database: durable_chat
User: durable_chat
Password: postgres
```

Then run:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Check Laravel:

```bash
curl http://127.0.0.1:8000/api/health
curl http://127.0.0.1:8000/api/users
```

Expected health response:

```json
{
  "ok": true,
  "service": "durable-chat-laravel-central",
  "centralNodeId": "laravel-central"
}
```

## Connect the original helper

In the original Durable Chat Relay project, run the helper with Laravel as the central target:

```bash
CENTRAL_URL=http://127.0.0.1:8000 npm run helper
```

Do **not** run the old Node central backend for this architecture.

Start the existing client and point it at the helper:

```txt
http://localhost:1234/?api=http://localhost:3001
```

Full instructions are in:

```txt
docs/original-helper-integration.md
```

## Central sync API

The original helper uses these endpoints:

```txt
POST /api/sync/events
GET  /api/sync/events?since=0
```

Push response:

```json
{
  "accepted": [],
  "duplicates": [],
  "conflictIds": [],
  "conflicts": [],
  "serverEvents": [],
  "centralNodeId": "laravel-central",
  "meta": {
    "syncAttemptId": "uuid",
    "sourceNodeId": "helper-demo",
    "orderingPolicy": "batch-order-with-per-device-logical-clock",
    "counts": {
      "received": 0,
      "accepted": 0,
      "duplicates": 0,
      "conflicts": 0,
      "serverEvents": 0
    }
  }
}
```

Pull response:

```json
{
  "centralNodeId": "laravel-central",
  "latestSequence": 0,
  "limit": 500,
  "events": []
}
```

Pull sync is bounded with `limit`. Recovery exports include an ordered event
checksum and export limit metadata, and recovery imports verify the checksum
when present.

## OOP structure

```txt
app/
  Application/
    Events/
    Messages/
    Recovery/
    Sync/
  Contracts/
  Domain/
    Chats/
    Events/
    Shared/
  Infrastructure/
    PostgresChatProjectionRepository.php
    PostgresChatQueryRepository.php
    PostgresChatSummaryLoader.php
    PostgresEventRepository.php
    PostgresMessageHydrator.php
  Http/
    Controllers/
```

Laravel handles routing, dependency injection, request/response flow and database integration. Durable chat behaviour is separated into typed services, DTOs, repositories and domain classes.

## Documentation

```txt
docs/api-contract.md
docs/oop-design.md
docs/resilience-scenarios.md
docs/original-helper-integration.md
docs/git-description.md
```

## Useful commands

```bash
php artisan durable-chat:about
php artisan test
vendor/bin/pint
```
