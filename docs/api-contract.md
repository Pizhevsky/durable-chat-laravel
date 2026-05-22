# API contract

This Laravel service is the central authority for Durable Chat Relay. Helpers
push local events to central in batches and pull missed central events by
sequence.

Laravel has a fixed central server identity. Helper identity is preserved as
event metadata such as `originNodeId`, `originDeviceId` and `sourceNodeId`.

## Health

```txt
GET /api/health
```

Response:

```json
{
  "ok": true,
  "service": "durable-chat-laravel-central",
  "centralNodeId": "laravel-central"
}
```

## Config

```txt
GET /api/config
```

Response:

```json
{
  "centralNodeId": "laravel-central",
  "centralUrl": "http://127.0.0.1:8000",
  "vapidPublicKey": null
}
```

## Users

```txt
GET /api/users
```

Returns the central user list used by chat projection and membership checks.

## Chats

```txt
GET /api/chats?userId=u-denis
```

Returns active chats for the requested user, including members, unread counts
and last-message summaries.

## Messages

```txt
GET /api/chats/{chatId}/messages?userId=u-denis
```

Returns messages only when the requested user is an active chat member.

## Publish One Event

```txt
POST /api/events
x-demo-user-id: u-denis
```

This endpoint supports direct development checks. The normal resilience path is
batch sync from the helper through `POST /api/sync/events`.

## Push Events

```txt
POST /api/sync/events
```

Request:

```json
{
  "sourceNodeId": "helper-demo",
  "events": []
}
```

Response:

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
    "receivedAt": "2026-05-20T10:00:00.000000Z",
    "completedAt": "2026-05-20T10:00:00.000000Z",
    "orderingPolicy": "batch-order-with-per-device-logical-clock",
    "replayGuarantee": "eventId and direct-chat uniqueness are idempotent; accepted events are returned in central sequence order by pull sync",
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

`conflicts` is the central rejection channel. Laravel returns a conflict when
storing the event would make central state untrue, for example when the actor
user is unknown or an event depends on a chat that central has not accepted yet.

Conflict object:

```json
{
  "eventId": "device-1:event-2",
  "code": "CAUSAL_DEPENDENCY_MISSING",
  "message": "Event depends on a chat that has not been accepted by central yet.",
  "status": 409,
  "category": "causal_ordering",
  "retryable": true
}
```

`conflictIds` is a compatibility field for helpers that only need rejected event
IDs. New clients should prefer `conflicts`, which includes stable codes,
categories, messages and retryability.

`retryable: true` means the event may become valid after a missing prerequisite
is fixed. Examples include `USER_NOT_FOUND` after users are seeded and
`CAUSAL_DEPENDENCY_MISSING` after the parent chat or message is accepted.

Central applies each batch in submitted order. For new events from the same
origin device, `logicalClock` must strictly advance beyond the latest accepted
event for that device. Replaying the same `eventId` is idempotent and returns
the existing central event as a duplicate.

## Pull Events

```txt
GET /api/sync/events?since=0&limit=500
```

Response:

```json
{
  "centralNodeId": "laravel-central",
  "latestSequence": 0,
  "limit": 500,
  "events": []
}
```

Pull sync is bounded. `limit` defaults to `DCR_SYNC_PULL_LIMIT` and is capped by
`DCR_MAX_SYNC_PULL_LIMIT`, so a helper cannot accidentally request the entire
event log in one response.

Events are returned in central sequence order. Event timestamps are stored in
PostgreSQL as `timestamptz` and returned as UTC ISO strings.

## Recovery Export

```txt
GET /api/recovery/export?userId=u-denis&deviceId=device-1
```

Recovery exports include:

```json
{
  "format": "durable-chat-recovery-v1",
  "latestSequence": 0,
  "eventCount": 0,
  "exportLimit": 10000,
  "truncated": false,
  "checksum": "sha256",
  "orderingPolicy": "central-sequence-ascending",
  "events": []
}
```

## Recovery Import

```txt
POST /api/recovery/import
```

Imports accept the same recovery format:

```txt
durable-chat-recovery-v1
```

When `checksum` is present, Laravel verifies it against the ordered `events`
array before importing. Imports reuse the same sync service as helper sync, so
replay remains idempotent by `eventId` and reports the same conflict taxonomy.
