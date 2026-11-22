#!/usr/bin/env bash
set -Eeuo pipefail

IMAGE="${SFM_TEST_IMAGE:-student-finance-manager:ci}"
CONTAINER_A="sfm-http-session-a-$$"
CONTAINER_B="sfm-http-session-b-$$"
BASE_A="http://127.0.0.1:18081"
BASE_B="http://127.0.0.1:18082"
EMAIL="scale-http-integration@example.test"
PASSWORD="Integration-Only-Password-123!"
COOKIE_JAR="$(mktemp)"
LOGIN_HTML="$(mktemp)"
LOGIN_RESPONSE="$(mktemp)"
SESSION_HEADERS="$(mktemp)"
SESSION_BODY="$(mktemp)"
APP_KEY_VALUE="${APP_KEY:-$(sed -n 's/^APP_KEY=//p' .env | head -n 1)}"
USER_ID=""
REQUEST_TMP_DIR="$(mktemp -d)"

cleanup() {
    local status=$?

    if [[ $status -ne 0 ]]; then
        echo "Multi-container HTTP session integration failed. Container logs follow." >&2
        docker logs "$CONTAINER_A" >&2 2>/dev/null || true
        docker logs "$CONTAINER_B" >&2 2>/dev/null || true
    fi

    docker rm -f "$CONTAINER_A" "$CONTAINER_B" >/dev/null 2>&1 || true

    if [[ -n "$USER_ID" ]]; then
        php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\DB::table("income")->where("user_id", (int) $argv[1])->delete();
        Illuminate\Support\Facades\DB::table("expenses")->where("user_id", (int) $argv[1])->delete();
        Illuminate\Support\Facades\DB::table("users")->where("id", (int) $argv[1])->delete();
        ' "$USER_ID" >/dev/null 2>&1 || true
    fi

    rm -f "$COOKIE_JAR" "$LOGIN_HTML" "$LOGIN_RESPONSE" "$SESSION_HEADERS" "$SESSION_BODY"
    rm -rf "$REQUEST_TMP_DIR"
    exit "$status"
}
trap cleanup EXIT

if [[ -z "$APP_KEY_VALUE" ]]; then
    echo "APP_KEY was not generated in the CI environment." >&2
    exit 1
fi

: "${DB_DATABASE:?DB_DATABASE must be set}"
: "${DB_USERNAME:?DB_USERNAME must be set}"
: "${DB_PASSWORD:?DB_PASSWORD must be set}"

USER_ID="$(php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::firstOrCreate(
    ["email" => "scale-http-integration@example.test"],
    ["fullname" => "Scale HTTP Integration", "password" => "Integration-Only-Password-123!"]
);
echo $user->getKey();
')"

start_app() {
    local name="$1"
    local host_port="$2"

    local docker_args=(
        --detach
        --name "$name"
        --add-host=host.docker.internal:host-gateway
        --publish "$host_port:10000"
        --env PORT=10000
        --env APP_ENV=testing
        --env APP_DEBUG=false
        --env APP_KEY="$APP_KEY_VALUE"
        --env APP_URL="http://127.0.0.1:$host_port"
        --env LOG_CHANNEL=stderr
        --env DB_CONNECTION=pgsql
        --env DB_HOST=host.docker.internal
        --env DB_PORT=5432
        --env DB_DATABASE="$DB_DATABASE"
        --env DB_USERNAME="$DB_USERNAME"
        --env DB_PASSWORD="$DB_PASSWORD"
        --env DB_SSLMODE=disable
        --env SESSION_DRIVER=redis
        --env SESSION_CONNECTION=session
        --env SESSION_COOKIE=sfm-http-integration-session
        --env SESSION_LIFETIME=120
        --env SESSION_ENCRYPT=true
        --env SESSION_SECURE_COOKIE=false
        --env CACHE_STORE=redis
        --env QUEUE_CONNECTION=redis
        --env REDIS_CLIENT=phpredis
        --env REDIS_HOST=host.docker.internal
        --env REDIS_PORT=6379
        --env REDIS_CACHE_DB=1
        --env REDIS_SESSION_DB=2
        --env REDIS_QUEUE_DB=3
        --env REDIS_CACHE_CONNECTION=cache
        --env REDIS_QUEUE_CONNECTION=queue
        --env REDIS_PREFIX=sfm-http-integration-database-
        --env CACHE_PREFIX=sfm-http-integration-cache-
        --env RUN_MIGRATIONS=false
    )

    docker run "${docker_args[@]}" "$IMAGE" >/dev/null
}

