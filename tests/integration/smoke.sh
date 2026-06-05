#!/usr/bin/env bash
# FurEver — end-to-end smoke test against a running stack (docker compose up -d).
#
# Steps:
#   1. Anonymous /dashboard      → 302 to /login
#   2. Login as admin            → 302 to /dashboard
#   3. /dashboard with cookie    → 200
#   4. /api/animals              → 200 + JSON with at least one animal
#   5. /users with non-admin     → 403
#   6. Logout                    → 302 to /login
#   7. /dashboard after logout   → 302 to /login

set -u

BASE_URL="${BASE_URL:-http://localhost:8080}"
COOKIE_JAR="$(mktemp)"
PASS=0
FAIL=0

trap 'rm -f "$COOKIE_JAR" "$COOKIE_JAR.adopter"' EXIT

assert_status() {
  local label="$1" expected="$2" actual="$3"
  if [ "$actual" = "$expected" ]; then
    printf "  \033[32m✔\033[0m  %s (HTTP %s)\n" "$label" "$actual"
    PASS=$((PASS+1))
  else
    printf "  \033[31m✘\033[0m  %s — expected HTTP %s, got %s\n" "$label" "$expected" "$actual"
    FAIL=$((FAIL+1))
  fi
}

# Strip "HTTP/1.1 100 Continue" preludes so awk only sees the final status code.
extract_status() { grep -E "^HTTP/[12]" "$1" | tail -n1 | awk '{print $2}'; }
extract_csrf() { grep -oE 'name="_csrf" value="[^"]+"' "$1" | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }

# 1. Anonymous /dashboard --------------------------------------------------------------------------
HEADERS="$(mktemp)"
curl -s -o /dev/null -D "$HEADERS" "$BASE_URL/dashboard"
assert_status "anonymous GET /dashboard redirects to /login" 302 "$(extract_status "$HEADERS")"

# 2. Fetch login form to capture CSRF + cookies, then POST credentials -----------------------------
LOGIN_FORM="$(mktemp)"
curl -s -c "$COOKIE_JAR" "$BASE_URL/login" -o "$LOGIN_FORM"
CSRF="$(extract_csrf "$LOGIN_FORM")"
[ -z "$CSRF" ] && { echo "  \033[31m✘\033[0m  could not extract CSRF token"; exit 1; }

curl -s -o /dev/null -D "$HEADERS" -b "$COOKIE_JAR" -c "$COOKIE_JAR" -L --max-redirs 0 \
  -X POST "$BASE_URL/login" \
  -d "_csrf=$CSRF" -d 'email=admin@furever.test' -d 'password=password'
assert_status "POST /login (admin) redirects to /dashboard" 302 "$(extract_status "$HEADERS")"

# 3. /dashboard with cookie ------------------------------------------------------------------------
curl -s -o /dev/null -D "$HEADERS" -b "$COOKIE_JAR" "$BASE_URL/dashboard"
assert_status "GET /dashboard as admin returns 200" 200 "$(extract_status "$HEADERS")"

# 4. /api/animals ----------------------------------------------------------------------------------
API_BODY="$(mktemp)"
curl -s -D "$HEADERS" -b "$COOKIE_JAR" "$BASE_URL/api/animals" -o "$API_BODY"
API_STATUS="$(extract_status "$HEADERS")"
assert_status "GET /api/animals returns 200" 200 "$API_STATUS"
if grep -q '"animals":' "$API_BODY"; then
  printf "  \033[32m✔\033[0m  /api/animals payload looks like JSON\n"
  PASS=$((PASS+1))
else
  printf "  \033[31m✘\033[0m  /api/animals body did not contain animals[]\n"
  FAIL=$((FAIL+1))
fi
rm -f "$API_BODY"

# 5. Non-admin → /users gives 403 ------------------------------------------------------------------
curl -s -c "$COOKIE_JAR.adopter" "$BASE_URL/login" -o "$LOGIN_FORM"
CSRF2="$(extract_csrf "$LOGIN_FORM")"
curl -s -o /dev/null -D "$HEADERS" -b "$COOKIE_JAR.adopter" -c "$COOKIE_JAR.adopter" --max-redirs 0 \
  -X POST "$BASE_URL/login" \
  -d "_csrf=$CSRF2" -d 'email=adopter@furever.test' -d 'password=password'

curl -s -o /dev/null -D "$HEADERS" -b "$COOKIE_JAR.adopter" "$BASE_URL/users"
assert_status "GET /users as adopter returns 403" 403 "$(extract_status "$HEADERS")"

# 6. Logout ----------------------------------------------------------------------------------------
LOGOUT_CSRF="$(curl -s -b "$COOKIE_JAR" "$BASE_URL/dashboard" | extract_csrf /dev/stdin)"
# /dashboard's CSRF lives in the meta tag; fall back to using session token from the form path:
LOGOUT_CSRF="$(curl -s -b "$COOKIE_JAR" "$BASE_URL/dashboard" | grep -oE 'meta name="csrf-token" content="[^"]+"' | head -1 | sed -E 's/.*content="([^"]+)".*/\1/')"
curl -s -o /dev/null -D "$HEADERS" -b "$COOKIE_JAR" -c "$COOKIE_JAR" --max-redirs 0 \
  -X POST "$BASE_URL/logout" \
  -d "_csrf=$LOGOUT_CSRF"
assert_status "POST /logout redirects" 302 "$(extract_status "$HEADERS")"

# 7. /dashboard after logout ----------------------------------------------------------------------
curl -s -o /dev/null -D "$HEADERS" -b "$COOKIE_JAR" "$BASE_URL/dashboard"
assert_status "GET /dashboard after logout redirects to /login" 302 "$(extract_status "$HEADERS")"

rm -f "$LOGIN_FORM" "$HEADERS"

echo
echo "Smoke results: $PASS passed, $FAIL failed."
exit $FAIL
