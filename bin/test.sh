#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

PHPUNIT_ARGS=("$@")

if [ ${#PHPUNIT_ARGS[@]} -eq 0 ]; then
    PHPUNIT_ARGS=(--no-coverage)
fi

docker compose build php
docker compose run --rm php vendor/bin/phpunit "${PHPUNIT_ARGS[@]}"
