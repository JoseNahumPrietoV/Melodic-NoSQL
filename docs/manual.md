# Manual de comandos

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

Correr las pruebas:

```bash
composer test
```

## Docker

Llenar en `.env` las variables `MONGO_ROOT_USERNAME`, `MONGO_ROOT_PASSWORD`, `MYSQL_PASSWORD` y `MYSQL_ROOT_PASSWORD`. Generar cada contraseña con:

```bash
openssl rand -hex 24
```

Generar el keyFile de MongoDB (solo la primera vez):

```bash
./docker/mongo/generate-keyfile.sh
```

Levantar, ver el estado, ver los logs y detener:

```bash
docker compose up --build --detach
docker compose ps
docker compose logs -f mongodb
docker compose down
```

| Servicio | Desde el host | Dentro de Docker |
|---|---|---|
| App | `localhost:8000` | `app:8000` |
| MySQL | `localhost:3306` | `mysql:3306` |
| MongoDB | `localhost:27018` | `mongodb:27017` |

## MongoDB

Abrir una consola:

```bash
docker exec -it melodic-mongodb sh -c 'mongosh -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD"'
```

Revisar el replica set:

```bash
docker exec melodic-mongodb sh -c 'mongosh --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --eval "rs.status().ok"'
```

Conectarse desde el host (en `.env`):

```
MONGODB_URI="mongodb://MONGO_ROOT_USERNAME:MONGO_ROOT_PASSWORD@127.0.0.1:27018/?authSource=admin&directConnection=true"
```

## Rotar contraseñas

Los volúmenes guardan la contraseña con la que se crearon. Para cambiarla sin borrar datos, con los contenedores corriendo y las contraseñas viejas todavía en `.env`:

```bash
docker exec melodic-mongodb sh -c 'mongosh --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" admin --eval "db.changeUserPassword(\"$MONGO_INITDB_ROOT_USERNAME\", \"NUEVA\")"'
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