wait_for_login() {
    local base_url="$1"
    local container="$2"

    for attempt in $(seq 1 30); do
        if curl --silent --show-error --fail "$base_url/login" --output /dev/null; then
            return 0
        fi

        sleep 2
    done

    echo "Application container $container did not become ready." >&2
    docker logs "$container" >&2 || true
    return 1
}

start_app "$CONTAINER_A" 18081
start_app "$CONTAINER_B" 18082

wait_for_login "$BASE_A" "$CONTAINER_A"
wait_for_login "$BASE_B" "$CONTAINER_B"

login_page_args=(
    --silent
    --show-error
    --fail
    --cookie-jar "$COOKIE_JAR"
)
curl "${login_page_args[@]}" "$BASE_A/login" --output "$LOGIN_HTML"

CSRF_TOKEN="$(grep -oE 'name="_token" value="[^"]+"' "$LOGIN_HTML" | sed -E 's/.*value="([^"]+)"/\1/' | head -n 1)"

if [[ -z "$CSRF_TOKEN" ]]; then
    echo "Could not extract the login CSRF token." >&2
    exit 1
fi

login_args=(
    --silent
    --show-error
    --output "$LOGIN_RESPONSE"
    --write-out '%{http_code}'
    --cookie "$COOKIE_JAR"
    --cookie-jar "$COOKIE_JAR"
    --data-urlencode "_token=$CSRF_TOKEN"
    --data-urlencode "email=$EMAIL"
    --data-urlencode "password=$PASSWORD"
)
LOGIN_STATUS="$(curl "${login_args[@]}" "$BASE_A/login")"

if [[ "$LOGIN_STATUS" != "302" ]]; then
    echo "Login through container A returned HTTP $LOGIN_STATUS instead of 302." >&2
    cat "$LOGIN_RESPONSE" >&2
    exit 1
fi

session_args=(
    --silent
    --show-error
    --output "$SESSION_BODY"
    --dump-header "$SESSION_HEADERS"
    --write-out '%{http_code}'
    --cookie "$COOKIE_JAR"
    --cookie-jar "$COOKIE_JAR"
)
SESSION_STATUS="$(curl "${session_args[@]}" "$BASE_B/offline/session")"

if [[ "$SESSION_STATUS" != "200" ]]; then
    echo "Authenticated session request through container B returned HTTP $SESSION_STATUS." >&2
    cat "$SESSION_BODY" >&2
    exit 1
fi

SESSION_USER_ID="$(php -r '
$data = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
echo $data["user_id"] ?? "";
' < "$SESSION_BODY")"

if [[ "$SESSION_USER_ID" != "$USER_ID" ]]; then
    echo "Container B resolved user ID $SESSION_USER_ID; expected $USER_ID." >&2
    exit 1
fi

if ! grep --ignore-case --extended-regexp '^Cache-Control:.*no-store.*private' "$SESSION_HEADERS" >/dev/null; then
    echo "The authenticated offline session response did not include private no-store cache headers." >&2
    cat "$SESSION_HEADERS" >&2
    exit 1
fi

return_args=(
    --silent
    --show-error
    --output "$SESSION_BODY"
    --write-out '%{http_code}'
    --cookie "$COOKIE_JAR"
)
RETURN_STATUS="$(curl "${return_args[@]}" "$BASE_A/offline/session")"

if [[ "$RETURN_STATUS" != "200" ]]; then
    echo "Session did not remain valid when switching back to container A (HTTP $RETURN_STATUS)." >&2
    exit 1
fi

ANONYMOUS_STATUS="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "$BASE_B/offline/session")"

if [[ "$ANONYMOUS_STATUS" != "302" ]]; then
    echo "Unauthenticated request to container B returned HTTP $ANONYMOUS_STATUS instead of 302." >&2
    exit 1
fi

