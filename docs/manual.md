# Manual de comandos

Guía para instalar y levantar el entorno. Los comandos de operación (conexión, usuarios, índices, respaldo, firewall y VPN) están en `docs/mongodb/comandos.md`.

## Entorno local

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs
php artisan migrate
npm install
npm run build
```

Levantar la app:

```bash
composer run dev
```

Correr las pruebas. Usan MongoDB (base `melodic_testing` con el usuario `melodic_test`), así que antes hay que llenar `MONGO_TEST_PASSWORD` y `MONGODB_TEST_URI` en `.env`, levantar el stack con `compose.localhost.yaml` y crear el usuario con el comando de "Usuarios de MongoDB":

```bash
docker compose -f compose.yaml -f compose.localhost.yaml up --detach
composer test
```

## Docker

Llenar en `.env` las variables `MONGO_ROOT_USERNAME`, `MONGO_ROOT_PASSWORD`, `MONGO_APP_PASSWORD`, `MONGO_READONLY_PASSWORD`, `MONGO_BACKUP_PASSWORD`, `MYSQL_PASSWORD` y `MYSQL_ROOT_PASSWORD`. Generar cada contraseña con:

```bash
openssl rand -hex 24
```

Generar el keyFile y los certificados TLS de MongoDB (solo la primera vez):

```bash
./docker/mongo/generate-keyfile.sh
./docker/mongo/tls/generate-certs.sh
./docker/mongo/sync-secrets.sh
```

`sync-secrets.sh` copia el keyFile, la CA y el certificado al volumen `melodic-nosql_mongodb-secrets`, que `compose.yaml` monta en `/run/secrets` (se usa un volumen en lugar de secretos de Compose para que funcione con SELinux). Sin ese volumen, `docker compose up` falla con `external volume "melodic-nosql_mongodb-secrets" not found`.

MongoDB solo acepta conexiones TLS. Para renovar los certificados, volver a correr `generate-certs.sh` y `sync-secrets.sh` y recrear los contenedores.

Los datos viven en los volúmenes `mysql-data-clean` y `mongodb-data-clean`. Al arrancar, la app crea las colecciones e índices de MongoDB y los planes de suscripción; las cuentas de prueba se agregan con `docker exec melodic-app php artisan db:seed --force`.

Levantar, ver el estado, ver los logs y detener:

```bash
docker compose up --build --detach
docker compose ps
docker compose logs -f mongodb
docker compose down
```

Aplicar cambios de `compose.yaml`, del `Dockerfile` o de `.env` después de actualizar la rama (recrea los contenedores y quita los que ya no existen, sin borrar volúmenes):

```bash
docker compose up --build --detach --force-recreate --remove-orphans
docker exec melodic-app php artisan view:clear
```

`view:clear` borra las vistas compiladas del despliegue anterior, que el volumen `app-storage` conserva.

El corte a MongoDB y su reversión están en `docs/mongodb/03-corte-y-reversion.md`. El despliegue en Render con MongoDB Atlas está en `docs/despliegue-render-atlas.md`.

| Servicio | Desde el host | Dentro de Docker |
|---|---|---|
| App | `https://localhost` (Caddy; `http://` redirige) | `app:8000` |
| MySQL | `127.0.0.1:3306` | `mysql:3306` |
| MongoDB | Solo con `compose.localhost.yaml`: `127.0.0.1:27018` | `mongodb:27017` |

## HTTPS

Caddy sirve la app en `https://localhost` con TLS 1.2 y 1.3, HSTS y redirección desde `http://`. El certificado lo firma su CA interna; para que el navegador y `curl` confíen en él, exportar la CA:

```bash
docker cp melodic-proxy:/data/caddy/pki/authorities/local/root.crt caddy-root.crt
curl -I --cacert caddy-root.crt https://localhost
```

Después importar `caddy-root.crt` en el navegador o en el sistema (en Arch: `sudo trust anchor caddy-root.crt`).

## Firewall

`docker/firewall/ufw-setup.sh` deja el host con entrada denegada salvo 80/tcp, 443/tcp, 51820/udp y el 22 solo desde la red de la VPN (`VPN_SUBNET`, por defecto `172.30.80.0/24`). También agrega a `/etc/ufw/after.rules` las reglas de la cadena `DOCKER-USER`, porque Docker se salta ufw en los puertos publicados. No borra reglas existentes y se puede repetir. Aplicar con una consola local abierta:

```bash
sudo sh docker/firewall/ufw-setup.sh
sudo ufw status verbose
sudo iptables -S DOCKER-USER
```

Revertir: borrar el bloque `MELODIC DOCKER-USER` de `/etc/ufw/after.rules`, quitar las reglas con `sudo ufw delete allow 80/tcp` (y las demás) y ejecutar `sudo ufw reload`.

## VPN (WireGuard)

