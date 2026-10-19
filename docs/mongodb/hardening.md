# Verificación de hardening

Evidencia de E4-T24 (2026-10-19, entorno Docker local en el host `192.168.1.66`). Los escaneos se hicieron con `instrumentisto/nmap:7.95`; los de puertos, desde un contenedor hacia la IP del host.

## TLS en MongoDB

MongoDB acepta solo TLS 1.2 y 1.3 (`--tlsDisabledProtocols TLS1_0,TLS1_1`). En TLS 1.2 se limitaron las suites a intercambio de claves con secreto perfecto hacia adelante y cifrado AEAD (`--tlsCipherConfig ECDHE+AESGCM:ECDHE+CHACHA20:DHE+AESGCM:DHE+CHACHA20`): antes ofrecía 47 suites, 30 de ellas sin secreto perfecto hacia adelante (`TLS_RSA_*`) o en modo CBC.

```text
$ nmap -Pn -p 27017 --script +ssl-enum-ciphers mongodb    # desde la red interna backend
Nmap scan report for mongodb (172.20.0.2)
Host is up (0.000035s latency).
PORT      STATE SERVICE
27017/tcp open  mongod
| ssl-enum-ciphers: 
|   TLSv1.2: 
|     ciphers: 
|       TLS_DHE_RSA_WITH_AES_128_GCM_SHA256 (dh 3072) - A
|       TLS_DHE_RSA_WITH_AES_256_GCM_SHA384 (dh 3072) - A
|       TLS_DHE_RSA_WITH_CHACHA20_POLY1305_SHA256 (dh 3072) - A
|       TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256 (secp256r1) - A
|       TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384 (secp256r1) - A
|       TLS_ECDHE_RSA_WITH_CHACHA20_POLY1305_SHA256 (secp256r1) - A
|     compressors: 
|       NULL
|     cipher preference: client
|     warnings: 
|       Key exchange (dh 3072) of lower strength than certificate key
|       Key exchange (secp256r1) of lower strength than certificate key
|   TLSv1.3: 
|     ciphers: 
|       TLS_AKE_WITH_AES_128_GCM_SHA256 (ecdh_x25519) - A
|       TLS_AKE_WITH_AES_256_GCM_SHA384 (ecdh_x25519) - A
|       TLS_AKE_WITH_CHACHA20_POLY1305_SHA256 (ecdh_x25519) - A
|     cipher preference: client
|_  least strength: A
```

`+` fuerza el script: con las suites limitadas, la sonda inicial de nmap no reconoce el puerto como TLS. Las advertencias solo indican que el intercambio de claves es más débil que la llave RSA de 4096 bits del certificado.

```text
$ openssl s_client -connect 127.0.0.1:27017 -<versión> -CAfile ca.pem    # dentro del contenedor
openssl s_client -tls1 -> New, (NONE), Cipher is (NONE)
openssl s_client -tls1_1 -> New, (NONE), Cipher is (NONE)
openssl s_client -tls1_2 -> New, TLSv1.2, Cipher is ECDHE-RSA-AES256-GCM-SHA384
openssl s_client -tls1_3 -> New, TLSv1.3, Cipher is TLS_AES_256_GCM_SHA384
40175FEBF27F0000:error:0A0000BF:SSL routines:tls_setup_handshake:no protocols available:../ssl/statem/statem_lib.c:104:
```

## TLS en el proxy (Caddy)

```text
$ nmap -Pn -p 443 --script ssl-enum-ciphers --script-args tls.servername=localhost proxy
Nmap scan report for proxy (172.19.0.4)
Host is up (0.000049s latency).
PORT    STATE SERVICE
443/tcp open  https
| ssl-enum-ciphers: 
|   TLSv1.2: 
|     ciphers: 
|       TLS_ECDHE_ECDSA_WITH_AES_128_GCM_SHA256 (secp256r1) - A
|       TLS_ECDHE_ECDSA_WITH_AES_256_GCM_SHA384 (secp256r1) - A
|       TLS_ECDHE_ECDSA_WITH_CHACHA20_POLY1305_SHA256 (secp256r1) - A
|     compressors: 
|       NULL
|     cipher preference: client
|   TLSv1.3: 
|     ciphers: 
|       TLS_AKE_WITH_AES_128_GCM_SHA256 (ecdh_x25519) - A
|       TLS_AKE_WITH_AES_256_GCM_SHA384 (ecdh_x25519) - A
|       TLS_AKE_WITH_CHACHA20_POLY1305_SHA256 (ecdh_x25519) - A
|     cipher preference: server
|_  least strength: A
```

## Puertos del host

Antes: `dev` antes de E3-T20 y E4-T22 (app en 8000 y MySQL en todas las interfaces). Después: esta rama con el proxy, la VPN y MySQL en `127.0.0.1`.

```text
# antes
22/tcp    filtered ssh
80/tcp    filtered http
443/tcp   filtered https
3306/tcp  open     mysql
8000/tcp  open     http-alt
27017/tcp filtered mongod
27018/tcp filtered mongod
51820/udp open|filtered unknown

# después
22/tcp    filtered ssh
80/tcp    open     http
443/tcp   open     https
3306/tcp  filtered mysql
8000/tcp  filtered http-alt
27017/tcp filtered mongod
27018/tcp filtered mongod
51820/udp open|filtered unknown
```

- En el "antes", 3306 y 8000 estaban abiertos aunque el ufw del host estaba activo: Docker publica los puertos con sus propias reglas y se salta ufw. Por eso E4-T22 filtra en la cadena `DOCKER-USER`.
- 27017 y 27018 ya estaban cerrados en la IP del host desde E4-T21; 27018 solo existe en `127.0.0.1` con `compose.localhost.yaml`.
- 51820/udp aparece `open|filtered` porque WireGuard no responde a paquetes que no son de un peer.
- Las reglas de `docker/firewall/ufw-setup.sh` se probaron en un contenedor aislado (E4-T22). Aplicarlas en el host requiere `sudo` y queda como paso manual (ver `docs/manual.md`).
