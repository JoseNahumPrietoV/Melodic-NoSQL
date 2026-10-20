# Migración de Melodic de SQL a MongoDB · Informe técnico

## 1. Introducción

Melodic es una plataforma de música en streaming construida con Laravel 12. Oyentes con suscripción escuchan y organizan canciones en playlists; artistas y sellos discográficos publican música y cobran regalías por reproducción; un administrador supervisa las finanzas.

Este informe documenta la migración de su persistencia desde un modelo relacional (MySQL, con SQLite en local) a MongoDB. El trabajo se planeó como un sprint Scrum de dos semanas con dos personas y 28 tareas en seis épicas (migración, control de acceso, cifrado en tránsito, hardening de red, consultas CRUD y comandos), registradas en `docs/scrum.md` y entregadas en 11 pull requests.

Objetivo: que Melodic funcione sobre MongoDB con los datos migrados y verificados, conexiones cifradas, red endurecida y la posibilidad de volver a la base relacional.

## 2. Sistema origen

| Elemento | Antes de la migración |
|---|---|
| Aplicación | Laravel 12 (PHP 8.2+), vistas Blade, Tailwind y Chart.js |
| Base de datos | MySQL 8.4 en Docker (fuente oficial); SQLite en desarrollo y pruebas |
| Sesiones, caché y colas | Driver `database` sobre la misma base relacional |
| Roles | `user` (oyente), `artist`, `label`, `admin`, con los middleware `role`, `subscribed` y `admin` |
| Despliegue | Docker Compose: `app` (puerto 8000), `mysql` (3306) y un `mongo:8.0` reservado sin uso |
| Pruebas | Solo los ejemplos de Laravel |

Flujos principales: registro e inicio de sesión, suscripción (genera un ingreso), reproducción (suma 0.15 a la regalía del artista o de su sello), retiro (70 % al artista o sello y 30 % de comisión), playlists, búsqueda y paneles por rol.

## 3. Justificación de la migración a NoSQL

La decisión se tomó a partir de cómo lee y escribe la aplicación (sección 1.3 de `docs/scrum.md`), no por una conversión de tablas a colecciones:

- **Reproducir es la operación más frecuente** y en SQL se hacía leyendo, sumando y guardando, con lo que se perdían incrementos concurrentes. En MongoDB es un `$inc` atómico dentro de una transacción.
- **Las playlists se leen completas.** El pivote `playlist_song` se convierte en un arreglo dentro del documento: mostrar, contar y añadir una canción se resuelve en un solo documento, y añadir sin duplicados es una única escritura condicionada.
- **La búsqueda filtra por nombre de canción y de artista.** Con una copia del artista dentro de cada canción, la búsqueda no necesita juntar tablas.
- **El retiro necesita leer y vaciar el saldo en un solo paso.** `findOneAndUpdate` lo hace atómicamente.
- **El modelo es flexible** para crecer (por ejemplo, estadísticas por reproducción) sin migraciones de esquema rígidas.

La migración también corrigió problemas del sistema origen: condiciones de carrera en el dinero, tres implementaciones distintas del retiro, montos calculados con `float` y un dashboard de administración que no funcionaba en SQLite.

## 4. Análisis del modelo relacional actual

El inventario completo está en `docs/mongodb/01-inventario.md`.

| Tabla | PK | Relaciones |
|---|---|---|
| `subscriptions` | `id` int | 1:N con `users` y `earnings` |
| `users` | `user_id` UUID | `subscription_id` → `subscriptions`; `label` → `users` (autorrelación) |
| `albums` | `album_id` int | `user_id` → `users` |
| `songs` | `song_id` int | `album_id` → `albums` |
| `playlists` | `playlist_id` int | `user_id` → `users` |
| `playlist_song` | sin PK | N:M entre `playlists` y `songs` |
| `plays` | `play_id` int | Ningún código la escribe |
| `royalties` | `royalties_id` int | `recipient_id` → `users`, único |
| `earnings` | `earning_id` int | `subscription` → `subscriptions`; `comision` → `royalties` |
| `expenses` | `expense_id` int | `from` → `royalties`; `user_id` → `users` |

```mermaid
erDiagram
    subscriptions ||--o{ users : subscription_id
    subscriptions ||--o{ earnings : subscription
    users ||--o{ users : label
    users ||--o{ albums : user_id
    users ||--o{ playlists : user_id
    users ||--o| royalties : recipient_id
    users ||--o{ expenses : user_id
    albums ||--o{ songs : album_id
    playlists ||--o{ playlist_song : playlist_id
    songs ||--o{ playlist_song : song_id
    royalties ||--o{ earnings : comision
    royalties ||--o{ expenses : from
```

