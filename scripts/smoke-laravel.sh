#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:8000}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/dcr-auth.sh"

curl -s "${BASE_URL}/api/health" | jq .
curl -s "${BASE_URL}/api/users" | jq .
curl_dcr_get "$BASE_URL" '/api/sync/events?since=0&limit=50' | jq .
