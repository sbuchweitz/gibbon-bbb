#!/bin/sh
# Rebuilds the committed vendor/ (runtime dependencies only) and strips files the module never loads.
# Run from anywhere; CI runs it and fails if vendor/ changes (drift check).
set -eu
cd "$(dirname "$0")/.."
# A fixed root version keeps vendor/composer/installed.php independent of the current git commit.
version=$(sed -n "s/^\$moduleVersion = '\(.*\)';/\1/p" version.php)
COMPOSER_ROOT_VERSION="$version" composer install --no-dev --optimize-autoloader --no-interaction --no-progress
lib=vendor/bigbluebutton/bigbluebutton-api-php
# Keep only what the module loads (src/), plus the library's licence and metadata.
rm -rf "$lib/tests" "$lib/docs" "$lib/dev" "$lib/captainhook.json" "$lib/phpstan.neon" "$lib/phpunit.xml.dist" \
    "$lib/CHANGELOG.md" "$lib/.env" "$lib/.github" "$lib/.gitignore" "$lib/.php-cs-fixer.php" "$lib/.scrutinizer.yml"
echo "vendor/ rebuilt: $(du -sh vendor | cut -f1)"
