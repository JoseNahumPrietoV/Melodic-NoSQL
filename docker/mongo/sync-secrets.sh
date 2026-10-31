#!/usr/bin/env sh
set -eu

dir="$(cd "$(dirname "$0")" && pwd)"
volume=melodic-nosql_mongodb-secrets

docker volume create "$volume" > /dev/null
docker run --rm -v "$volume:/secrets" -v "$dir:/src:ro,z" alpine:3 sh -c '
    cp /src/keyfile /secrets/mongodb_keyfile
    cp /src/tls/ca.pem /secrets/mongodb_tls_ca
    cp /src/tls/server.pem /secrets/mongodb_tls_certificate
    chmod 400 /secrets/mongodb_keyfile /secrets/mongodb_tls_certificate
    chmod 444 /secrets/mongodb_tls_ca
'
