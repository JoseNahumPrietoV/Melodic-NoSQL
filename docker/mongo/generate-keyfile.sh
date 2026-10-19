#!/usr/bin/env sh
set -eu

keyfile="$(dirname "$0")/keyfile"

openssl rand -base64 756 > "$keyfile"
chmod 400 "$keyfile"
