# Architecture

This Laravel service is the central HTTP authority for Durable Chat Relay. It is designed to work with the original Node helper rather than replace it.

The original project owns:

```txt
Vue client
Socket.IO transport
Node helper
browser IndexedDB recovery
peer-assisted WebRTC fallback
optional original Node central server
```

This project owns:

```txt
Laravel central API
PostgreSQL durable event log
helper signature verification
idempotent projection
central read APIs
```

## Runtime shape

```txt
Vue client :1234
   |
   | Socket.IO and helper API
   v
Original Node helper :3001
   |
   | signed HTTP sync
   v
Laravel central :8000
   |
   v
PostgreSQL
```

Laravel does not host the browser Socket.IO endpoint.

## Central responsibilities

| Concern | Laravel responsibility |
|---|---|
| Helper trust | Verify signed helper sync requests. |
| Event log | Store accepted events once by `eventId`. |
| Projection | Build chats, members, messages and read state. |
| Direct chat reconciliation | Keep one canonical direct chat per user pair. |
| Sync pull | Return missed central events by cursor without skipping pages. |
| Recovery | Export/import recovery payloads with checksum verification. |
| Read APIs | Provide users, chats and messages from PostgreSQL projections. |

## What remains outside Laravel

| Concern | Owner |
|---|---|
| Browser realtime transport | Original Node central/helper through Socket.IO. |
| Local helper queue | Original Node helper SQLite. |
| Browser outbox and cache | Original Vue client with IndexedDB. |
| Peer-assisted delivery | Original WebRTC/browser layer. |
| Original standalone demo | Original Node central + SQLite. |

## Failure model

The Laravel path demonstrates this flow:

```txt
central available -> helper syncs signed batches normally
central unavailable -> helper stores events locally and retries
central returns -> helper signs pending events and Laravel accepts each event once
several helpers create same direct chat -> Laravel keeps one authoritative chat id
```

This is a portfolio resilience prototype, not a production secure messaging platform.
