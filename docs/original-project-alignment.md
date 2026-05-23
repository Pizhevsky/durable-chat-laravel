# Original Project Alignment

## Why this document exists

The original Durable Chat Relay project already explains the full resilience prototype: Vue client, Node central server, Node helper, browser IndexedDB, recovery, peer assisted WebRTC fallback and demo flows.

This Laravel project should not copy all of that. It exists as an additional central server option.

## Original project role

The original project provides:

- Vue 3 client
- Socket.IO realtime behaviour
- Node.js central mode for the original demo path
- Node.js helper mode for the Laravel integration path
- SQLite event stores
- browser IndexedDB outbox and cache
- recovery export/import from the client path
- WebRTC peer signalling and peer event replication
- one computer local only demo

## This project role

This Laravel project replaces only the central HTTP authority in the Laravel integration path:

```txt
Original Node central server
        ↓
Laravel 12 central server + PostgreSQL
```

It keeps the original helper and client architecture:

```txt
Vue client -> original Node helper -> Laravel central -> PostgreSQL
```

## Terminology kept consistent

| Original project term | Meaning in this Laravel project |
|---|---|
| central server | Laravel central API for the Laravel integration path |
| helper node | original Node.js helper process |
| event log | PostgreSQL `events` table |
| helper sync | signed `POST /api/sync/events` and signed `GET /api/sync/events` |
| recovery import | Laravel import through central sync rules |
| local only | still handled by the original client/helper path |
| peer assisted mode | still handled by the original client/helper path |

## What is intentionally not duplicated

This project does not reimplement:

- Socket.IO server for the Vue client
- browser IndexedDB storage
- service worker notification path
- WebRTC peer data channels
- local only UI simulation
- helper SQLite queue
- helper backoff loop

Those features belong to the original project.

## Contract between projects

The helper talks to Laravel through signed HTTP:

```http
POST /api/sync/events
GET  /api/sync/events?since=0&limit=200
```

Recovery endpoints are available on Laravel:

```http
POST /api/recovery/import
GET  /api/recovery/export
```

Recovery import/export uses the same signed helper request contract as sync.

The Vue client continues to talk to the helper:

```txt
http://localhost:1234?api=http://localhost:3001
```

Do not point the Vue client directly to Laravel. Laravel does not expose the Socket.IO endpoint expected by the client.

## Clean demo rule

When switching between Node central and Laravel central, reset the stores that take part in the demo:

```txt
Laravel PostgreSQL database
original central SQLite database
helper SQLite database
browser localStorage/sessionStorage/IndexedDB
```

Mixed old data can make retry, cursor and direct chat reconciliation demos confusing because each layer may remember a different event sequence.
