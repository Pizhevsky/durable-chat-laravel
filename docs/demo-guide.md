# Demo Guide

This project is designed to be demonstrated together with the original Durable Chat Relay helper.

The demo has three levels:

- **Central API demo**: signed helper-style requests, idempotent event sync, recovery checksum validation and PostgreSQL projection.
- **Helper integration demo**: the original Vue client and Node helper running against Laravel as the central server.
- **Resilience demo**: central outage, helper local storage, retry with backoff, and central reconciliation when Laravel returns.

The original project owns the browser-facing runtime. This Laravel project owns the central HTTP authority and PostgreSQL durable event store.

## Start clean

Use a fresh PostgreSQL database or reset it before recording a demo:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan test
```

Start Laravel:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Check the central server:

```bash
curl http://127.0.0.1:8000/api/health
curl http://127.0.0.1:8000/api/readiness
```

What to point out:

| Visible result | Technical activity |
|---|---|
| Health endpoint responds | Laravel central process is running. |
| Readiness endpoint responds | Laravel can reach PostgreSQL and required tables. |
| `centralNodeId` is stable | The helper knows which central authority accepted sync. |

## Signed API demo scripts

With Laravel running, use the local scripts:

```bash
scripts/demo-sync.sh
scripts/demo-duplicate-retry.sh
scripts/demo-recovery-dry-run.sh
```

What to point out:

| Visible result | Technical activity |
|---|---|
| Unsigned helper sync is rejected | Laravel verifies HMAC helper headers. |
| Signed sync is accepted | Helper identity, timestamp and raw body signature match. |
| Duplicate retry does not duplicate state | Laravel stores each event once by `eventId`. |
| Recovery dry run reports what would happen | Import can validate before writing. |
| Corrupted recovery data is rejected | SHA-256 checksum is verified before import. |

Suggested explanation:

> The helper is trusted as a local relay, but central still verifies that sync requests were signed by a known helper. The browser never receives this secret.

## Laravel central with original helper

Terminal 1, in this repository:

```bash
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2, in the original Durable Chat Relay repository:

```bash
npm install
npm run dev:laravel
```

Open:

```txt
http://localhost:1234?api=http://localhost:3001
```

Demo steps:

1. Select **Denis** as the current demo user.
2. Open Anna in another window.
3. Create a direct chat through the helper.
4. Send a message.
5. Confirm it appears in the other window.
6. Check Laravel read APIs or PostgreSQL tables to confirm central projection.

What to point out:

| Visible behaviour | Technical activity |
|---|---|
| Browser still uses Socket.IO | The Node helper remains browser-facing. |
| Laravel receives signed sync | Helper sends HMAC-signed HTTP batches. |
| PostgreSQL stores official history | Laravel is the central authority. |
| Events remain retry safe | `eventId` is the idempotency key. |
| Direct chat is stable | The canonical pair key prevents duplicate 1:1 chats. |

## Central outage while helper is available

This is the most important Laravel integration demo.

Demo steps:

1. Start Laravel and the original helper with `npm run dev:laravel`.
2. Send one message normally.
3. Stop Laravel.
4. Keep the helper and browser running.
5. Send more messages through the helper.
6. Restart Laravel.
7. Wait for helper sync retry.
8. Confirm pending events are accepted once and appear in central history.

What to point out:

| Visible behaviour | Technical activity |
|---|---|
| Users continue through helper | Helper stores events locally in SQLite. |
| Sync does not tight-loop | Helper retries with backoff. |
| Laravel accepts events after recovery | Helper signs pending batches. |
| Duplicates are safe | Laravel deduplicates by `eventId`. |
| PostgreSQL has the official history | Central projection catches up after outage. |

Suggested explanation:

> The helper gives the local office a place to keep working. Laravel does not need to be available for every user action, but it remains the authority that later validates and stores official history.

## Multi-helper direct chat reconciliation

This demo is for the most important distributed edge case.

Scenario:

```txt
Helper A creates direct chat chat-a for Denis + Anna while offline.
Helper B creates direct chat chat-b for Denis + Anna while offline.
Central receives one first and makes it authoritative.
The other helper syncs later and remaps its local chat id to the authoritative central chat id.
```

Expected result:

```txt
one central direct chat
one canonical direct pair key
pending messages rewritten to the authoritative chat id
no duplicate direct chat in final read APIs
```

What to point out:

| Behaviour | Technical activity |
|---|---|
| Same pair converges to one chat | Direct pair key is canonical and sorted. |
| Losing helper remaps local chat id | Central returns authoritative server events. |
| Pending messages keep syncing | Events are rewritten to the central chat id before retry. |
| Read APIs show one chat | PostgreSQL constraints protect the invariant. |

## Recovery dry run and checksum validation

Demo steps:

1. Export a recovery dump through the API or helper flow.
2. Run a dry-run import.
3. Change one event field in the JSON dump without updating the checksum.
4. Try to import again.

What to point out:

| Visible result | Technical activity |
|---|---|
| Valid dump is accepted in dry-run mode | Laravel validates without writing. |
| Corrupted dump is rejected | Import verifies SHA-256 checksum. |
| Known events are skipped | Recovery import is idempotent. |

## PostgreSQL checks

Useful tables to inspect:

```txt
events
chats
chat_members
messages
message_reads
sync_states
```

Look for:

```txt
one row per event_id
one direct chat per direct_pair_key
messages projected once
latest message query using PostgreSQL rather than PHP grouping
recovery checksum verification before import
```
