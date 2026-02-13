#!/bin/bash
set -e

TAG="${1:-$(git describe --tags --always 2>/dev/null || echo 'dev')}"

echo "Writing version $TAG..."
echo "$TAG" > VERSION

echo "Updating Composer..."
composer self-update --quiet

echo "Installing dependencies..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "Ensuring data directory exists..."
mkdir -p data

echo "Setting permissions..."
chmod -R 775 data

echo "Deploy of $TAG complete."
