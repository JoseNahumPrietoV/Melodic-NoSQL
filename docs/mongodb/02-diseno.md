# Diseño de colecciones

Parte de la sección 1.5 de `docs/scrum.md` y del inventario en `01-inventario.md`. Base de datos: `melodic`. Las referencias a casos de uso apuntan a la tabla "Consultas por controlador" del inventario.

## Colecciones

| Colección | Origen | Contenido |
|---|---|---|
| `users` | `users` | Cuentas de los cuatro roles |
| `subscriptions` | `subscriptions` | Catálogo de planes |
| `albums` | `albums` | Álbumes de cada artista |
| `songs` | `songs` | Canciones con un snapshot de su álbum y su artista |
| `playlists` | `playlists` + `playlist_song` | Playlists con sus canciones embebidas |
| `royalties` | `royalties` | Un monedero por destinatario |
| `earnings` | `earnings` | Ingresos de la plataforma, solo inserción |
| `expenses` | `expenses` | Pagos a artistas y labels, solo inserción |

`plays` no se migra: ningún código la escribe y el contador vive en `songs.plays` (backlog C01).

## Documentos de ejemplo

```js
// users
{ _id: "9b1c7e2a-4f0d-4c1e-9a55-2f6d3c8b1a01", username: "Carla Ríos", email: "carla@melodic.com",
  password: "$2y$12$...", role: "artist", is_suscribed: true, fin_subscription: null,
  subscription_id: null, label: "5e0a9c11-7b3d-4b8e-8f21-0c4d6a9e7f10", path: null,
  email_verified_at: null, remember_token: null,
  created_at: ISODate("2026-10-12T15:04:00Z"), updated_at: ISODate("2026-10-12T15:04:00Z") }

// subscriptions
{ _id: ObjectId("6710a0000000000000000001"), legacy_id: 1, name: "Mensual",
  cost: NumberDecimal("99.00"), months: 1,
  created_at: ISODate("2026-10-12T15:00:00Z"), updated_at: ISODate("2026-10-12T15:00:00Z") }

// albums
{ _id: ObjectId("6710a0000000000000000101"), legacy_id: 7, title: "Noches de octubre",
  path: "album_covers/noches.jpg", user_id: "9b1c7e2a-4f0d-4c1e-9a55-2f6d3c8b1a01",
  created_at: null, updated_at: null }

// songs
{ _id: ObjectId("6710a0000000000000000201"), legacy_id: 42, name: "Luna nueva", length: 0,
  plays: NumberLong(1280), file_path: "songs/audio/luna.mp3", image_path: null,
  album_id: ObjectId("6710a0000000000000000101"),
  album: { title: "Noches de octubre", path: "album_covers/noches.jpg" },
  artist: { user_id: "9b1c7e2a-4f0d-4c1e-9a55-2f6d3c8b1a01", username: "Carla Ríos" },
  created_at: ISODate("2026-10-13T18:20:00Z"), updated_at: ISODate("2026-10-18T09:11:00Z") }

// playlists
{ _id: ObjectId("6710a0000000000000000301"), legacy_id: 3, name: "Para correr", description: null,
  path: null, user_id: "1f4e8d22-0a6b-4c3f-b7e9-5d2a1c0e9b33",
  songs: [ { song_id: ObjectId("6710a0000000000000000201"), added_at: null } ],
  created_at: ISODate("2026-10-14T20:00:00Z"), updated_at: ISODate("2026-10-14T20:00:00Z") }

// royalties
{ _id: ObjectId("6710a0000000000000000401"), legacy_id: 2,
  recipient_id: "5e0a9c11-7b3d-4b8e-8f21-0c4d6a9e7f10", amount: NumberDecimal("192.00"),
  last_payment: ISODate("2026-10-16T12:00:00Z"),
  created_at: ISODate("2026-10-13T18:25:00Z"), updated_at: ISODate("2026-10-18T09:11:00Z") }

// earnings
{ _id: ObjectId("6710a0000000000000000501"), legacy_id: 15, amount: NumberDecimal("57.60"),
  state: "pagado", at: ISODate("2026-10-16T12:00:00Z"),
  subscription: null, comision: ObjectId("6710a0000000000000000401"),
  created_at: ISODate("2026-10-16T12:00:00Z"), updated_at: ISODate("2026-10-16T12:00:00Z") }

// expenses
{ _id: ObjectId("6710a0000000000000000601"), legacy_id: 9, amount: NumberDecimal("134.40"),
  state: "pagado", at: ISODate("2026-10-16T12:00:00Z"),
  from: ObjectId("6710a0000000000000000401"), user_id: "5e0a9c11-7b3d-4b8e-8f21-0c4d6a9e7f10",
  created_at: ISODate("2026-10-16T12:00:00Z"), updated_at: ISODate("2026-10-16T12:00:00Z") }
```

