# PHP 8.x OOP design

The Laravel central server keeps HTTP concerns, application use cases, domain rules and PostgreSQL persistence in separate layers.

## HTTP layer

Controllers live in:

```txt
app/Http/Controllers
```

They read request data, call an application service and return JSON.

They do not own event validation, projection, idempotency or sync rules.

## Application layer

Use case services live in:

```txt
app/Application
```

Examples:

```txt
ApplyChatEventService
ListMessagesService
SyncConflictFactory
SyncEventsService
ExportRecoveryDumpService
ImportRecoveryDumpService
```

These classes coordinate complete application operations. They keep request handling separate from durable chat behaviour.

## Domain layer

Domain logic lives in:

```txt
app/Domain
```

Examples:

```txt
ChatEventDto
CausalOrderingPolicy
EventType
EventSyncStatus
EventValidator
EventProjector
DirectChatKeyFactory
DomainRuleException
```

Important rules live here:

- event IDs must use the expected device event format
- direct chats must have exactly two unique participants
- direct chat pair keys must be canonical
- message text cannot be empty
- event chat IDs must match payload chat IDs
- only active members can send or read messages
- only group owners can change group membership
- repeated events must be idempotent

## Infrastructure layer

Database implementations live in:

```txt
app/Infrastructure
```

Current classes:

```txt
PostgresChatProjectionRepository
PostgresChatQueryRepository
PostgresChatSummaryLoader
PostgresEventRepository
PostgresMessageHydrator
```

Application and domain code depend on interfaces rather than PostgreSQL implementation details.

```txt
EventRepositoryInterface
ChatProjectionRepositoryInterface
ChatQueryRepositoryInterface
```

This keeps sync rules testable and makes persistence boundaries explicit.

## PHP 8.x practices used

- Constructor property promotion
- Readonly DTOs
- Enums for event types and sync statuses
- Typed method arguments and return types
- Small final classes for focused responsibilities
- Explicit domain exceptions
- Repository interfaces for persistence boundaries
- Match expressions for event dispatch

## Central server boundary

Laravel is always the central authority. It stores the durable event log, validates sync batches, applies idempotency rules and projects chat state into PostgreSQL.

The original Node.js helper remains outside this codebase. It handles local realtime communication, retry and offline tolerant behaviour near the client.