echo "PASS: authenticated Redis session created on container A was accepted by container B and remained valid on A."
echo "PASS: unauthenticated access was rejected and the offline session response was private/no-store."

# Read a fresh CSRF token after authentication; login regenerates the session token.
AUTH_HTML="$REQUEST_TMP_DIR/auth.html"
INCOME_A_BODY="$REQUEST_TMP_DIR/income-a.json"
INCOME_B_BODY="$REQUEST_TMP_DIR/income-b.json"
INCOME_REPLAY_BODY="$REQUEST_TMP_DIR/income-replay.json"
EXPENSE_A_BODY="$REQUEST_TMP_DIR/expense-a.json"
EXPENSE_B_BODY="$REQUEST_TMP_DIR/expense-b.json"
EXPENSE_REPLAY_BODY="$REQUEST_TMP_DIR/expense-replay.json"
INCOME_A_STATUS="$REQUEST_TMP_DIR/income-a.status"
INCOME_B_STATUS="$REQUEST_TMP_DIR/income-b.status"
EXPENSE_A_STATUS="$REQUEST_TMP_DIR/expense-a.status"
EXPENSE_B_STATUS="$REQUEST_TMP_DIR/expense-b.status"

curl --silent --show-error --fail --cookie "$COOKIE_JAR" "$BASE_A/income" --output "$AUTH_HTML"
AUTH_CSRF_TOKEN="$(grep -oE 'name="_token" value="[^"]+"' "$AUTH_HTML" | sed -E 's/.*value="([^"]+)"/\1/' | head -n 1)"

if [[ -z "$AUTH_CSRF_TOKEN" ]]; then
    echo "Could not extract an authenticated CSRF token for financial-write integration." >&2
    exit 1
fi

TODAY="$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo now(config("app.timezone"))->toDateString();')"
INCOME_TOKEN="$(cat /proc/sys/kernel/random/uuid)"
EXPENSE_TOKEN="$(cat /proc/sys/kernel/random/uuid)"

post_income() {
    local base_url="$1"
    local response_file="$2"

    curl --silent --show-error \
        --output "$response_file" \
        --write-out '%{http_code}' \
        --cookie "$COOKIE_JAR" \
        --header 'Accept: application/json' \
        --header "X-CSRF-TOKEN: $AUTH_CSRF_TOKEN" \
        --data-urlencode "submission_token=$INCOME_TOKEN" \
        --data-urlencode 'source=Concurrent CI Income' \
        --data-urlencode 'amount=1250.50' \
        --data-urlencode "transaction_date=$TODAY" \
        "$base_url/income"
}

post_expense() {
    local base_url="$1"
    local response_file="$2"

    curl --silent --show-error \
        --output "$response_file" \
        --write-out '%{http_code}' \
        --cookie "$COOKIE_JAR" \
        --header 'Accept: application/json' \
        --header "X-CSRF-TOKEN: $AUTH_CSRF_TOKEN" \
        --data-urlencode "submission_token=$EXPENSE_TOKEN" \
        --data-urlencode 'category=Concurrent CI Expense' \
        --data-urlencode 'amount=350.25' \
        --data-urlencode 'currency=KES' \
        --data-urlencode "transaction_date=$TODAY" \
        "$base_url/expenses"
}

post_income "$BASE_A" "$INCOME_A_BODY" > "$INCOME_A_STATUS" &
INCOME_A_PID=$!
post_income "$BASE_B" "$INCOME_B_BODY" > "$INCOME_B_STATUS" &
INCOME_B_PID=$!
wait "$INCOME_A_PID"
wait "$INCOME_B_PID"

INCOME_A_CODE="$(cat "$INCOME_A_STATUS")"
INCOME_B_CODE="$(cat "$INCOME_B_STATUS")"
if [[ "$INCOME_A_CODE" != "201" || "$INCOME_B_CODE" != "201" ]]; then
    echo "Concurrent income requests returned HTTP $INCOME_A_CODE and $INCOME_B_CODE; expected 201 for both." >&2
    cat "$INCOME_A_BODY" "$INCOME_B_BODY" >&2
    exit 1
fi

json_field() {
    php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    echo $data[$argv[2]] ?? "";
    ' "$1" "$2"
}

