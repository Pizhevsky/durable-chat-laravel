# API Contract

## Central identity

Laravel responses use:

```json
{
  "centralNodeId": "laravel-central"
}
```

Laravel includes `nodeRole: "central"` and `nodeId` only as compatibility fields for the existing helper contract. They are fixed response fields, not runtime role configuration.

## Public endpoints

```http
GET /api/health
GET /api/readiness
GET /api/config
GET /api/users
GET /api/chats
GET /api/chats/{chatId}/messages
POST /api/events
```

`POST /api/events` is a demo direct event endpoint. The integrated helper path uses `/api/sync/events`.

## Signed helper endpoints

```http
POST /api/sync/events
GET  /api/sync/events?since=0&limit=200
GET  /api/recovery/export
POST /api/recovery/import
```

These endpoints require:

```txt
X-DCR-Helper-Id
X-DCR-Timestamp
X-DCR-Signature
```

The signature payload is:

```txt
timestamp + "
" + method + "
" + path-with-query + "
" + raw-body
```

See `docs/helper-central-auth.md`.

## Health

```http
GET /api/health
```

Checks that the Laravel app is running.

Example:

```json
{
  "ok": true,
  "service": "durable-chat-laravel",
  "centralNodeId": "laravel-central"
}
```

## Readiness

```http
GET /api/readiness
```

Checks that Laravel can reach PostgreSQL and the events table.

Example:

```json
{
  "ok": true,
  "service": "durable-chat-laravel",
  "centralNodeId": "laravel-central",
  "checks": {
    "database": "ok",
    "eventsTable": "ok"
  }
}
```

## Sync push

```http
POST /api/sync/events
```

Accepts helper event batches.

Request:

```json
{
  "sourceNodeId": "helper-demo",
  "events": [
    {
      "eventId": "device-1:event-1",
      "originNodeId": "helper-demo",
      "originDeviceId": "device-1",
      "actorUserId": "u-denis",
      "chatId": "chat-1",
      "type": "chat.created",
      "payload": {
        "chatId": "chat-1",
        "type": "direct",
        "clientChatId": "client-chat-1",
        "memberIds": ["u-anna"]
      },
      "createdAt": "2026-05-22T00:00:00.000Z",
      "logicalClock": 1,
      "syncStatus": "local"
    }
  ]
}
```

`createdAt` is the event timestamp supplied by the helper/client. Laravel stores
it as PostgreSQL `timestamptz` and returns it as a UTC ISO string. The central
event log also stores `client_created_at` and `central_received_at` separately,
so event time and central ingestion time are not conflated.

Response:

```json
{
  "accepted": ["device-1:event-1"],
  "duplicates": [],
  "conflicts": [],
  "serverEvents": [],
  "nodeRole": "central",
  "nodeId": "laravel-central",
  "centralNodeId": "laravel-central",
  "dryRun": false
}
```

`serverEvents` may contain authoritative central events that the helper must apply locally. This is important for direct chat reconciliation between several helpers.

Conflicts include a stable code, category and retryability hint:

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

Current categories are `validation`, `missing_reference`, `causal_ordering`,
`domain_rule`, `duplicate` and `temporary`.

Laravel enforces per-device logical clock advancement before accepting new
events. Events that depend on a chat or message central has not accepted yet
are rejected as `CAUSAL_DEPENDENCY_MISSING` so helpers can retry after the
missing prerequisite arrives.

## Sync pull

```http
GET /api/sync/events?since=0&limit=200
```

Returns central events after a sequence cursor.

Response:

```json
{
  "nodeRole": "central",
  "nodeId": "laravel-central",
  "centralNodeId": "laravel-central",
  "latestSequence": 10,
  "currentSequence": 12,
  "hasMore": true,
  "events": []
}
```

`latestSequence` is the last returned sequence in this response. The helper stores it as the next cursor. This prevents skipped events when there are more central events than one response limit.

## Recovery dry run

```http
POST /api/recovery/import?dryRun=true
```

Previews a recovery import without writing events or projections.

Recovery import uses the same signed helper authorization and the same sync
rules as a real import. Dry run executes the import path inside a rollback, so
validation, causal ordering, idempotency and projection conflicts match the
non-dry-run behaviour.

## SHA-256 recovery checksum

Recovery exports include a SHA-256 checksum calculated from the canonical events payload. Recovery import verifies this checksum before accepting or previewing events, so truncated or manually corrupted dumps are rejected instead of being applied silently.