`compose.vpn.yaml` agrega un servidor WireGuard en `10.8.0.0/24` con un peer por integrante (`personaA`, `personaB`). Por la VPN solo se alcanzan MongoDB en `10.8.0.1:27017` y el SSH del host. Las claves se generan en `docker/wireguard/config/`, que no se versiona. Llenar `VPN_SERVER_URL` en `.env` con la IP o el dominio del host y levantar:

```bash
docker compose -f compose.yaml -f compose.vpn.yaml up --detach
docker exec melodic-wireguard wg show
```

La configuración de cada peer queda en `docker/wireguard/config/peer_personaA/peer_personaA.conf` (también hay un QR en `.png`). Con la VPN activa en el equipo del integrante:

```bash
mongosh "mongodb://melodic_readonly:PASSWORD@10.8.0.1:27017/melodic?authSource=admin&directConnection=true&tls=true&tlsCAFile=docker/mongo/tls/ca.pem"
```

El certificado de MongoDB incluye `10.8.0.1`; si se generó antes de la VPN, volver a correr `generate-certs.sh` y recrear los contenedores.

## MongoDB

Abrir una consola:

```bash
docker exec -it melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD"'
```

Revisar el replica set:

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --eval "rs.status().ok"'
```

MongoDB está en una red interna y no publica puertos. Para conectarse desde el host en desarrollo, levantar el stack con el archivo local, que lo publica solo en `127.0.0.1`:

```bash
docker compose -f compose.yaml -f compose.localhost.yaml up --detach
```

Y en `.env`:

```
MONGODB_URI="mongodb://melodic_app:MONGO_APP_PASSWORD@127.0.0.1:27018/melodic?authSource=admin&directConnection=true&tls=true&tlsCAFile=docker/mongo/tls/ca.pem"
```

## Colecciones e índices de MongoDB

La migración de MongoDB vive en `database/migrations/mongodb/` para que `composer test` no la ejecute. Crear y revertir:

```bash
docker exec melodic-app php artisan migrate --path=database/migrations/mongodb --force
docker exec melodic-app php artisan migrate:rollback --path=database/migrations/mongodb --force
```

Ver los índices de una colección:

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin melodic --eval "db.songs.getIndexes()"'
```

## Carga de datos a MongoDB

Copia los datos de la base relacional a MongoDB. Se puede repetir: hace upsert por `legacy_id` y no duplica. Primero simular, después cargar:

```bash
docker exec melodic-app php artisan melodic:migrate-to-mongo --dry-run
docker exec melodic-app php artisan melodic:migrate-to-mongo
```

Opciones: `--collection=songs` carga una sola colección (sus referencias ya deben estar cargadas) y `--from=sqlite` cambia la conexión de origen. El comando termina con código 1 si alguna fila tuvo errores.

Verificar que MongoDB tiene lo mismo que la base relacional (conteos, montos, reproducciones, canciones por playlist, referencias y una muestra campo por campo). Termina con código 1 si hay diferencias:

```bash
docker exec melodic-app php artisan melodic:verify-mongo
```

## Usuarios de MongoDB

| Usuario | Permisos | Uso |
|---|---|---|
| `melodic_app` | `readWrite` sobre `melodic` | La aplicación |
| `melodic_readonly` | `read` sobre `melodic` | Consultas y reportes |
| `melodic_backup` | `backup` | `mongodump` |
| `melodic_test` | `readWrite` sobre `melodic_testing` | `composer test`; solo se crea si `MONGO_TEST_PASSWORD` tiene valor |

Se crean solos cuando el volumen está vacío. En un volumen que ya existe, o después de cambiar sus contraseñas en `.env` y recrear el contenedor, aplicarlos con:

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" /docker-entrypoint-initdb.d/01-users.js'
```

Entrar como `melodic_readonly`:

```bash
docker exec -it melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca -u melodic_readonly -p "$MONGO_READONLY_PASSWORD" --authenticationDatabase admin melodic'
```

## Rotar contraseñas

Los volúmenes guardan la contraseña con la que se crearon. Para cambiarla sin borrar datos, con los contenedores corriendo y las contraseñas viejas todavía en `.env`:

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" admin --eval "db.changeUserPassword(\"$MONGO_INITDB_ROOT_USERNAME\", \"NUEVA\")"'
docker exec melodic-mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "ALTER USER root@\"%\" IDENTIFIED BY \"NUEVA_ROOT\"; ALTER USER root@localhost IDENTIFIED BY \"NUEVA_ROOT\"; ALTER USER melodic@\"%\" IDENTIFIED BY \"NUEVA_APP\";"'
```

Después poner las nuevas en `.env` y recrear los contenedores:

```bash
docker compose up --detach --force-recreate
```

## Laravel dentro del contenedor

```bash
docker exec -it -e XDG_CONFIG_HOME=/tmp melodic-app php artisan tinker
```

Probar la conexión a MongoDB:

```bash
docker exec -e XDG_CONFIG_HOME=/tmp melodic-app php artisan tinker --execute 'dump(DB::connection("mongodb")->getClient()->listDatabaseNames());'
```
