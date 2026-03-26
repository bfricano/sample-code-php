#!/bin/bash
set -euo pipefail

# Only run in remote (Claude Code on the web) environments
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "$CLAUDE_PROJECT_DIR"

# Fix malformed version alias in composer.json for Composer 2.x compatibility
# "xsd2php-dev" is not a valid version; it should be "dev-xsd2php"
if [ -f "composer.json" ] && grep -q '"xsd2php-dev as' composer.json; then
  sed -i 's/"xsd2php-dev as/"dev-xsd2php as/' composer.json
fi

# Fix SSH repository URL to HTTPS (SSH not available in container)
if [ -f "composer.json" ] && grep -q 'git@github.com:' composer.json; then
  sed -i 's|git@github.com:|https://github.com/|' composer.json
fi

# Install PHP dependencies via Composer
# --no-dev: skip dev dependencies that require GitHub auth for private VCS repos
# --ignore-platform-reqs: legacy SDK targets PHP ~5.3 but we run PHP 8.x
if [ -f "composer.json" ]; then
  composer install --no-interaction --no-progress --prefer-dist --ignore-platform-reqs --no-dev
fi
