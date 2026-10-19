# Corte a MongoDB y reversión

## Qué es el corte

Los modelos de dominio usan `$connection = 'mongodb'` desde E1-T09, así que el corte es desplegar ese código con los datos ya cargados. `DB_CONNECTION` se queda en la base relacional (MySQL en Docker, SQLite en local): ahí siguen la tabla `migrations`, las sesiones, la caché y las colas, fijadas con `SESSION_CONNECTION`, `DB_CACHE_CONNECTION` y `DB_QUEUE_CONNECTION` (también `queue.failed` y `queue.batching`). Cambiar `DB_CONNECTION` a `mongodb` rompería `php artisan migrate`, porque las migraciones relacionales usan el `Blueprint` de SQL.

## Go / no-go

Se hace el corte solo si se cumplen los tres criterios. Si falla cualquiera, es no-go y se revierte.

1. `melodic:verify-mongo` termina con 0 diferencias.
2. `composer test` pasa sobre MongoDB.
3. El recorrido con los 4 roles funciona: oyente (suscribirse, explorar, reproducir, crear una playlist y añadir una canción), artista (dashboard y álbum), label (dashboard y retiro) y admin (dashboard con la gráfica).

## Procedimiento

```bash
docker exec melodic-app php artisan down
docker exec melodic-mysql sh -c 'mysqldump -umelodic -p"$MYSQL_PASSWORD" --single-transaction --no-tablespaces melodic' > respaldo-melodic.sql
docker exec melodic-mongodb sh -c 'mongodump --quiet --uri "mongodb://melodic_backup:$MONGO_BACKUP_PASSWORD@127.0.0.1:27017/?authSource=admin&tls=true&tlsCAFile=/run/secrets/mongodb_tls_ca" --db melodic --archive --gzip' > respaldo-mongo.archive.gz
git switch <rama con el corte>
docker compose up --build --detach app
docker exec melodic-app php artisan view:clear
docker exec melodic-app php artisan migrate --path=database/migrations/mongodb --force
docker exec melodic-app php artisan melodic:migrate-to-mongo
docker exec melodic-app php artisan melodic:verify-mongo
composer test
docker exec melodic-app php artisan up
```

Si MongoDB ya tenía datos de un intento anterior, antes de la migración se corre `migrate:rollback --path=database/migrations/mongodb --force` para empezar desde colecciones vacías.

`view:clear` es obligatorio: el volumen `app-storage` conserva las vistas compiladas del despliegue anterior y Laravel puede seguir usándolas.

## Reversión

```bash
docker exec melodic-app php artisan down
git switch <rama anterior al corte>
docker compose up --build --detach app
docker exec melodic-app php artisan view:clear
docker exec melodic-app php artisan up
```

- La carga no modifica la base relacional, así que la app vuelve con los datos que tenía antes del corte.
- Sin pérdida de datos solo si se revierte antes de reabrir el tráfico. Lo escrito en MongoDB después de `php artisan up` no existe en la base relacional y requiere conciliación manual.
- En el repositorio, la reversión es `git revert` del merge del corte.

## Bitácora del ensayo (2026-10-19, entorno Docker local)

Base de origen: MySQL `melodic` sembrada con los seeders y datos de factories (13 usuarios, 18 canciones, 5 playlists, 2 regalías, 6 ingresos, 2 egresos), más las escrituras del recorrido previo en MySQL.

### Primer intento (01:58): no-go

| Paso | Resultado |
|---|---|
| Mantenimiento, respaldos, despliegue, carga, verificación, pruebas | Correctos |
| Recorrido con los 4 roles | Falló: 500 en la playlist y en el dashboard del artista |

Causas y correcciones:

- La playlist usaba una vista compilada del código anterior guardada en el volumen `app-storage`. Se agregó `view:clear` al despliegue.
- `withCount('songs')` no está soportado por laravel-mongodb. Se corrigió en `DashboardController@artist` y se agregaron pruebas del dashboard del artista y de la página del álbum.

### Reversión (02:02): correcta

| Paso | Tiempo (s) |
|---|---|
| R1. Modo mantenimiento | 0.2 |
| R2. Desplegar el código anterior (`dev`, MySQL) y limpiar vistas | 30.8 |
| R3. Salir de mantenimiento | 0.2 |
| R4. Recorrido con los 4 roles sobre MySQL | 2.0 |
| **Ventana de mantenimiento** | **31.2** |

### Segundo intento (02:04): go

| Paso | Tiempo (s) |
|---|---|
| 1. Modo mantenimiento | 0.2 |
| 2a. Respaldo MySQL (`mysqldump`) | 0.1 |
| 2b. Respaldo MongoDB (`mongodump`) | 0.3 |
| 3. Desplegar el código nuevo y limpiar vistas | 31.5 |
| 4. Colecciones e índices desde cero | 0.8 |
| 5. Carga (62 documentos, 0 errores) | 0.3 |
| 6. Verificación: 0 diferencias | 0.3 |
| 7. `composer test` sobre MongoDB | 17.2 |
| 8. Salir de mantenimiento | 0.2 |
| **Ventana de mantenimiento** | **50.9** |
| 9. Recorrido con los 4 roles | 2.0, correcto |

La carga incluyó las escrituras hechas en MySQL antes del corte y durante la reversión (7 playlists, 10 ingresos, 4 egresos).
