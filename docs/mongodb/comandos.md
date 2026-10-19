# Runbook de comandos

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
