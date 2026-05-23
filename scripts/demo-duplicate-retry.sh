#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/dcr-auth.sh"

body='{"sourceNodeId":"helper-demo","events":[{"eventId":"device-1:event-2","originNodeId":"helper-demo","originDeviceId":"device-1","actorUserId":"u-denis","chatId":"chat-demo-1","type":"message.created","payload":{"chatId":"chat-demo-1","messageId":"message-demo-1","clientMessageId":"client-message-demo-1","text":"Hello through the helper path"},"createdAt":"2026-05-20T10:01:00.000Z","logicalClock":2,"syncStatus":"local"}]}'

printf '\n== Retry same message event ==\n'
for attempt in 1 2 3; do
  printf '\nAttempt %s\n' "$attempt"
  curl_dcr_post_json "$BASE_URL" '/api/sync/events' "$body" | python3 -m json.tool
done

printf '\nExpected: first attempt may be accepted if not already synced. Later attempts should appear in duplicates, and the projected message should still exist once.\n'
