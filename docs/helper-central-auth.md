# Helper To Central Authorization

The original Node helper signs central sync requests with an HMAC signature. Laravel verifies the signature before accepting helper sync traffic.

This same contract is used by both central implementations:

```txt
Original helper -> Laravel central
Original helper -> original Node central
```

## Protected Laravel endpoints

```http
POST /api/sync/events
GET  /api/sync/events?since=...&limit=...
GET  /api/recovery/export
POST /api/recovery/import
```

Other demo/read endpoints such as health, readiness, config, users, chats and messages remain unsigned.

## Headers

The helper must send:

```txt
X-DCR-Helper-Id: helper-demo
X-DCR-Timestamp: 2026-05-22T00:00:00Z
X-DCR-Signature: <hex hmac sha256>
```

The signature payload is:

```txt
timestamp + "
" + method + "
" + path-with-query + "
" + raw-body
```

Example path values:

```txt
/api/sync/events
/api/sync/events?since=0&limit=200
/api/recovery/import?dryRun=true
```

For GET requests, the raw body is an empty string.

## Local configuration

Laravel `.env`:

```env
DCR_HELPER_SHARED_SECRET=local-dev-helper-secret
DCR_TRUSTED_HELPER_IDS=helper-demo
DCR_HELPER_SIGNATURE_TOLERANCE_SECONDS=300
```

Original helper command:

```bash
npm run helper:laravel
```

The helper command sets the same local secret. If you change the Laravel secret, change the helper environment too.

## Failure responses

Unsigned or invalid helper requests return `401` with a structured error code:

```txt
helper_signature_not_configured
unknown_helper
missing_helper_signature
invalid_helper_timestamp
stale_helper_signature
invalid_helper_signature
```

## Security scope

This is server to server authorization for helper sync. It proves that the request came from a helper that knows the shared secret.

It is not full user authentication, per chat authorization, signed browser events, message encryption or production key management.
