# Original Helper Integration

## Goal

Use the existing Durable Chat Relay helper as the browser facing local resilience layer, while Laravel becomes the central sync authority.

```txt
Vue client -> Node helper -> Laravel central -> PostgreSQL
```

## Start Laravel central

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

## Start the original helper and Vue client

In the original project:

```bash
npm run dev:laravel
```

Or run them separately:

```bash
npm run helper:laravel
npm run dev:client
```

Open:

```txt
http://localhost:1234?api=http://localhost:3001
```

## What not to run

Do not run the old Node central server for the Laravel central demo:

```bash
npm run dev
npm run dev:central
```

Those commands test the original Node central path. Laravel owns the central API in this setup.

Do not point the Vue app at `http://127.0.0.1:8000`. Laravel does not host the client's Socket.IO endpoint.

## Helper contract

The helper sends local or recovered events to:

```http
POST /api/sync/events
```

The helper pulls missed central events from:

```http
GET /api/sync/events?since=0&limit=200
```

Both requests must be signed with the HMAC helper headers documented in `docs/helper-central-auth.md`.

## Sync response compatibility

Laravel keeps these response fields for the original helper contract:

```txt
accepted
duplicates
conflicts
serverEvents
latestSequence
currentSequence
hasMore
nodeRole
nodeId
centralNodeId
```

`nodeRole` and `nodeId` are fixed compatibility fields. Laravel is still always the central server and cannot be configured as a helper.

## Multi helper reconciliation

If two helpers create the same direct chat offline, Laravel keeps one canonical central chat id. The losing helper receives the central `chat.created` event and remaps its local duplicate chat id before retrying pending messages.

This protects the integrated system from this failure mode:

```txt
Helper A creates chat-a for Denis and Anna.
Helper B creates chat-b for Denis and Anna.
Both sync later.
The system must not leave two direct chats for the same pair.
```

Expected result:

```txt
one central direct chat
helpers remapped to the central chat id
pending messages rewritten to the central chat id
browser state reconciled after central event application
```

## Reset before a clean demo

For a clean helper and Laravel demo, reset:

```txt
Laravel PostgreSQL database
helper SQLite database
browser localStorage
browser sessionStorage
browser IndexedDB
```

Mixed old state can make retry, cursor and direct chat reconciliation demos confusing.
