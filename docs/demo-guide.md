# Demo Guide

## Demo purpose

The demo shows a user-facing resilience story:

```txt
users keep working through a local helper while central connectivity is unreliable;
when central returns, pending events retry safely and official chat state converges.
```

Laravel's role in that story is the central sync authority for the existing helper architecture.

The main behaviours to show are:

- signed helper sync
- duplicate retry safety
- projected central chat state
- direct chat duplicate protection
- multi helper direct chat reconciliation
- central event pull by cursor
- recovery import dry run
- database readiness check

## Start Laravel

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

The seed data creates demo users used by the original Durable Chat Relay client and helper:

```txt
u-denis
u-anna
u-mark
u-kate
u-ivan
```

## Start original helper and client

From the original project:

```bash
npm install
npm run dev:laravel
```

Open:

```txt
http://localhost:1234?api=http://localhost:3001
```

## Demo 1 — central health and readiness

```bash
curl http://127.0.0.1:8000/api/health
curl http://127.0.0.1:8000/api/readiness
```

What it proves:

```txt
Laravel is running and can reach PostgreSQL.
```

## Demo 2 — signed helper contract sync

Run:

```bash
scripts/demo-sync.sh
```

This script signs its sync requests and then:

1. checks health
2. checks readiness
3. posts a `chat.created` event
4. posts a `message.created` event
5. reads projected messages
6. pulls central events after cursor `0`

What it proves:

```txt
The helper contract is signed, accepted by Laravel, and projected into central read state.
```

## Demo 3 — retry the same event

Run after `scripts/demo-sync.sh`:

```bash
scripts/demo-duplicate-retry.sh
```

Expected central behaviour:

```txt
first unseen event -> accepted
same event again  -> duplicates
message row       -> still one
```

## Demo 4 — recovery dry run

Run:

```bash
scripts/demo-recovery-dry-run.sh
```

Expected response:

```json
{
  "accepted": ["device-3:event-1"],
  "duplicates": [],
  "conflicts": [],
  "dryRun": true
}
```

The event is not written while `dryRun=true`.

## Demo 5 — original helper integration

With Laravel running and `npm run dev:laravel` active:

1. open the Vue app through `http://localhost:1234?api=http://localhost:3001`
2. create a direct chat
3. send a message
4. check Laravel `events` and `messages` tables
5. stop Laravel temporarily
6. send another helper local event
7. restart Laravel
8. let helper sync retry

Expected result:

```txt
helper signs retry requests
Laravel accepts pending events after it returns
central events are stored once
messages are projected once
```

## Demo 6 — multi helper reconciliation

Use two helper databases or two helper ports if you want to demonstrate the full conflict scenario:

```txt
Helper A creates Denis + Anna direct chat while central is unavailable.
Helper B creates the same direct chat while central is unavailable.
Laravel comes back.
Helper A syncs first.
Helper B syncs second.
```

Expected result:

```txt
one central direct chat
one canonical direct pair key
losing local chat id remapped to the central chat id
pending messages rewritten before retry
```

## Clean reset

Before repeating reconciliation demos, reset:

```txt
Laravel PostgreSQL database
helper SQLite database or databases
browser localStorage/sessionStorage/IndexedDB
```
