#!/bin/sh
# Generates a self-signed TLS certificate for local development.
# The cert is placed in docker/nginx/ssl/ and is NOT committed to git.

SSL_DIR="$(cd "$(dirname "$0")" && pwd)/ssl"
mkdir -p "$SSL_DIR"

openssl req -x509 \
    -nodes \
    -days 3650 \
    -newkey rsa:2048 \
    -keyout "$SSL_DIR/key.pem" \
    -out    "$SSL_DIR/cert.pem" \
    -subj   "/C=DE/ST=Dev/L=Dev/O=Dev/CN=localhost" \
    -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

echo "Self-signed certificate generated in $SSL_DIR"
echo "  cert: $SSL_DIR/cert.pem"
echo "  key:  $SSL_DIR/key.pem"
echo ""
echo "To trust it in Chrome/Firefox, import cert.pem into your browser's"
echo "trusted authorities (or use 'mkcert' for a smoother experience)."