INCOME_A_ID="$(json_field "$INCOME_A_BODY" record_id)"
INCOME_B_ID="$(json_field "$INCOME_B_BODY" record_id)"
if [[ -z "$INCOME_A_ID" || "$INCOME_A_ID" != "$INCOME_B_ID" ]]; then
    echo "Concurrent income requests did not resolve to the same record ID ($INCOME_A_ID vs $INCOME_B_ID)." >&2
    exit 1
fi

# A conflicting replay must return the original row without changing its values.
INCOME_REPLAY_CODE="$(curl --silent --show-error --output "$INCOME_REPLAY_BODY" --write-out '%{http_code}' \
    --cookie "$COOKIE_JAR" --header 'Accept: application/json' --header "X-CSRF-TOKEN: $AUTH_CSRF_TOKEN" \
    --data-urlencode "submission_token=$INCOME_TOKEN" --data-urlencode 'source=Conflicting replay' \
    --data-urlencode 'amount=9999.99' --data-urlencode "transaction_date=$TODAY" "$BASE_B/income")"
if [[ "$INCOME_REPLAY_CODE" != "201" || "$(json_field "$INCOME_REPLAY_BODY" record_id)" != "$INCOME_A_ID" ]]; then
    echo "Conflicting income replay did not resolve to the original record." >&2
    cat "$INCOME_REPLAY_BODY" >&2
    exit 1
fi

INCOME_RECORD="$(php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$query = Illuminate\Support\Facades\DB::table("income")->where("user_id", (int) $argv[1])->where("submission_token", $argv[2]);
$record = $query->first();
echo json_encode(["count" => $query->count(), "id" => $record?->id, "amount" => $record?->amount, "source" => $record?->source], JSON_THROW_ON_ERROR);
' "$USER_ID" "$INCOME_TOKEN")"
INCOME_COUNT="$(php -r '$d=json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR); echo $d["count"];' "$INCOME_RECORD")"
INCOME_AMOUNT="$(php -r '$d=json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR); echo $d["amount"];' "$INCOME_RECORD")"
INCOME_SOURCE="$(php -r '$d=json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR); echo $d["source"];' "$INCOME_RECORD")"
if [[ "$INCOME_COUNT" != "1" || "$INCOME_AMOUNT" != "1250.50" || "$INCOME_SOURCE" != "Concurrent CI Income" ]]; then
    echo "Income idempotency invariant failed: $INCOME_RECORD" >&2
    exit 1
fi

post_expense "$BASE_A" "$EXPENSE_A_BODY" > "$EXPENSE_A_STATUS" &
EXPENSE_A_PID=$!
post_expense "$BASE_B" "$EXPENSE_B_BODY" > "$EXPENSE_B_STATUS" &
EXPENSE_B_PID=$!
wait "$EXPENSE_A_PID"
wait "$EXPENSE_B_PID"

EXPENSE_A_CODE="$(cat "$EXPENSE_A_STATUS")"
EXPENSE_B_CODE="$(cat "$EXPENSE_B_STATUS")"
if [[ ! "$EXPENSE_A_CODE" =~ ^(200|201)$ || ! "$EXPENSE_B_CODE" =~ ^(200|201)$ ]]; then
    echo "Concurrent expense requests returned HTTP $EXPENSE_A_CODE and $EXPENSE_B_CODE." >&2
    cat "$EXPENSE_A_BODY" "$EXPENSE_B_BODY" >&2
    exit 1
fi

EXPENSE_A_ID="$(json_field "$EXPENSE_A_BODY" record_id)"
EXPENSE_B_ID="$(json_field "$EXPENSE_B_BODY" record_id)"
if [[ -z "$EXPENSE_A_ID" || "$EXPENSE_A_ID" != "$EXPENSE_B_ID" ]]; then
    echo "Concurrent expense requests did not resolve to the same record ID ($EXPENSE_A_ID vs $EXPENSE_B_ID)." >&2
    exit 1
fi

