#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/dcr-auth.sh"

body="$(python3 - <<'PYSCRIPT'
import hashlib
import json

events = [
    {
        "eventId": "device-3:event-1",
        "originNodeId": "helper-demo",
        "originDeviceId": "device-3",
        "actorUserId": "u-denis",
        "chatId": "chat-demo-3",
        "type": "chat.created",
        "payload": {
            "chatId": "chat-demo-3",
            "clientChatId": "client-chat-demo-3",
            "type": "direct",
            "memberIds": ["u-denis", "u-kate"],
        },
        "createdAt": "2026-05-20T10:05:00.000Z",
        "logicalClock": 1,
        "syncStatus": "local",
    }
]
canonical_events = json.dumps(events, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
dump = {
    "format": "durable-chat-recovery-v1",
    "exportedAt": "2026-05-20T10:05:00.000Z",
    "exportedBy": "u-denis",
    "deviceId": "browser-demo",
    "events": events,
    "checksum": hashlib.sha256(canonical_events.encode("utf-8")).hexdigest(),
}
print(json.dumps(dump, separators=(",", ":")))
PYSCRIPT
)"

printf '\n== Recovery dry run ==\n'
curl_dcr_post_json "$BASE_URL" '/api/recovery/import?dryRun=true' "$body" | python3 -m json.tool

printf '\nExpected: dryRun is true and no rows are written. Remove ?dryRun=true to apply the recovery import. Corrupt the events array without updating checksum to see a checksum mismatch.\n'
