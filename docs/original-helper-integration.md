# Connect the original Node.js helper to Laravel central

This setup keeps the original helper and replaces only the old Node.js central backend.

```txt
Laravel central server on :8000
Original Node helper on :3001
Vue client on :1234
```

Laravel does not run in helper mode. The `NODE_ROLE` setting remains part of the original Node project only, because that project still needs to start the helper process.

## 1. Start Laravel central

From this Laravel project:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Check:

```bash
curl http://127.0.0.1:8000/api/health
```

Expected:

```json
{
  "ok": true,
  "service": "durable-chat-laravel-central",
  "centralNodeId": "laravel-central"
}
```

## 2. Start the original helper

From the original Durable Chat Relay project:

```bash
CENTRAL_URL=http://127.0.0.1:8000 npm run helper
```

The existing helper script normally sets:

```txt
NODE_ROLE=helper
PORT=3001
DATABASE_PATH=./data/helper.sqlite
```

`CENTRAL_URL` points the helper to Laravel instead of the old Node central server.

## 3. Start the existing Vue client

From the original project:

```bash
npm run dev:client
```

Open:

```txt
http://localhost:1234/?api=http://localhost:3001
```

The query parameter tells the frontend to talk to the helper on port `3001`.

## 4. Optional package script

Add this script to the original project's `package.json` for a clear Laravel backed helper command:

```json
"helper:laravel": "NODE_ROLE=helper PORT=3001 DATABASE_PATH=./data/helper.sqlite NODE_ID=helper-demo CENTRAL_URL=http://127.0.0.1:8000 tsx server/index.ts"
```

Then run:

```bash
npm run helper:laravel
```

## 5. What should not run

Do not run this as the central backend for this architecture:

```bash
npm run dev:central
```

That command starts the old Node central backend. The central authority is now Laravel and PostgreSQL.

## 6. Technical flow

When a user sends a message:

```txt
Vue client
  -> original Node helper through Socket.IO / local API
  -> helper stores locally in SQLite
  -> helper pushes pending events to Laravel POST /api/sync/events
  -> Laravel validates and stores events in PostgreSQL
  -> helper pulls missed central events from GET /api/sync/events?since=...
```

Laravel is the source of truth. The helper is the local resilience layer.

## 7. Smoke checks

Laravel central:

```bash
curl http://127.0.0.1:8000/api/users
```

Original helper:

```bash
curl http://127.0.0.1:3001/api/health
```

Frontend override:

```txt
http://localhost:1234/?api=http://localhost:3001
```

## 8. Bridge versus helper

A bridge only translates one protocol to another. The original helper is not just a bridge.

It provides local realtime behaviour, local SQLite durability, retry, pull sync and peer assisted recovery paths. Those responsibilities make it a local resilience layer.
