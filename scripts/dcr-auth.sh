#!/usr/bin/env bash

DCR_HELPER_ID="${DCR_HELPER_ID:-helper-demo}"
DCR_HELPER_SHARED_SECRET="${DCR_HELPER_SHARED_SECRET:-local-dev-helper-secret}"

sign_dcr_request() {
  local method="$1"
  local path="$2"
  local body="${3:-}"
  local timestamp
  local signature

  timestamp="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  signature="$(printf '%s\n%s\n%s\n%s' "$timestamp" "$method" "$path" "$body" | openssl dgst -sha256 -hmac "$DCR_HELPER_SHARED_SECRET" -r | awk '{print $1}')"

  printf '%s\n%s\n' "$timestamp" "$signature"
}

curl_dcr_get() {
  local base_url="$1"
  local path="$2"
  local auth
  local timestamp
  local signature

  auth="$(sign_dcr_request GET "$path" '')"
  timestamp="$(printf '%s' "$auth" | sed -n '1p')"
  signature="$(printf '%s' "$auth" | sed -n '2p')"

  curl -sS "${base_url}${path}" \
    -H "X-DCR-Helper-Id: ${DCR_HELPER_ID}" \
    -H "X-DCR-Timestamp: ${timestamp}" \
    -H "X-DCR-Signature: ${signature}"
}

curl_dcr_post_json() {
  local base_url="$1"
  local path="$2"
  local body="$3"
  local auth
  local timestamp
  local signature

  auth="$(sign_dcr_request POST "$path" "$body")"
  timestamp="$(printf '%s' "$auth" | sed -n '1p')"
  signature="$(printf '%s' "$auth" | sed -n '2p')"

  curl -sS -X POST "${base_url}${path}" \
    -H 'Content-Type: application/json' \
    -H "X-DCR-Helper-Id: ${DCR_HELPER_ID}" \
    -H "X-DCR-Timestamp: ${timestamp}" \
    -H "X-DCR-Signature: ${signature}" \
    -d "$body"
}
