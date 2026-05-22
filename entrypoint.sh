#!/bin/bash
set -e

# Warn in deploy logs when auth/email env vars are missing (common Railway setup gap)
if [ -z "${GOOGLE_CLIENT_ID:-}" ]; then
    echo "WARNING: GOOGLE_CLIENT_ID is not set — website Google OAuth may fail; mobile app uses built-in client IDs."
fi
if [ -z "${JWT_PASSPHRASE:-}" ]; then
    echo "WARNING: JWT_PASSPHRASE is not set — JWT key generation and login tokens may fail."
fi
if [ -z "${GOOGLE_CLIENT_SECRET:-}" ]; then
    echo "WARNING: GOOGLE_CLIENT_SECRET is not set — Google Sign-In will fail."
fi
if [ -z "${GOOGLE_REDIRECT_URI:-}" ]; then
    echo "WARNING: GOOGLE_REDIRECT_URI is not set — Google Sign-In may fail (redirect_uri_mismatch)."
fi
if [ -z "${MAILER_DSN:-}" ] || [ "$MAILER_DSN" = "null://null" ]; then
    if [ -z "${BREVO_API_KEY:-}" ]; then
        echo "WARNING: MAILER_DSN and BREVO_API_KEY are not set — contact/verification email may not work."
    fi
fi

# JWT pem files are gitignored — generate on Railway/fresh deploy so /api/login and mobile Google auth work
echo "Ensuring JWT keypair exists..."
php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction 2>&1 || {
    echo "WARNING: lexik:jwt:generate-keypair failed — set JWT_SECRET_KEY paths and JWT_PASSPHRASE on Railway."
}

# Run production migrations automatically — capture stderr so failures are visible
echo "Running database migrations..."
MIGRATION_OUTPUT=$(php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1) || {
    echo "ERROR: Database migrations failed (exit code $?):"
    echo "$MIGRATION_OUTPUT"
    exit 1
}
echo "$MIGRATION_OUTPUT"

echo "Starting PHP-FPM..."
# Redirect both stdout and stderr to the container log so PHP fatal errors are visible
php-fpm -F 2>&1 &
PHP_PID=$!

# When this script exits for any reason, kill PHP-FPM so the container fully stops
cleanup() {
    echo "Shutting down PHP-FPM (pid $PHP_PID)..."
    kill "$PHP_PID" 2>/dev/null || true
    wait "$PHP_PID" 2>/dev/null || true
}
trap cleanup EXIT

# Wait for PHP-FPM to bind to port 9000 (up to 10 s) instead of a blind sleep
echo "Waiting for PHP-FPM to become ready..."
for i in $(seq 1 10); do
    if timeout 1 bash -c 'echo > /dev/tcp/127.0.0.1/9000' 2>/dev/null; then
        echo "PHP-FPM is ready (attempt $i)"
        break
    fi
    # Check whether the process already died while we were waiting
    if ! kill -0 "$PHP_PID" 2>/dev/null; then
        echo "ERROR: PHP-FPM exited unexpectedly during startup"
        exit 1
    fi
    echo "PHP-FPM not ready yet, waiting... ($i/10)"
    sleep 1
done

# Final check — if we exhausted the loop without a successful connect, abort
if ! timeout 1 bash -c 'echo > /dev/tcp/127.0.0.1/9000' 2>/dev/null; then
    echo "ERROR: PHP-FPM did not start within 10 seconds"
    exit 1
fi

# Watchdog: poll PHP-FPM liveness every 5 s; if it dies, stop Nginx so the
# container exits and Railway restarts it. This runs entirely via kill signals
# and never calls wait(), so it cannot trigger "not a child of this shell".
watchdog() {
    while true; do
        sleep 5
        if ! kill -0 "$PHP_PID" 2>/dev/null; then
            echo "ERROR: PHP-FPM (pid $PHP_PID) is no longer running — stopping Nginx"
            nginx -s quit 2>/dev/null || true
            return
        fi
    done
}
watchdog &
WATCHDOG_PID=$!

echo "Starting Nginx..."
# nginx -g "daemon off;" is the foreground process; the script blocks here.
# When Nginx exits (gracefully or otherwise), execution continues below.
nginx -g "daemon off;"

# Nginx has exited — stop the watchdog and let the EXIT trap clean up PHP-FPM
kill "$WATCHDOG_PID" 2>/dev/null || true
echo "Nginx has exited — container will stop"