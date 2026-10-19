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
docker exec -it melodic-mongodb mongosh -u root -p root
```

Revisar el replica set:

```bash
docker exec melodic-mongodb mongosh --quiet -u root -p root --eval 'rs.status().ok'
```

Conectarse desde el host (en `.env`):

```
MONGODB_URI="mongodb://root:root@127.0.0.1:27018/?authSource=admin&directConnection=true"
```

## Laravel dentro del contenedor

```bash
docker exec -it -e XDG_CONFIG_HOME=/tmp melodic-app php artisan tinker
```

Probar la conexión a MongoDB:

```bash
docker exec -e XDG_CONFIG_HOME=/tmp melodic-app php artisan tinker --execute 'dump(DB::connection("mongodb")->getClient()->listDatabaseNames());'
```
