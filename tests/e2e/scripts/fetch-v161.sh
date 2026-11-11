#!/bin/sh
# Downloads v1.6.1 of the original BigBlueButton module (GPLv3, github.com/wenlanc/BigBlueButton) for the
# upgrade test. It is not part of this repository; it lands in the git-ignored .scratch/v161.
set -eu
root="$(cd "$(dirname "$0")/../../.." && pwd)"
target="$root/.scratch/v161"
rm -rf "$target"
git clone --quiet https://github.com/wenlanc/BigBlueButton.git "$target"
git -C "$target" checkout --quiet d3ae3e3 # last 1.x commit (1.6.1)
composer install --no-dev --no-interaction --quiet --working-dir="$target"
echo "v1.6.1 ready in $target"
