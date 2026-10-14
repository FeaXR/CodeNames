#!/bin/sh
# Runs before the image's Laravel automations (migrations, caching).
set -e

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Generate one with:" >&2
    echo '  echo "base64:$(openssl rand -base64 32)"' >&2
    exit 1
fi

# Make sure the SQLite database exists on the persistent volume.
if [ "$DB_CONNECTION" = "sqlite" ] && [ ! -f "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
    echo "Created SQLite database at $DB_DATABASE"
fi
