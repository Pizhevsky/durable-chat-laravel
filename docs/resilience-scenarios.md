# Resilience scenarios

These scenarios describe the behaviour this architecture is designed to support.

## 1. Laravel central is unavailable

Expected behaviour:

```txt
User sends message
Helper stores event locally in SQLite
Helper keeps retrying central sync
Laravel returns later
Helper pushes pending events
Laravel accepts new events and marks them central synced
```

## 2. Helper repeats the same event

Expected behaviour:

```txt
Helper sends the same event twice
Laravel stores it once
Second sync returns duplicate
PostgreSQL event log remains stable
```

Duplicate detection is backed by central constraints. Event ID replays and
duplicate direct-chat pair attempts are returned as duplicates instead of being
stored twice.

## 3. Direct chat is created from two clients

Expected behaviour:

```txt
Client A creates direct chat Denis Anna
Client B creates direct chat Anna Denis
Laravel canonical direct pair key prevents duplicate direct chats
```

## 4. Client refreshes after outage

Expected behaviour:

```txt
Client asks helper for state
Helper pulls missed central events from Laravel
Helper applies them locally
UI rebuilds from local helper read model
```

## 5. Recovery import

Expected behaviour:

```txt
Recovery dump contains events
Laravel validates format
Valid unseen events are accepted
Already seen events are treated as duplicates
Invalid events are conflicts
```

Recovery exports include a checksum over the ordered event array. Imports verify
that checksum when present before replaying events through the normal sync path.

## 6. Retryable conflicts

Expected behaviour:

```txt
Laravel rejects chat.created with USER_NOT_FOUND
Central users are seeded
Helper requeues or recreates the rejected event
Laravel accepts chat.created
Dependent message events can then be retried
```

`retryable: true` means the event may become valid after a missing prerequisite
is fixed. It does not mean Laravel accepted the event, and it does not force a
helper that already marked the event as conflict to resend it automatically.

## 7. Bounded pull sync

Expected behaviour:

```txt
Helper asks for missed central events
Laravel returns events after the requested sequence
Response is capped by limit
Helper repeats pull sync with the latest sequence it has applied
```

This keeps normal sync responses bounded and leaves room for chunked catch-up on
large event logs.

## 8. Conflict visibility

Expected behaviour:

```txt
Laravel rejects an event
Response includes eventId, code, message, HTTP-style status, category and retryable
Server logs include syncAttemptId, sourceNodeId, eventId and conflict code
Operator can connect client-visible conflicts with server logs
```