## Embebido o referencia

| Relación | Decisión | Justificación |
|---|---|---|
| `playlist_song` → `playlists.songs[]` | Embebido (referencias) | Mostrar una playlist, contar sus canciones y añadir una (`PlaylistController@show`, `@index`, `@addSong`, `DashboardController@user`) se resuelve en un documento. Añadir sin duplicar es un `updateOne` atómico. El arreglo guarda solo `song_id`, no la canción completa |
| `album` y `artist` → `songs` | Snapshot embebido | Explorar y buscar en playlist (`DashboardController@explore`, `PlaylistController@show`) filtran por `name` o por `artist.username` en una sola colección, sin `$lookup`. Reproducir (`SongController@registerPlay`) obtiene el destinatario sin leer el álbum |
| `songs.album_id` → `albums` | Referencia | Se conserva junto al snapshot para `AlbumController@show` y para contar canciones por álbum en `DashboardController@artist` |
| `albums.user_id` → `users` | Referencia | Un artista tiene pocos álbumes y `DashboardController@artist` los consulta solos |
| `users.label` → `users` | Referencia | `DashboardController@label` busca artistas por `label`; embeber artistas en el label duplicaría usuarios |
| `users.subscription_id` → `subscriptions` | Referencia | Catálogo pequeño que cambia aparte de los usuarios (`SubscriptionController`) |
| `royalties.recipient_id` → `users` | Referencia, colección propia | Cada reproducción hace `$inc` sobre el saldo. Embebido en `users` reescribiría el usuario y competiría con login y suscripción |
| `earnings.subscription`, `earnings.comision`, `expenses.from`, `expenses.user_id` | Referencia | Libros contables de solo inserción. Se agregan por fecha y por usuario (`AdminController`, `DashboardController@artist`) |

## Snapshot de álbum y artista en `songs`

- Campos: `album: { title, path }` y `artist: { user_id, username }`.
- Se escriben al crear la canción (`SongController@store`) y en la carga inicial.
- Hoy no hay flujos que editen `albums.title`, `albums.path` ni `users.username`. Si se agregan, deben actualizar el snapshot en la misma operación:
  - Álbum: `db.songs.updateMany({ album_id }, { $set: { "album.title": ..., "album.path": ... } })`.
  - Artista: `db.songs.updateMany({ "artist.user_id": id }, { $set: { "artist.username": ... } })`.
- `artist.user_id` es siempre el artista dueño del álbum. El label se resuelve desde `users.label` al pagar la regalía, porque un artista puede cambiar de label.

## Límite de canciones por playlist

- Máximo 500 canciones por playlist, para que el documento no crezca sin control.
- Se valida en la misma escritura, sin leer antes:

```js
db.playlists.updateOne(
  { _id: playlistId, user_id: userId, "songs.song_id": { $ne: songId }, "songs.499": { $exists: false } },
  { $push: { songs: { song_id: songId, added_at: new Date() } } }
)
```

- `matchedCount: 0` significa que la canción ya estaba, que la playlist está llena o que no es del usuario. La aplicación distingue el caso y responde como hoy.

## Lo que se queda en la base relacional

