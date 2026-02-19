#!/bin/sh
set -e

APP_DIR=/var/www/html

# First-run: bootstrap Symfony skeleton
if [ ! -f "$APP_DIR/composer.json" ]; then
    echo "==> No Symfony project found. Creating new Symfony application..."

    TMPDIR=$(mktemp -d)
    composer create-project symfony/skeleton "$TMPDIR" --prefer-dist --no-interaction

    # Copy into the mounted volume
    cp -r "$TMPDIR/." "$APP_DIR/"
    rm -rf "$TMPDIR"

    cd "$APP_DIR"

    echo "==> Installing Doctrine ORM pack and supporting bundles..."
    composer require \
        symfony/orm-pack \
        symfony/maker-bundle \
        nelmio/cors-bundle \
        --no-interaction

    echo "==> Symfony application ready."
fi

cd "$APP_DIR"

# Install / sync dependencies whenever the container starts
if [ -f composer.json ]; then
    composer install --prefer-dist --no-interaction --optimize-autoloader
fi

exec "$@"