Hallazgos que afectaban la migración y cómo se resolvieron:

| # | Hallazgo | Resolución |
|---|---|---|
| 1 | El registro aceptaba cualquier rol desde el formulario | Solo `user` y `artist` (E2-T15) |
| 2 | Secretos en `compose.yaml` y MongoDB publicado | Secretos en `.env` (E2-T18) y red interna (E4-T21) |
| 3 | Condiciones de carrera al reproducir y retirar | `$inc` y `findOneAndUpdate` en transacción (E1-T11) |
| 4 | Tres implementaciones del retiro | Un solo `WalletService` (E1-T11) |
| 5 | `DATE_FORMAT` rompía el dashboard en SQLite | Agregación con `$dateToString` (E1-T12) |
| 6 | Dinero calculado con `float` | `Decimal128` y bcmath (E1-T11) |
| 7 | `plays` nunca se escribe | No se migra; queda en el backlog |
| 10 | No había pruebas | 42 pruebas funcionales (E1-T06 en adelante) |

## 5. Estrategia de modelado

Se modeló a partir de las consultas, con estas reglas (detalle en `docs/mongodb/02-diseno.md`):

- **Embeber** cuando los datos se leen juntos y crecen de forma acotada: las canciones de una playlist (máximo 500) y la copia del artista en cada canción.
- **Referenciar** cuando la entidad se consulta sola o cambia por su cuenta: álbumes, usuarios, planes, regalías y movimientos contables.
- **Colección propia para el saldo** (`royalties`): cada reproducción hace `$inc` sobre él, y embebido en `users` competiría con el inicio de sesión y la suscripción.
- **Identificadores:** `users._id` conserva el UUID original. Las demás colecciones usan `ObjectId` y guardan `legacy_id` (el entero original) para la carga idempotente y la verificación.
- **Tipos:** dinero en `Decimal128`, fechas en UTC, nulos explícitos en `label`, `subscription_id` y `fin_subscription`.
- **Sin migración mecánica:** `playlist_song` desaparece como tabla y `plays` no se migra.

## 6. Diagrama de documentos

```mermaid
classDiagram
    class Song {
        _id: ObjectId
        legacy_id: Int
        name: String
        length: Int
        plays: Long
        file_path: String
        image_path: String
        album_id: String
        artist: ArtistSnapshot
        created_at: Date
    }
    class ArtistSnapshot {
        user_id: String (UUID)
        username: String
    }
    class Playlist {
        _id: ObjectId
        legacy_id: Int
        name: String
        user_id: String (UUID)
        songs: PlaylistEntry[0..500]
        created_at: Date
    }
    class PlaylistEntry {
        song_id: String
        added_at: Date
    }
    class Royalty {
        _id: ObjectId
        recipient_id: String (UUID)
        amount: Decimal128
        last_payment: Date
    }
    Song *-- ArtistSnapshot : embebido
    Playlist *-- PlaylistEntry : embebido
```

Ejemplo de una canción:

```js
{ _id: ObjectId("6710a0000000000000000201"), legacy_id: 42, name: "Luna nueva", length: 0,
  plays: NumberLong(1280), file_path: "songs/audio/luna.mp3", image_path: null,
  album_id: "6710a0000000000000000101",
  artist: { user_id: "9b1c7e2a-4f0d-4c1e-9a55-2f6d3c8b1a01", username: "Carla Ríos" },
  created_at: ISODate("2026-10-13T18:20:00Z"), updated_at: ISODate("2026-10-18T09:11:00Z") }
```

Los documentos de ejemplo de las 8 colecciones están en `docs/mongodb/02-diseno.md`.

## 7. Arquitectura de MongoDB (propuesta y final)

### Propuesta

Replica set de un nodo (`rs0`) para tener transacciones, con autenticación por keyFile, TLS y usuarios de mínimo privilegio; MongoDB aislado en la red de Docker y accesible para administración solo por VPN. El corte consistía en cambiar `DB_CONNECTION` a `mongodb`.

### Final