EXPENSE_REPLAY_CODE="$(curl --silent --show-error --output "$EXPENSE_REPLAY_BODY" --write-out '%{http_code}' \
    --cookie "$COOKIE_JAR" --header 'Accept: application/json' --header "X-CSRF-TOKEN: $AUTH_CSRF_TOKEN" \
    --data-urlencode "submission_token=$EXPENSE_TOKEN" --data-urlencode 'category=Conflicting replay' \
    --data-urlencode 'amount=9999.99' --data-urlencode 'currency=KES' \
    --data-urlencode "transaction_date=$TODAY" "$BASE_B/expenses")"
if [[ "$EXPENSE_REPLAY_CODE" != "200" || "$(json_field "$EXPENSE_REPLAY_BODY" record_id)" != "$EXPENSE_A_ID" ]]; then
    echo "Conflicting expense replay did not resolve to the original record." >&2
    cat "$EXPENSE_REPLAY_BODY" >&2
    exit 1
fi

EXPENSE_RECORD="$(php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$query = Illuminate\Support\Facades\DB::table("expenses")->where("user_id", (int) $argv[1])->where("submission_token", $argv[2]);
$record = $query->first();
echo json_encode(["count" => $query->count(), "id" => $record?->id, "amount" => $record?->amount, "category" => $record?->category], JSON_THROW_ON_ERROR);
' "$USER_ID" "$EXPENSE_TOKEN")"
EXPENSE_COUNT="$(php -r '$d=json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR); echo $d["count"];' "$EXPENSE_RECORD")"
EXPENSE_AMOUNT="$(php -r '$d=json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR); echo $d["amount"];' "$EXPENSE_RECORD")"
EXPENSE_CATEGORY="$(php -r '$d=json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR); echo $d["category"];' "$EXPENSE_RECORD")"
if [[ "$EXPENSE_COUNT" != "1" || "$EXPENSE_AMOUNT" != "350.25" || "$EXPENSE_CATEGORY" != "Concurrent CI Expense" ]]; then
    echo "Expense idempotency invariant failed: $EXPENSE_RECORD" >&2
    exit 1
fi

echo "PASS: concurrent income requests across containers created one row and returned the same ID."
echo "PASS: concurrent expense requests across containers created one row and returned the same ID."
echo "PASS: conflicting replays did not overwrite the original income or expense."


if [[ "${SFM_RUN_BROWSER_E2E:-false}" == "true" ]]; then
    OFFLINE_E2E_TOKEN="$(cat /proc/sys/kernel/random/uuid)"
    BROWSER_SESSION_COOKIE="$(awk '$6 == "sfm-http-integration-session" { print $7; exit }' "$COOKIE_JAR")"
    if [[ -z "$BROWSER_SESSION_COOKIE" ]]; then
        echo "Could not extract the synthetic authenticated session cookie for Chromium." >&2
        exit 1
    fi

    echo "Running Chromium offline PWA lifecycle against the isolated CI container..."
    SFM_BROWSER_BASE_URL="$BASE_A" \
    SFM_BROWSER_SESSION_COOKIE="$BROWSER_SESSION_COOKIE" \
    SFM_OFFLINE_E2E_TOKEN="$OFFLINE_E2E_TOKEN" \
        node tests/Browser/offline-pwa.playwright.cjs

    OFFLINE_RECORD="$(php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $record = Illuminate\Support\Facades\DB::table("income")
        ->where("user_id", (int) $argv[1])
        ->where("submission_token", $argv[2])
        ->first();
    echo json_encode([
        "count" => Illuminate\Support\Facades\DB::table("income")->where("user_id", (int) $argv[1])->where("submission_token", $argv[2])->count(),
        "amount" => $record?->amount,
        "source" => $record?->source,
    ], JSON_THROW_ON_ERROR);
    ' "$USER_ID" "$OFFLINE_E2E_TOKEN")"

    php -r '
    $record = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
    if ($record["count"] !== 1 || $record["amount"] !== "17.25" || $record["source"] !== "Playwright Offline Income") {
        fwrite(STDERR, "Offline browser sync did not persist exactly one expected server-side income record: ".$argv[1].PHP_EOL);
        exit(1);
    }
    echo "PASS: offline browser submission persisted exactly once in PostgreSQL after reconnect.".PHP_EOL;
    ' "$OFFLINE_RECORD"
fi
