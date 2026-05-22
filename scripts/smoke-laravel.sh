#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:8000}"

curl -s "${BASE_URL}/api/health" | jq .
curl -s "${BASE_URL}/api/users" | jq .
curl -s "${BASE_URL}/api/sync/events?since=0" | jq .
