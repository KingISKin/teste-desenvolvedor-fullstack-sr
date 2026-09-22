#!/usr/bin/env bash
# End-to-end smoke test against the running docker compose stack, going
# through the public web service exactly like the SPA does:
#   login -> upload sample CSV -> poll import until finished -> assert totals.
#
# Expected values are computed independently from the CSV with awk. Totals are
# compared as deltas against a baseline, so the script is also re-runnable
# against a stack that already contains data.
#
# Usage: scripts/e2e-smoke.sh   (env: BASE_URL, DEMO_USER_EMAIL, DEMO_USER_PASSWORD,
#                                     CSV_FILE, IMPORT_TIMEOUT_SECONDS)
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080}"
EMAIL="${DEMO_USER_EMAIL:-demo@example.com}"
PASSWORD="${DEMO_USER_PASSWORD:-password}"
CSV_FILE="${CSV_FILE:-samples/financial_transactions.csv}"
TIMEOUT_SECONDS="${IMPORT_TIMEOUT_SECONDS:-300}"

log() { printf '[e2e] %s\n' "$*"; }
fail() {
    printf '[e2e] FAILED: %s\n' "$*" >&2
    exit 1
}

on_error() {
    local status=$?
    if [ "$status" -ne 0 ] && command -v docker > /dev/null 2>&1; then
        printf '\n[e2e] ---- docker compose logs (last 200 lines per service) ----\n' >&2
        docker compose logs --no-color --tail=200 >&2 || true
    fi
    exit "$status"
}
trap on_error EXIT

for tool in curl jq awk; do
    command -v "$tool" > /dev/null 2>&1 || fail "'$tool' is required."
done
[ -f "$CSV_FILE" ] || fail "CSV file not found: $CSV_FILE"

# ---------------------------------------------------------------------------
# Expected values straight from the CSV (%.0f: exact for integers < 2^53).
# ---------------------------------------------------------------------------
read -r EXPECTED_ROWS EXPECTED_INCOME EXPECTED_EXPENSE EXPECTED_BALANCE < <(
    awk -F, 'NR > 1 {
        gsub(/\r/, "")
        if ($0 == "") next
        rows++
        if ($4 == "Receita") income += $3
        else if ($4 == "Despesa") expense += $3
    } END { printf "%d %.0f %.0f %.0f\n", rows, income, expense, income - expense }' "$CSV_FILE"
)
log "Expected from CSV: rows=${EXPECTED_ROWS} income=${EXPECTED_INCOME} expense=${EXPECTED_EXPENSE} balance=${EXPECTED_BALANCE}"

# Performs a request and fails on an unexpected HTTP status. Prints the body.
request() {
    local expected_status=$1
    shift
    local body_file status
    body_file="$(mktemp)"
    status="$(curl -sS -o "$body_file" -w '%{http_code}' -H 'Accept: application/json' "$@")" || {
        rm -f "$body_file"
        fail "request failed: $*"
    }
    if [ "$status" != "$expected_status" ]; then
        cat "$body_file" >&2
        rm -f "$body_file"
        fail "expected HTTP ${expected_status}, got ${status}: $*"
    fi
    cat "$body_file"
    rm -f "$body_file"
}

assert_equals() {
    local label=$1 expected=$2 actual=$3
    [ "$expected" = "$actual" ] || fail "${label}: expected ${expected}, got ${actual}"
    log "OK ${label} = ${actual}"
}

# ---------------------------------------------------------------------------
log "Checking that the API rejects anonymous requests"
request 401 "${BASE_URL}/api/dashboard" > /dev/null

# Prints a fresh bearer token for the demo user.
login() {
    local token
    token="$(request 200 -X POST "${BASE_URL}/api/auth/login" \
        -H 'Content-Type: application/json' \
        -d "$(jq -n --arg email "$EMAIL" --arg password "$PASSWORD" '{email: $email, password: $password}')" |
        jq -r '.token')"
    [ -n "$token" ] && [ "$token" != "null" ] || fail "login did not return a token"
    printf '%s' "$token"
}

# Revokes the token and proves it can no longer be used.
logout() {
    local token=$1
    request 204 -H "Authorization: Bearer ${token}" -X POST "${BASE_URL}/api/auth/logout" > /dev/null
    request 401 -H "Authorization: Bearer ${token}" "${BASE_URL}/api/auth/me" > /dev/null
}

