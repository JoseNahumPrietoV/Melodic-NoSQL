# Runbook de comandos

Comandos de operación de Melodic sobre MongoDB. La guía de instalación está en `docs/manual.md`. Los comandos de `mongosh` toman las credenciales de las variables del contenedor, así que se pueden copiar sin editar.

## Entorno

```bash
docker compose up --build --detach
docker compose -f compose.yaml -f compose.localhost.yaml -f compose.vpn.yaml up --detach
docker compose ps
docker compose logs -f mongodb
docker compose down
```

Estado del replica set y, si no existe, iniciarlo (el healthcheck ya lo hace):

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --eval "rs.status().members.map(m => m.name + \" \" + m.stateStr)"'
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --eval "rs.initiate({ _id: \"rs0\", members: [{ _id: 0, host: \"mongodb:27017\" }] })"'
```

## Conexión

Con TLS y el usuario de solo lectura, dentro del contenedor:

```bash
docker exec -it melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca -u melodic_readonly -p "$MONGO_READONLY_PASSWORD" --authenticationDatabase admin melodic'
```

Desde otro equipo, con la VPN activa:

```bash
mongosh "mongodb://melodic_readonly:PASSWORD@10.8.0.1:27017/melodic?authSource=admin&directConnection=true&tls=true&tlsCAFile=ca.pem"
```

Una conexión sin TLS debe fallar:

```bash
docker exec melodic-mongodb sh -c 'mongosh --quiet "mongodb://127.0.0.1:27017/?tls=false&serverSelectionTimeoutMS=3000" --eval "db.runCommand({ ping: 1 })"'
```

## Usuarios e índices

Crear o actualizar los usuarios de la app (idempotente) y listarlos:

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin /docker-entrypoint-initdb.d/01-users.js'
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin admin --eval "db.getUsers().users.map(u => u.user + \" \" + JSON.stringify(u.roles))"'
```

Crear un usuario a mano con mínimo privilegio y borrarlo:

```bash
docker exec -it melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin admin --eval "db.createUser({ user: \"reportes\", pwd: passwordPrompt(), roles: [{ role: \"read\", db: \"melodic\" }], mechanisms: [\"SCRAM-SHA-256\"] })"'
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin admin --eval "db.dropUser(\"reportes\")"'
```

Índices: listarlos, y crear uno que falte (si ya existe con la misma definición, no hace nada). Los índices de la app se versionan en `database/migrations/mongodb/`; uno nuevo va en una migración nueva.

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin melodic --eval "db.getCollectionNames().forEach(c => print(c, JSON.stringify(db[c].getIndexes().map(i => i.key))))"'
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin melodic --eval "db.songs.createIndex({ album_id: 1 })"'
```

## Artisan

```bash
docker exec melodic-app php artisan down
docker exec melodic-app php artisan migrate --force
docker exec melodic-app php artisan migrate --path=database/migrations/mongodb --force
docker exec melodic-app php artisan melodic:migrate-to-mongo --dry-run
docker exec melodic-app php artisan melodic:migrate-to-mongo
docker exec melodic-app php artisan melodic:verify-mongo
docker exec melodic-app php artisan view:clear
docker exec melodic-app php artisan up
```

Después del corte, `melodic:migrate-to-mongo` sin `--dry-run` sobrescribe en MongoDB los datos con los de la base relacional, que ya no recibe escrituras: solo se usa antes del corte o en un ensayo. El orden completo del corte y la reversión está en `03-corte-y-reversion.md`.

## Respaldo y restauración

Respaldo con `melodic_backup` (rol `backup`) a un archivo comprimido:

```bash
docker exec melodic-mongodb sh -c 'mongodump --quiet --uri "mongodb://melodic_backup:$MONGO_BACKUP_PASSWORD@127.0.0.1:27017/?authSource=admin&tls=true&tlsCAFile=/run/secrets/mongodb_tls_ca" --db melodic --archive --gzip' > melodic-$(date +%F).archive.gz
```

Restaurar en una base temporal para comprobar el respaldo (con root, porque `melodic_backup` no tiene el rol `restore`):

```bash
docker exec -i melodic-mongodb sh -c 'mongorestore --quiet --uri "mongodb://$MONGO_INITDB_ROOT_USERNAME:$MONGO_INITDB_ROOT_PASSWORD@127.0.0.1:27017/?authSource=admin&tls=true&tlsCAFile=/run/secrets/mongodb_tls_ca" --archive --gzip --nsFrom "melodic.*" --nsTo "melodic_restore.*" --drop' < melodic-AAAA-MM-DD.archive.gz
```

Comparar la original con la restaurada (conteo y hash por colección) y borrar la temporal:

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --eval "const o = db.getSiblingDB(\"melodic\"), r = db.getSiblingDB(\"melodic_restore\"), ho = o.runCommand({ dbHash: 1 }).collections, hr = r.runCommand({ dbHash: 1 }).collections; o.getCollectionNames().forEach(c => print(c, o[c].countDocuments(), r[c].countDocuments(), ho[c] === hr[c]))"'
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin melodic_restore --eval "db.dropDatabase()"'
```

Para restaurar sobre la base real, en mantenimiento y sin `--nsFrom/--nsTo`: `mongorestore ... --archive --gzip --drop`.

Prueba del 2026-10-19 (E6-T28), con 78 documentos en 8 colecciones:

| Paso | Tiempo | Tamaño |
|---|---|---|
| `mongodump` | 0.1 s | 4.8 KB comprimido |
| `mongorestore` en `melodic_restore` | 0.9 s | — |

- Conteos, `dbHash` e índices iguales en las 8 colecciones.
- `melodic:verify-mongo` da la misma salida contra la original y contra la restaurada. Las 12 diferencias que reporta frente a MySQL son escrituras hechas en MongoDB después del corte, que MySQL ya no recibe.

## Firewall y VPN

Firewall del host (después de `sudo sh docker/firewall/ufw-setup.sh`):

```bash
sudo ufw status verbose
sudo iptables -S DOCKER-USER
docker run --rm instrumentisto/nmap:7.95 -Pn -sT -p 22,80,443,3306,8000,27017,27018 IP_DEL_HOST
```

Desde otra máquina solo deben aparecer abiertos 80 y 443 (y 51820/udp como `open|filtered`).

VPN:

```bash
docker exec melodic-wireguard wg show
docker exec melodic-wireguard iptables -t nat -S PREROUTING
```

`wg show` debe mostrar un `latest handshake` reciente por cada peer conectado.

TLS de MongoDB y del proxy (solo 1.2 y 1.3):

```bash
docker run --rm --network melodic-nosql_backend instrumentisto/nmap:7.95 -Pn -p 27017 --script +ssl-enum-ciphers mongodb
docker exec melodic-mongodb sh -c 'echo | openssl s_client -connect 127.0.0.1:27017 -tls1_1 -CAfile /run/secrets/mongodb_tls_ca'
docker cp melodic-proxy:/data/caddy/pki/authorities/local/root.crt caddy-root.crt
curl -I --cacert caddy-root.crt https://localhost
```

El `openssl` con `-tls1_1` debe terminar con `no protocols available`. La evidencia completa está en `hardening.md`.

