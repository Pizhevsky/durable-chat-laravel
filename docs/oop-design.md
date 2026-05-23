# PHP 8.x OOP Design

## Design goal

Laravel handles routing, dependency injection, middleware, migrations and HTTP responses. The durable chat rules live in typed PHP classes.

```txt
Controller -> Application service -> Domain service -> Repository -> PostgreSQL
```

## Main boundaries

| Layer | Responsibility |
|---|---|
| Controllers | HTTP request and JSON response |
| Application services | Use case orchestration |
| DTOs | Explicit typed boundaries between layers |
| Enums | Controlled event and status values |
| Domain services | Validation, projection, pair key rules |
| Repositories | Persistence contracts |
| Infrastructure | PostgreSQL/Eloquent query implementation |

## Thin controllers

Controllers do not contain projection rules or database invariants. For example, `SyncController` reads the incoming `events` array, calls `SyncEventsService`, and returns the result.

That keeps controller code small and makes sync rules easier to test.

## Application services

Important use cases are represented by application services:

```txt
SyncEventsService
ApplyChatEventService
ExportRecoveryDumpService
ImportRecoveryDumpService
RecoveryChecksum
```

These classes coordinate validation, persistence and projection.

## DTOs

DTOs make event boundaries explicit:

```txt
ChatEventDto
ApplyEventResultDto
SyncEventsResultDto
```

The event DTO carries the central sync contract:

```txt
event id
origin node id
origin device id
actor user id
chat id
event type
payload
created time
logical clock
sync status
```

## Enums

Enums avoid uncontrolled string values inside the domain layer:

```txt
EventType
EventSyncStatus
ChatType
```

PostgreSQL check constraints also protect the same controlled values below application code.

## Domain services

The most important domain services are:

```txt
EventValidator
EventPayloadValidator
EventPayloadFields
EventProjector
EventProjectionRules
DirectChatKeyFactory
```

`EventValidator` checks event envelope fields before projection. `EventPayloadValidator`
and `EventPayloadFields` keep event-specific payload rules and primitive field checks
separate.

`EventProjector` updates read models from accepted events. `EventProjectionRules`
holds reusable guard checks such as active membership, known users and group
ownership.

`DirectChatKeyFactory` creates a canonical sorted pair key so a direct chat has one identity regardless of participant order.

## Repository interfaces

Application and domain services depend on interfaces:

```txt
EventRepositoryInterface
ChatProjectionRepositoryInterface
UserQueryRepositoryInterface
ChatListQueryRepositoryInterface
MessageQueryRepositoryInterface
```

The PostgreSQL implementation sits under:

```txt
app/Infrastructure
```

This keeps the central sync logic from depending directly on controller code or raw database calls.

Query-side infrastructure is split by read responsibility:

```txt
PostgresUserQueryRepository
PostgresChatListQueryRepository
PostgresMessageQueryRepository
PostgresChatMemberQuery
PostgresChatSummaryQuery
PostgresMessageHydrator
PostgresEventMapper
PostgresProjectionMapper
PostgresDateTime
```

That keeps user listing, chat summaries, message reads, member loading and
message/event hydration from accumulating in one broad repository.

## Database constraints as part of design

The project does not rely only on service code for correctness. The migration adds constraints for:

- unique event ids
- unique direct pair keys
- unique chat membership rows
- unique message ids
- controlled event types
- controlled sync statuses
- non-negative logical clocks

That is important for a resilient sync system because retries, recovery imports and helper reconnects can repeat the same logical action.

## Tests that protect the design

| Test | Purpose |
|---|---|
| `HelperContractTest` | Verifies helper-facing API response shape |
| `IdempotencyProjectionTest` | Verifies duplicate retries do not duplicate projections |
| `DirectChatDuplicateProtectionTest` | Verifies same direct pair creates one chat |
| `PostgresConstraintTest` | Verifies database constraints protect invariants |
| `RecoveryDryRunTest` | Verifies recovery preview does not write rows |
| `EventValidatorTest` | Verifies invalid domain payloads are rejected |
| `DirectChatKeyFactoryTest` | Verifies pair key canonicalisation |

## Why this matters

The project is small enough to read, but structured like a real backend component. The code shows that the central server has clear responsibilities, explicit contracts and protected invariants.