log "Logging in as ${EMAIL}"
TOKEN="$(login)"
AUTH=(-H "Authorization: Bearer ${TOKEN}")

# Baseline (this request also warms the dashboard cache, so the final check
# proves the cache is invalidated when the worker persists transactions).
BASELINE="$(request 200 "${AUTH[@]}" "${BASE_URL}/api/dashboard")"
BASE_INCOME="$(jq -r '.data.income' <<< "$BASELINE")"
BASE_EXPENSE="$(jq -r '.data.expense' <<< "$BASELINE")"
BASE_BALANCE="$(jq -r '.data.balance' <<< "$BASELINE")"
BASE_TOTAL="$(request 200 "${AUTH[@]}" "${BASE_URL}/api/transactions?per_page=1" | jq -r '.meta.total')"

log "Uploading ${CSV_FILE}"
IMPORT_ID="$(request 202 "${AUTH[@]}" -X POST "${BASE_URL}/api/imports" \
    -F "file=@${CSV_FILE};type=text/csv" | jq -r '.data.id')"
log "Import #${IMPORT_ID} queued"

deadline=$((SECONDS + TIMEOUT_SECONDS))
while :; do
    IMPORT="$(request 200 "${AUTH[@]}" "${BASE_URL}/api/imports/${IMPORT_ID}")"
    STATUS="$(jq -r '.data.status' <<< "$IMPORT")"
    log "status=${STATUS} processed=$(jq -r '.data.processed_rows' <<< "$IMPORT")/$(jq -r '.data.total_rows' <<< "$IMPORT")"

    case "$STATUS" in
        completed) break ;;
        failed) jq . <<< "$IMPORT" >&2; fail "import failed" ;;
    esac

    [ "$SECONDS" -lt "$deadline" ] || fail "import did not finish within ${TIMEOUT_SECONDS}s"
    sleep 2
done

assert_equals "import.total_rows" "$EXPECTED_ROWS" "$(jq -r '.data.total_rows' <<< "$IMPORT")"
assert_equals "import.processed_rows" "$EXPECTED_ROWS" "$(jq -r '.data.processed_rows' <<< "$IMPORT")"
assert_equals "import.failed_rows" "0" "$(jq -r '.data.failed_rows' <<< "$IMPORT")"

DASHBOARD="$(request 200 "${AUTH[@]}" "${BASE_URL}/api/dashboard")"
# jq arithmetic is exact for integers < 2^53; totals are compared as integers.
INCOME_DELTA="$(jq -r --argjson base "$BASE_INCOME" '.data.income - $base' <<< "$DASHBOARD")"
EXPENSE_DELTA="$(jq -r --argjson base "$BASE_EXPENSE" '.data.expense - $base' <<< "$DASHBOARD")"
BALANCE_DELTA="$(jq -r --argjson base "$BASE_BALANCE" '.data.balance - $base' <<< "$DASHBOARD")"

assert_equals "dashboard.income (delta)" "$EXPECTED_INCOME" "$INCOME_DELTA"
assert_equals "dashboard.expense (delta)" "$EXPECTED_EXPENSE" "$EXPENSE_DELTA"
assert_equals "dashboard.balance (delta)" "$EXPECTED_BALANCE" "$BALANCE_DELTA"

TOTAL="$(request 200 "${AUTH[@]}" "${BASE_URL}/api/transactions?per_page=1" | jq -r '.meta.total')"
assert_equals "transactions.meta.total (delta)" "$EXPECTED_ROWS" "$((TOTAL - BASE_TOTAL))"

log "Logging out"
logout "$TOKEN"

log "Logging in and out a second time"
SECOND_TOKEN="$(login)"
[ "$SECOND_TOKEN" != "$TOKEN" ] || fail "second login returned the revoked token"
request 200 -H "Authorization: Bearer ${SECOND_TOKEN}" "${BASE_URL}/api/auth/me" > /dev/null
logout "$SECOND_TOKEN"

log "Checking that the API rejects anonymous requests after logout"
request 401 "${BASE_URL}/api/transactions" > /dev/null

log "All end-to-end checks passed"
