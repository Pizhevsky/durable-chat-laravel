#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/dcr-auth.sh"

printf '\n== Health ==\n'
curl -sS "${BASE_URL}/api/health" | python3 -m json.tool

printf '\n== Readiness ==\n'
curl -sS "${BASE_URL}/api/readiness" | python3 -m json.tool

printf '\n== Sync chat.created ==\n'
chat_body='{"sourceNodeId":"helper-demo","events":[{"eventId":"device-1:event-1","originNodeId":"helper-demo","originDeviceId":"device-1","actorUserId":"u-denis","chatId":"chat-demo-1","type":"chat.created","payload":{"chatId":"chat-demo-1","clientChatId":"client-chat-demo-1","type":"direct","memberIds":["u-anna"]},"createdAt":"2026-05-20T10:00:00.000Z","logicalClock":1,"syncStatus":"local"}]}'
curl_dcr_post_json "$BASE_URL" '/api/sync/events' "$chat_body" | python3 -m json.tool

printf '\n== Sync message.created ==\n'
message_body='{"sourceNodeId":"helper-demo","events":[{"eventId":"device-1:event-2","originNodeId":"helper-demo","originDeviceId":"device-1","actorUserId":"u-denis","chatId":"chat-demo-1","type":"message.created","payload":{"chatId":"chat-demo-1","messageId":"message-demo-1","clientMessageId":"client-message-demo-1","text":"Hello through the helper path"},"createdAt":"2026-05-20T10:01:00.000Z","logicalClock":2,"syncStatus":"local"}]}'
curl_dcr_post_json "$BASE_URL" '/api/sync/events' "$message_body" | python3 -m json.tool

printf '\n== Read projected messages ==\n'
curl -sS "${BASE_URL}/api/chats/chat-demo-1/messages?userId=u-denis" | python3 -m json.tool

printf '\n== Pull central events after cursor 0 ==\n'
curl_dcr_get "$BASE_URL" '/api/sync/events?since=0&limit=50' | python3 -m json.tool