```mermaid
flowchart LR
    U[Navegador] -- HTTPS 443 --> P[Caddy<br/>TLS 1.2/1.3, HSTS]
    P -- red default --> A[Laravel app]
    A -- TLS · melodic_app<br/>red interna backend --> M[(MongoDB 8.0<br/>replica set rs0)]
    A -- sesiones, caché, colas --> S[(MySQL 8.4<br/>127.0.0.1)]
    V[Administrador] -- WireGuard 51820/udp --> W[WireGuard<br/>10.8.0.1]
    W -- DNAT 10.8.0.1:27017 --> M
    F{{Firewall ufw + DOCKER-USER}} -.-> P
    F -.-> W
```

| Aspecto | Propuesta | Final y motivo |
|---|---|---|
| Topología | Replica set de un nodo | Igual, con keyFile, `requireTLS` y sin puertos publicados |
| Referencias entre documentos | `ObjectId` | String con el hexadecimal del `ObjectId`: es la convención de laravel-mongodb y con `ObjectId` las relaciones `hasMany` no encontraban registros |
| Copia del álbum en `songs` | `album{title, path}` y `artist` | Solo `artist`: un campo `album` ocultaba la relación `album()` que usan las vistas |
| Corte | Cambiar `DB_CONNECTION` | Los modelos usan `$connection = 'mongodb'`; `DB_CONNECTION` queda relacional para sesiones, caché, colas y la tabla `migrations` |
| Índice de `legacy_id` | Único | Único y sparse, porque los documentos creados después del corte no lo tienen |
| Migración de índices | `database/migrations` | `database/migrations/mongodb`, para que las pruebas no la ejecuten |
| Pruebas | SQLite en memoria | Base `melodic_testing` en MongoDB con su propio usuario |
| VPN | WireGuard en el host | WireGuard en un contenedor opcional (`compose.vpn.yaml`) |

## 8. Diagrama de modelo de datos (colecciones)

```mermaid
erDiagram
    users {
        string _id "UUID"
        string email "único"
        string role
        string label "UUID o null"
        string subscription_id "o null"
    }
    subscriptions {
        ObjectId _id
        int legacy_id
        Decimal128 cost
        int months
    }
    albums {
        ObjectId _id
        string user_id
        string title
    }
    songs {
        ObjectId _id
        string album_id
        object artist "copia"
        long plays
    }
    playlists {
        ObjectId _id
        string user_id
        array songs "embebido"
    }
    royalties {
        ObjectId _id
        string recipient_id "único"
        Decimal128 amount
    }
    earnings {
        ObjectId _id
        Decimal128 amount
        date at
        string subscription
        string comision
    }
    expenses {
        ObjectId _id
        Decimal128 amount
        date at
        string from
        string user_id
    }
    users ||--o{ albums : "user_id"
    users ||--o{ playlists : "user_id"
    users ||--o{ users : "label"
    subscriptions ||--o{ users : "subscription_id"
    albums ||--o{ songs : "album_id"
    songs }o--o{ playlists : "songs[].song_id"
    users ||--o| royalties : "recipient_id"
    royalties ||--o{ earnings : "comision"
    royalties ||--o{ expenses : "from"
    subscriptions ||--o{ earnings : "subscription"
    users ||--o{ expenses : "user_id"
```

## 9. Índices y validación de datos

Los 18 índices se crean con una migración versionada (`database/migrations/mongodb/2026_10_14_000000_create_mongodb_collections.php`) y cada uno responde a una consulta real:

| Colección | Índice | Consulta |
|---|---|---|
| `users` | `{email: 1}` único | Login y unicidad del correo |
| `users` | `{label: 1}` | Artistas de un sello |
| `users` | `{role: 1, created_at: -1}` | Últimos 10 sellos |
| `albums` | `{user_id: 1}` | Álbumes del artista |
| `songs` | `{album_id: 1}` | Canciones de un álbum |
| `songs` | `{"artist.user_id": 1}` | Reproducciones por artista y sello |
| `songs` | `{created_at: -1}` | Explorar sin búsqueda |
| `playlists` | `{user_id: 1, created_at: -1}` | Playlists del usuario |
| `royalties` | `{recipient_id: 1}` único | Saldo en cada reproducción y retiro |
| `earnings` | `{at: 1}` | Ingresos por mes y de 30 días |
| `expenses` | `{user_id: 1, at: -1}` | Últimos 5 retiros |
| 7 colecciones | `{legacy_id: 1}` único y sparse | Carga idempotente y verificación |

Con `explain("executionStats")`, las 19 consultas críticas usan su índice y examinan solo los documentos que devuelven. Solo recorren la colección completa la búsqueda con regex (tampoco usaba índice con `LIKE '%q%'` en SQL), el total de ingresos y la lista de planes (detalle en `docs/mongodb/consultas-crud.md`).

