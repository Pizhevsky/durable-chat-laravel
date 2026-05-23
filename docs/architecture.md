# Architecture

## Purpose

This Laravel service is the central HTTP authority for Durable Chat Relay. It is designed to work with the original Node helper rather than replace it.

The original project has several resilience layers:

```txt
Vue client
Node helper
browser IndexedDB recovery
peer assisted WebRTC fallback
optional original Node central server
```

This project focuses on one layer only:

```txt
Laravel central server backed by PostgreSQL
```

## Runtime shape with Laravel central

```txt
Vue client :1234
   |
   | Socket.IO and helper facing API
   v
Original Node helper :3001
   |
   | signed POST /api/sync/events
   | signed GET  /api/sync/events?since=...
   v
Laravel central API :8000
   |
   v
PostgreSQL
```

Laravel is always central. It does not run as a helper and it does not expose helper role configuration. It keeps fixed `nodeRole: "central"` and `nodeId` response fields only for compatibility with the original helper contract.

## Responsibility split

| Concern | Owner |
|---|---|
| Socket.IO connection with Vue client | Original Node helper |
| Local helper queue and retry | Original Node helper |
| Browser IndexedDB recovery | Original Vue/browser layer |
| WebRTC peer assisted path | Original helper and browser layer |
| Helper request signing | Original Node helper |
| Helper signature verification | Laravel central |
| Authoritative event storage | Laravel central + PostgreSQL |
| Event validation | Laravel domain layer |
| Idempotency | Laravel sync service and database constraints |
| Chat/message projection | Laravel projector and repositories |
| Recovery import/export | Laravel central API |
| Read APIs for central state | Laravel central API |

## Event flow

Normal helper sync:

```txt
1. User action creates a local event.
2. Helper stores or forwards the event.
3. Helper signs the sync request with its helper id, timestamp and HMAC signature.
4. Laravel verifies the helper signature.
5. Laravel validates the event.
6. Laravel stores the event once by event id.
7. Laravel projects read models such as chats and messages.
8. Helper can pull missed central events by sequence cursor.
```

Retry flow:

```txt
Helper sends event device-1:event-2.
Laravel stores and projects it.
The network response is lost.
Helper retries device-1:event-2.
Laravel returns it as duplicate.
The message still exists once.
```

Pull cursor flow:

```txt
Helper requests events after sequence 0 with limit 200.
Laravel returns at most 200 events.
latestSequence is the last returned sequence, not the current database maximum.
Helper stores latestSequence as its cursor.
If hasMore is true, helper can pull again without skipping events.
```

## Central event model

A central event has a stable id and source metadata:

```json
{
  "eventId": "device-1:event-2",
  "originNodeId": "helper-demo",
  "originDeviceId": "device-1",
  "actorUserId": "u-denis",
  "chatId": "chat-1",
  "type": "message.created",
  "payload": {
    "chatId": "chat-1",
    "messageId": "message-1",
    "clientMessageId": "client-message-1",
    "text": "Hello through the helper path"
  },
  "createdAt": "2026-05-20T10:01:00.000Z",
  "logicalClock": 2,
  "syncStatus": "local"
}
```

`originNodeId` and `originDeviceId` describe where the event came from. They do not mean Laravel can become a helper.

`createdAt` is the client/helper event timestamp from the sync payload. Laravel
stores it in PostgreSQL as `timestamptz`, mirrors it into `events.client_created_at`,
and records central ingestion time separately in `events.central_received_at`.
API responses return the event timestamp as a UTC ISO string.

## Multi helper direct chat reconciliation

Direct chats use a canonical pair key based on sorted participant ids.

```txt
u-anna:u-denis
```

If several helpers create the same direct chat offline, Laravel keeps one central chat id and returns the accepted central `chat.created` event to later helpers through `serverEvents` or pull sync.

Expected result:

```txt
one direct chat in PostgreSQL
one central chat id used by all helpers after reconciliation
pending helper events rewritten from the losing local chat id to the central chat id
browser state reconciled to the central chat id
```

## PostgreSQL invariants

Important rules are protected below the service layer:

| Invariant | Protection |
|---|---|
| Event id is stored once | unique `events.event_id` |
| Direct 1:1 chat pair is unique | unique direct pair key constraint/index |
| Chat member appears once per chat | key on `chat_members(chat_id, user_id)` |
| Message id is stored once | key on `messages.id` |
| Sender client message id is stable | unique sender/client message id rule |
| Message read state is unique per user/message | key on `message_reads(message_id, user_id)` |

Timestamp columns use PostgreSQL `timestamptz` rather than strings. This keeps
ordering, filtering and timezone conversion in the database instead of relying
on lexicographic ISO string sorting.

## Security boundary

The Laravel central server verifies helper sync signatures. This protects helper to central traffic in the local integration setup.

It does not replace full production security. A production system would still need real user authentication, user authorization, signed device events, key rotation, message encryption, rate limiting, monitoring and deployment hardening.
