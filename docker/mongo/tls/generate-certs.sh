#!/usr/bin/env sh
set -eu

dir="$(dirname "$0")"
days=825

openssl req -x509 -newkey rsa:4096 -nodes -days "$days" \
    -subj "/CN=Melodic Local CA" \
    -addext "basicConstraints=critical,CA:TRUE" \
    -addext "keyUsage=critical,keyCertSign,cRLSign" \
    -keyout "$dir/ca.key" -out "$dir/ca.pem"

openssl req -newkey rsa:4096 -nodes \
    -subj "/CN=mongodb" \
    -keyout "$dir/server.key" -out "$dir/server.csr"

printf 'subjectAltName = DNS:mongodb, DNS:localhost, IP:127.0.0.1\nextendedKeyUsage = serverAuth, clientAuth\n' > "$dir/server.ext"

openssl x509 -req -days "$days" -in "$dir/server.csr" \
    -CA "$dir/ca.pem" -CAkey "$dir/ca.key" -set_serial "0x$(openssl rand -hex 16)" \
    -extfile "$dir/server.ext" -out "$dir/server.crt"

cat "$dir/server.key" "$dir/server.crt" > "$dir/server.pem"
rm "$dir/server.csr" "$dir/server.ext" "$dir/server.key" "$dir/server.crt"
chmod 600 "$dir/ca.key" "$dir/server.pem"
chmod 644 "$dir/ca.pem"