**Validación de datos.** No se configuraron validadores `$jsonSchema` en las colecciones. La validación vive en la aplicación y en los índices:

- Reglas de Laravel en cada formulario, con `Rule::unique` y `Rule::exists` sobre los modelos de MongoDB.
- Índices únicos para el correo, el monedero por destinatario y `legacy_id`.
- Valores por defecto en los modelos (rol, suscripción, nulos explícitos), porque MongoDB no tiene valores por defecto de columna.
- El límite de 500 canciones se valida dentro de la misma escritura: `{"songs.499": {$exists: false}}`.

Agregar `$jsonSchema` queda como trabajo futuro (sección 14).

## 10. Estrategia e implementación de la migración ETL

| Fase | Implementación |
|---|---|
| Extracción | `melodic:migrate-to-mongo` lee de la conexión relacional (`--from`, por defecto MySQL) en el orden subscriptions → users → albums → songs → playlists → royalties → earnings → expenses |
| Transformación | Enteros a `ObjectId` más `legacy_id`; llaves foráneas a la referencia del documento; decimales a `Decimal128` sin pasar por `float`; fechas a UTC; `playlist_song` a arreglo; copia del artista en cada canción |
| Carga | Upsert por `legacy_id` (por UUID en `users`), en lotes de 500. Se puede repetir sin duplicar. `--dry-run` simula y `--collection` carga una sola colección |
| Verificación | `melodic:verify-mongo` compara conteos, montos al centavo, reproducciones, canciones por playlist, referencias huérfanas y una muestra campo por campo. Termina con código 1 si hay diferencias |

**Corte y reversión** (`docs/mongodb/03-corte-y-reversion.md`): mantenimiento, respaldo de MySQL y MongoDB, despliegue, colecciones e índices, carga, verificación, pruebas, salida de mantenimiento y recorrido con los 4 roles. Criterios de go: 0 diferencias, pruebas en verde y recorrido correcto.

| Ensayo (2026-10-19) | Resultado | Ventana de mantenimiento |
|---|---|---|
| Primer corte | No-go: encontró vistas compiladas antiguas y `withCount`, que laravel-mongodb no soporta; ambos se corrigieron | — |
| Reversión a MySQL | Correcta | 31 s |
| Segundo corte | Go: 62 documentos, 0 diferencias, 42 pruebas, recorrido correcto | 51 s |

La reversión es volver a desplegar el código anterior: la carga no modifica la base relacional.

## 11. Seguridad y hardening implementado

| Capa | Medida | Tarea |
|---|---|---|
| Aplicación | El registro público solo crea oyentes y artistas | E2-T15 |
| Aplicación | 5 intentos por minuto en login y registro, con contadores separados | E2-T16 |
| Secretos | Contraseñas y `APP_KEY` en `.env`; el `APP_KEY` publicado se rotó | E2-T18 |
| Base de datos | Usuarios `melodic_app` (readWrite), `melodic_readonly` (read), `melodic_backup` (backup) y `melodic_test`, con SCRAM-SHA-256 | E2-T17 |
| Cifrado | MongoDB con `requireTLS`, solo TLS 1.2/1.3 y suites con secreto perfecto hacia adelante | E3-T19, E4-T24 |
| Cifrado | La app se sirve por HTTPS con Caddy, HSTS y cookies `secure` | E3-T20 |
| Red | MongoDB en una red interna de Docker, sin puertos publicados; MySQL solo en `127.0.0.1` | E4-T21, E4-T22 |
| Red | Firewall ufw con reglas en `DOCKER-USER`: entrada solo por 80, 443, 51820/udp y SSH desde la VPN | E4-T22 |
| Red | Acceso administrativo a MongoDB solo por WireGuard en `10.8.0.1:27017` | E4-T23 |

Evidencia (`docs/mongodb/hardening.md`): antes, 3306 y 8000 estaban abiertos aunque ufw estaba activo, porque Docker lo salta. Después, solo 80 y 443. En MongoDB, las suites de TLS 1.2 bajaron de 47 a 6, y `openssl -tls1_1` es rechazado.

## 12. Vistas del sistema y despliegue

### Vistas