Sesiones, caché y colas siguen en la conexión relacional (SQLite local, MySQL en Docker) durante el sprint: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` y `migrations`. Al cambiar `DB_CONNECTION` a MongoDB en E1-T14 se fijan con `SESSION_CONNECTION`, `DB_CACHE_CONNECTION` y `DB_QUEUE_CONNECTION`.

## Tipos e índices

### Identificadores

- `users._id` conserva el UUID como string: lo genera la app y lo referencian `albums`, `playlists`, `royalties`, `expenses` y `users.label`.
- Las demás colecciones usan `ObjectId` y guardan `legacy_id` (el int original) durante la transición, para resolver las FK en la carga y comparar contra la base relacional. Las FK int se convierten al `ObjectId` del documento con ese `legacy_id`.
- Las vistas y rutas usan `song_id`, `album_id`, `playlist_id` y `user_id`. En E1-T09 cada modelo expone un accessor con ese nombre que devuelve su `_id` como string, para no tocar las vistas.

### Dinero

`Decimal128`, con el cast `decimal:2` de laravel-mongodb. Se probó contra MongoDB 8.0 y laravel-mongodb 5.8:

- El modelo devuelve un string (`'10.30'`); `number_format` y la aritmética con `float` funcionan en Blade y en PHP.
- `$inc` sobre un `Decimal128` conserva el tipo. Para evitar errores de redondeo, E1-T11 incrementa con `new Decimal128('0.15')` en lugar de un `float`.
- `sum()` del query builder devuelve un objeto `Decimal128`. E1-T12 lo convierte con `(string)` antes de operar.

Se descarta guardar centavos: obligaría a cambiar cada vista y cada cálculo.

### Conversión de tipos

| Origen (MySQL / SQLite) | MongoDB | Regla |
|---|---|---|
| PK int (`id`, `album_id`, `song_id`, ...) | `ObjectId` en `_id` y `Int32` en `legacy_id` | Se genera un `ObjectId` nuevo; el int se guarda aparte |
| UUID (`user_id`, `label`, `recipient_id`) | String | Sin cambios |
| FK int (`album_id`, `subscription_id`, `from`, `comision`, `subscription`) | `ObjectId` | Se busca el documento por `legacy_id` |
| `decimal(10,2)` y `decimal(13,2)` | `Decimal128` | Desde el string de la base, sin pasar por `float` |
| `timestamp` (`created_at`, `updated_at`, `at`, `last_payment`, `email_verified_at`) | `Date` UTC | La app usa `UTC`; se interpreta el valor como UTC |
| `date` (`fin_subscription`) | `Date` a las 00:00 UTC | |
| `boolean` (`is_suscribed`, 0/1) | `Boolean` | |
| `unsignedBigInteger` (`plays`) | `Int64` | |
| `tinyint` / `int` (`months`, `length`) | `Int32` | |
| `string` / `text` | String | |
| `NULL` | `null` explícito | Se guarda el campo con `null` en `label`, `subscription_id`, `fin_subscription` y los demás nulos, para conservar `whereNull` y `=== null` |

### Índices

| Colección | Índice | Consulta que lo justifica |
|---|---|---|
| `users` | `{ email: 1 }` único | Login (`LoginController@login`) y la regla `unique:users,email` del registro y de la creación de labels y artistas |
| `users` | `{ label: 1 }` | Artistas de un label (`DashboardController@label`) |
| `users` | `{ role: 1, created_at: -1 }` | Últimos 10 labels (`AdminController@index`) |
| `albums` | `{ user_id: 1 }` | Álbumes del artista (`DashboardController@artist`) |
| `songs` | `{ album_id: 1 }` | Canciones de un álbum (`AlbumController@show`) y conteo por álbum |
| `songs` | `{ "artist.user_id": 1 }` | Reproducciones por artista y por label (`DashboardController@label`) |
| `songs` | `{ created_at: -1 }` | Explorar sin búsqueda, por fecha (`DashboardController@explore`) |
| `playlists` | `{ user_id: 1, created_at: -1 }` | Playlists del usuario por fecha (`PlaylistController@index`, `DashboardController@user`) |
| `royalties` | `{ recipient_id: 1 }` único | Saldo en cada reproducción, retiro y panel; garantiza un monedero por destinatario |
| `earnings` | `{ at: 1 }` | Ingresos por mes y de los últimos 30 días (`AdminController@index`) |
| `expenses` | `{ user_id: 1, at: -1 }` | Últimos 5 retiros (`DashboardController@artist`, `@label`) |
| `subscriptions`, `albums`, `songs`, `playlists`, `royalties`, `earnings`, `expenses` | `{ legacy_id: 1 }` único | Upsert idempotente de la carga (E1-T07) y verificación (E1-T08) |

Consultas que recorren la colección completa, de forma aceptada:

- Búsqueda por `name` y `artist.username` con regex (`@explore`, `PlaylistController@show`): un `LIKE '%q%'` tampoco usa índice en SQL. Atlas Search o un índice de texto quedan en el backlog (C05).
- 6 canciones al azar (`DashboardController@user`): `$sample` no usa índices.
- `SUM` total de `earnings` y `expenses` y la lista de planes: frecuencia baja y colecciones pequeñas.
