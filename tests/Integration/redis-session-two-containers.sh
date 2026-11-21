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
        Illuminate\Support\Facades\DB::table("users")->where("id", (int) $argv[1])->delete();
        ' "$USER_ID" >/dev/null 2>&1 || true
    fi

    rm -f "$COOKIE_JAR" "$LOGIN_HTML" "$LOGIN_RESPONSE" "$SESSION_HEADERS" "$SESSION_BODY"
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