| Vista | Ruta | Rol | Contenido |
|---|---|---|---|
| Login y registro | `/login`, `/register` | Público | Acceso y alta de oyentes o artistas |
| Premium | `/premium` | Oyente | Planes de suscripción |
| Inicio del oyente | `/dashboard/user` | Oyente | 6 canciones al azar y sus playlists |
| Explorar | `/explore` | Todos los suscritos | Catálogo y búsqueda por canción o artista |
| Mis playlists y detalle | `/playlists`, `/playlist/{id}` | Oyente | Playlists, sus canciones en orden y buscador para añadir |
| Álbum | `/album/{id}` | Suscritos | Canciones del álbum |
| Panel del artista | `/dashboard/artist` | Artista | Álbumes, subida de canciones, saldo y retiros |
| Panel del sello | `/dashboard/label` | Sello | Artistas, reproducciones totales, saldo y retiros |
| Panel de administración | `/dashboard/admin` | Admin | Ingresos, egresos, ganancia, proyección, gráfica mensual y alta de sellos |

Un reproductor común (`components/player`) permite escuchar desde cualquier vista.

### Despliegue

El proyecto **no está publicado en internet**; se despliega con Docker Compose en un solo equipo:

| Servicio | Función | Exposición |
|---|---|---|
| `proxy` (Caddy 2.10) | HTTPS y redirección | 80 y 443 |
| `app` (Laravel) | Aplicación | Solo detrás del proxy |
| `mongodb` (8.0) | Datos de dominio | Red interna |
| `mysql` (8.4) | Sesiones, caché, colas | `127.0.0.1:3306` |
| `wireguard` (opcional) | VPN administrativa | 51820/udp |

Para publicarlo en línea bastaría un servidor con dominio: cambiar `localhost` por el dominio en `docker/proxy/Caddyfile` (Caddy obtiene un certificado público automáticamente), fijar `APP_URL`, aplicar `docker/firewall/ufw-setup.sh` y definir `VPN_SERVER_URL`.

## 13. Consultas y agregaciones, comandos de administración y pruebas RBAC

**Consultas** (catálogo completo con su equivalente SQL en `docs/mongodb/consultas-crud.md`):

```js
db.songs.updateOne({ _id: songId }, { $inc: { plays: 1 } })
db.playlists.updateOne(
  { _id: playlistId, "songs.song_id": { $ne: songId }, "songs.499": { $exists: false } },
  { $push: { songs: { song_id: songId, added_at: new Date() } } })
db.royalties.findOneAndUpdate(
  { recipient_id: userId, amount: { $gt: NumberDecimal("0") } },
  { $set: { amount: NumberDecimal("0.00"), last_payment: new Date() } },
  { returnDocument: "before" })
```

**Agregaciones:**

```js
db.earnings.aggregate([
  { $match: { at: { $gte: seisMesesAtras } } },
  { $group: { _id: { $dateToString: { format: "%Y-%m", date: "$at" } }, sum: { $sum: "$amount" } } },
  { $sort: { _id: 1 } }])
db.users.aggregate([
  { $match: { role: "artist", label: { $ne: null } } },
  { $lookup: { from: "songs", localField: "_id", foreignField: "artist.user_id", as: "songs" } },
  { $group: { _id: "$label", plays: { $sum: { $sum: "$songs.plays" } } } }])
```

**Comandos de administración** (runbook completo en `docs/mongodb/comandos.md`):

```bash
docker exec melodic-app php artisan melodic:migrate-to-mongo --dry-run
docker exec melodic-app php artisan melodic:verify-mongo
docker exec melodic-mongodb sh -c 'mongodump --quiet --uri "mongodb://melodic_backup:$MONGO_BACKUP_PASSWORD@127.0.0.1:27017/?authSource=admin&tls=true&tlsCAFile=/run/secrets/mongodb_tls_ca" --db melodic --archive --gzip' > respaldo.archive.gz
docker exec melodic-wireguard wg show
```

El respaldo y la restauración se probaron en una base temporal: conteos, `dbHash` e índices idénticos en las 8 colecciones.

**Pruebas RBAC.** En la aplicación, las pruebas automáticas comprueban:

- El registro rechaza los roles `admin` y `label`.
- Un oyente recibe 403 en el panel del artista, y quien no es admin, en el de administración.
- Solo el dueño puede ver o modificar su playlist.
- Un oyente sin suscripción es enviado a Premium.

En la base de datos, con `mongosh`:

| Usuario | Prueba | Resultado |
|---|---|---|
| `melodic_app` | `dropDatabase` en `admin` | `not authorized` |
| `melodic_app` | Leer `config` u otra base | `not authorized` |
| `melodic_app` | Escribir en `melodic` | Permitido |
| `melodic_readonly` | Insertar en `melodic` | `not authorized` |
| `melodic_backup` | Insertar en `melodic` | `not authorized` |

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u melodic_app -p "$MONGO_APP_PASSWORD" --authenticationDatabase admin --eval "db.getSiblingDB(\"admin\").dropDatabase()"'
```

## 14. Limitaciones y trabajo futuro

**Limitaciones:**

- El replica set tiene un solo nodo: permite transacciones, pero no alta disponibilidad.
- El firewall está probado en un contenedor aislado, pero aplicarlo en el host requiere `sudo` y queda como paso manual.
- El sistema no está desplegado en internet, y los certificados son de CAs locales.
- La búsqueda por texto usa regex y recorre la colección completa.
- Las sesiones, la caché y las colas siguen en MySQL.
- No hay validadores `$jsonSchema` en las colecciones.
- La copia del artista en cada canción debe propagarse si algún día se permite editar el nombre de usuario.
- Las pruebas necesitan un MongoDB en ejecución.

**Trabajo futuro:**

- Replica set de tres nodos y respaldos programados.
- Validadores `$jsonSchema` por colección.
- Atlas Search o un índice de texto para la búsqueda (backlog C05).
- Escribir `plays` para estadísticas por oyente (C01).
- Retirar `legacy_id` cuando termine el periodo de transición.
- Llevar sesiones y caché a Redis.
- Despliegue en un servidor con dominio y certificado público.

## 15. Conclusiones

La migración se completó en un sprint con las 28 tareas cerradas. Melodic funciona sobre MongoDB con un modelo diseñado a partir de sus consultas: las playlists embebidas, la copia del artista y el saldo en su propia colección resuelven en un solo documento las operaciones más frecuentes, y cada consulta crítica tiene un índice comprobado con `explain()`.

El cambio de base de datos sirvió además para corregir defectos del sistema original: reproducir y retirar ahora son atómicos, el dinero se calcula sin errores de redondeo y el dashboard de administración funciona en cualquier entorno. La carga es repetible y verificable, y el corte se ensayó dos veces junto con una reversión de 31 segundos.

En seguridad, el sistema pasó de tener secretos en el repositorio y puertos abiertos a usar cifrado de extremo a extremo, usuarios con permisos mínimos, una red aislada, un firewall que cubre los puertos de Docker y acceso administrativo solo por VPN.

Las decisiones que cambiaron respecto al plan (referencias como texto, sin copia del álbum, corte por modelos) salieron de probar el diseño contra el código real, y quedaron documentadas junto con su motivo.

## Anexos

**Repositorio principal:** https://github.com/Theproxy206/Melodic-NoSQL

**Pull requests:**

| PR | Contenido |
|---|---|
| #1 | Replica set y conexión de Laravel (E1-T04) |
| #2 | Secretos fuera de `compose.yaml` (E2-T18) |
| #3 | Roles del registro público (E2-T15) |
| #4 | Límite de intentos (E2-T16) |
| #5 | Pruebas funcionales base (E1-T06) |
| #6 | Usuarios de mínimo privilegio (E2-T17) |
| #7 | Red interna para MongoDB (E4-T21) |
| #8 | TLS en MongoDB (E3-T19) |
| #9 | Inventario, diseño, índices, carga y catálogo CRUD (E1-T01 a E1-T07, E5-T25) |
| #10 | Verificación, modelos, playlists, dinero, dashboard, búsqueda y corte (E1-T08 a E1-T14) |
| #11 | HTTPS, firewall, VPN, hardening, explain, respaldo y runbook (E3-T20, E4-T22 a E4-T24, E5-T26, E6-T27, E6-T28) |

**Documentación del repositorio:**

| Documento | Contenido |
|---|---|
| `docs/scrum.md` | Plan del sprint y tarjetas |
| `docs/manual-usuario.md` | Manual de usuario |
| `docs/manual.md` | Instalación y configuración |
| `docs/mongodb/01-inventario.md` | Modelo relacional y catálogo de consultas |
| `docs/mongodb/02-diseno.md` | Colecciones, tipos e índices |
| `docs/mongodb/03-corte-y-reversion.md` | Procedimiento y bitácora del corte |
| `docs/mongodb/consultas-crud.md` | Consultas CRUD, agregaciones y explain |
| `docs/mongodb/hardening.md` | Evidencia de TLS y puertos |
| `docs/mongodb/comandos.md` | Runbook de operación |
| `docs/migracion-mongodb/FASE-0-LINEA-BASE.md` | Línea base inicial |
