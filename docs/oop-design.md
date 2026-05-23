# PHP 8.x OOP Design

Laravel handles routing, dependency injection, middleware, migrations and HTTP responses. The durable chat rules live in typed PHP classes with clear responsibilities.

```txt
Controller -> Application service -> Domain service / Policy -> Repository -> PostgreSQL
```

## Main boundaries

| Layer | Responsibility |
|---|---|
| Controllers | HTTP request and JSON response. |
| Middleware | HTTP boundary concerns such as helper signature verification. |
| Application services | Use case orchestration and transactions. |
| DTOs | Typed input/output boundaries between layers. |
| Enums | Controlled event and sync status values. |
| Policies | Membership and direct-chat rule checks. |
| Domain services | Event validation, projection and domain calculations. |
| Repositories | Persistence contracts. |
| Infrastructure | PostgreSQL query implementation and mapping. |

## Services

Application services keep controllers small:

```txt
SyncEventsService
ApplyChatEventService
ListMessagesService
ImportRecoveryDumpService
ExportRecoveryDumpService
```

These services coordinate validation, idempotency, persistence and projection. Controllers do not contain event business rules.

## Repositories and read/write split

The code separates write projection from read queries:

```txt
ChatProjectionRepositoryInterface   -> write/projection side
ChatListQueryRepositoryInterface    -> read/query side
MessageQueryRepositoryInterface     -> read/query side
UserQueryRepositoryInterface        -> read/query side
EventRepositoryInterface            -> durable event log
```

This lets read queries be optimised independently. For example, chat summaries can use PostgreSQL-specific latest-message queries without leaking that detail into domain services.

## Helper signature verifier

`HelperSignatureVerifier` owns helper-to-central trust checks:

```txt
known helper id
timestamp tolerance
raw request body signature
constant-time HMAC comparison
```

The HTTP middleware delegates to this class. That keeps the security rule testable outside the framework boundary.

## Direct pair key value object

`DirectPairKey` represents the canonical identity of a direct chat:

```txt
two unique users
sorted stable order
same pair always produces the same key
```

This avoids treating a direct pair key as a random string and supports multi-helper reconciliation.

## Policies

Rules that decide whether an action is allowed belong in policy-style classes, not in repositories.

Examples:

```txt
ChatMembershipPolicy
DirectChatPolicy
```

Repositories load data. Policies express business rules. Application services decide when to call them.

## Why there is no full pipeline yet

The event acceptance flow is currently simple enough to stay readable inside services:

```txt
validate -> idempotency check -> store event -> project read model -> build result
```

A full pipeline would be useful if there are many optional stages such as audit logging, moderation, rate limits or async queue processing. For this portfolio version, smaller services and policies give the value without adding unnecessary indirection.

## What this demonstrates

The project is not trying to show every OOP pattern. It uses OOP where it protects the important system behaviours:

```txt
signed helper sync
idempotent event acceptance
direct chat reconciliation
membership rule boundaries
PostgreSQL-backed event projection
recovery checksum validation
```
