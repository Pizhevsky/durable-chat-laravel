# Shared Integration Contract

This document is duplicated in both repositories so the integration rules stay visible from either side.

There are two separate projects:

```txt
Original Durable Chat Relay project
  Vue client + Node helper + optional original Node central

Laravel central server project
  Laravel 12 HTTP central API + PostgreSQL durable event store
```

For the Laravel integration path:

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

## Transport boundary

The Vue client does not talk directly to Laravel. It talks to the Node helper because the client uses Socket.IO and local recovery behaviour owned by the original project.

Only the helper sends signed HTTP sync requests to the central server.

## Protected helper sync endpoints

```http
POST /api/sync/events
GET  /api/sync/events?since=...&limit=...
```

Central implementations may also protect recovery import/export when used as helper/operator endpoints.

## Helper signature headers

```txt
X-DCR-Helper-Id
X-DCR-Timestamp
X-DCR-Signature
```

The browser must not contain the helper secret.

## Sync response expectations

A central sync response should identify accepted events, duplicates, conflicts and any authoritative server events needed for reconciliation.

The helper uses these fields to:

```txt
mark local events as central-synced
avoid retrying permanent conflicts
apply central events missed during outage
remap duplicate direct chat ids to the authoritative central chat id
advance the pull cursor only to the last returned sequence
```

## Direct chat reconciliation

Direct chat identity is based on a canonical pair key. If two helpers create the same direct chat offline, the central server keeps one authoritative chat and returns enough information for the losing helper to remap its local chat id.

## Central implementations

The original Node central and the Laravel central should follow the same helper sync contract. They differ in implementation and storage, not in the helper-facing contract.
