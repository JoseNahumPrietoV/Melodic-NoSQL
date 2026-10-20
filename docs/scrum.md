# Plan Scrum · Migración de Melodic de SQL a MongoDB

| Dato | Valor |
|---|---|
| Duración | 1 sprint de 2 semanas · 10 días hábiles |
| Fechas | lunes 12/10/2026 → viernes 23/10/2026 |
| Equipo | 2 personas: **Persona A** (datos y backend) · **Persona B** (infraestructura, seguridad y backend) |
| Tablero | Microsoft Planner · depósitos `Diseño BackLog` → `TO DO` → `In progress` → `Done` |
| Rama de trabajo | `feature/mongodb` (`main` sigue en SQLite hasta el corte, tarea E1-T14) |

Objetivo del sprint: **Melodic funciona sobre MongoDB en el entorno Docker, con los datos migrados y verificados, conexiones cifradas, red endurecida y con la posibilidad de volver a SQLite.**

Las seis épicas corresponden a la lista de implementaciones de la BD en Planner:

| Épica | Elemento de la lista | Puntos |
|---|---|---|
| E1 | Migración de SQL a MongoDB | 37 |
| E2 | Seguridad: Control de Acceso y Autenticación | 5 |
| E3 | Cifrado en Tránsito de datos | 5 |
| E4 | Hardening de Red (VPN, Firewall, SSL, TLS) | 7 |
| E5 | Consultas CRUD | 5 |
| E6 | Comandos | 3 |
| | **Total del sprint** | **62** |

---

## 1. Análisis del repositorio

### 1.1 Stack actual

- Laravel 12 sobre PHP 8.2 o superior, con vistas Blade, Vite, Tailwind y Chart.js (este último por CDN en el dashboard de admin).
- La base de datos por defecto es SQLite (`DB_CONNECTION=sqlite`). Las pruebas usan SQLite en memoria (`phpunit.xml`).
- Sesiones, caché y colas usan el driver `database`, así que también viven en SQLite.
- `compose.yaml` ya define un servicio `mongo:8.0`, aunque la app todavía no lo usa: no está instalada la extensión `ext-mongodb` ni el paquete `mongodb/laravel-mongodb`.
- Hay cuatro roles: `user` (oyente), `artist`, `label` y `admin`. El control de acceso lo hacen los middleware `role`, `subscribed` y `admin`.

### 1.2 Inventario relacional

| Tabla | PK | Columnas relevantes | Relaciones |
|---|---|---|---|
| `subscriptions` | `id` (int) | `name`, `cost` decimal(10,2), `months` | 1:N con `users`, 1:N con `earnings` |
| `users` | `user_id` (UUID) | `username`, `email` único, `password`, `role`, `is_suscribed`, `fin_subscription` (fecha), `path`, `remember_token` | `subscription_id` → `subscriptions`; `label` → `users` (autorrelación: el label de un artista) |
| `albums` | `album_id` (int) | `title`, `path` | `user_id` → `users` (artista) |
| `songs` | `song_id` (int) | `name`, `length`, `plays` (contador), `file_path`, `image_path` | `album_id` → `albums` |
| `playlists` | `playlist_id` (int) | `name`, `description`, `path` | `user_id` → `users` |
| `playlist_song` | sin PK | `timestamps` | N:M entre `playlists` y `songs`, con `cascade` |
| `plays` | `play_id` (int) | `timestamps` | `song_id`, `user_id`. **Ningún código la escribe** |
| `royalties` | `royalties_id` (int) | `amount` decimal(13,2), `last_payment` | `recipient_id` → `users` (único: un monedero por artista o label) |
| `earnings` | `earning_id` (int) | `amount`, `state`, `at` | `subscription` → `subscriptions`; `comision` → `royalties` |
| `expenses` | `expense_id` (int) | `amount`, `state`, `at` | `from` → `royalties`; `user_id` → `users` |
| Tablas de framework | — | `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` | Se quedan en SQLite durante este sprint |

### 1.3 Patrones de lectura y escritura (los que guían el diseño)

| Caso de uso (controlador) | Operación actual | Frecuencia | Implicación en MongoDB |
|---|---|---|---|
| Dashboard de oyente (`DashboardController@user`) | 6 canciones al azar con `album.artist` y playlists del usuario con su número de canciones | Alta | `$sample` en `songs`, con el álbum y el artista embebidos como snapshot. El conteo sale de `$size` de un arreglo |
| Explorar (`@explore`) y buscar en playlist (`PlaylistController@show`) | `LIKE '%q%'` sobre `songs.name` o `users.username` (vía `album.artist`) | Alta | Regex insensible a mayúsculas sobre `name` y `artist.username` en una sola colección, sin join |
| Reproducir (`SongController@registerPlay`) | Transacción: `plays + 1` y luego regalías del artista o su label `+ 0.15` (leer, sumar y guardar) | **Muy alta** | `$inc` atómico en `songs` y upsert con `$inc` en `royalties` dentro de una transacción |
| Retiro (`WalletController`, `LabelController@withdraw`) | Transacción: leer saldo → `expense` 70 % → `earning` 30 % → saldo a 0 | Baja | `findOneAndUpdate` que lee el saldo y lo pone en 0 en un solo paso, más dos inserts, todo en una transacción |
| Suscripción (`SubscriptionController@store`) | Insertar `earning` y actualizar el usuario | Baja | Dos escrituras sin transacción, igual que hoy |
| Dashboard de artista y label | Álbumes con conteo de canciones, artistas del label con álbumes y canciones, saldo y últimos 5 retiros | Media | Índices `albums.user_id`, `users.label` y `expenses{user_id, at}`. Agregación de `plays` por `artist.user_id` |
| Dashboard de admin | `SUM` de ingresos y egresos, ingresos por mes de los últimos 6 meses, últimos 10 labels | Baja | `$group` con `$dateToString` e índice `earnings.at` |
| Playlists (`PlaylistController@addSong`) | Comprobar si la canción existe y luego `attach` (pivote) | Media | `updateOne` con condición `songs.song_id $ne` y `$push`: atómico y sin duplicados |

### 1.4 Hallazgos que afectan la migración

| # | Severidad | Hallazgo | Se resuelve en |
|---|---|---|---|
| 1 | Alta | `RegisterController` asigna el `role` directamente desde `account_type` del formulario. Cualquiera puede registrarse como `admin` o `label`. | E2-T15 |
| 2 | Alta | `compose.yaml` incluye en el repositorio el `APP_KEY`, las credenciales `root/root` de MongoDB y `APP_DEBUG=true`, y publica el puerto 27017 en el host. | E2-T18, E4-T21 |
| 3 | Alta | Condiciones de carrera con dinero. `registerPlay` lee, suma y guarda, así que pierde incrementos concurrentes. `withdraw` lee el saldo y después lo pone en 0, así que borra las regalías generadas entre la lectura y la escritura. | E1-T11 |
| 4 | Media | El retiro está implementado tres veces y cada copia hace algo distinto. `DashboardController::withdraw` no tiene ruta y no guarda `user_id`. `LabelController::withdraw` no guarda `from` ni `comision`. Solo `WalletController::withdraw` está completo. | E1-T11 |
| 5 | Media | `AdminController` usa `DATE_FORMAT`, que es exclusivo de MySQL. Con SQLite, la conexión por defecto, el dashboard de admin falla. | E1-T12 |
| 6 | Media | Los montos se calculan con `float` (`amount * 0.30`) y pueden aparecer errores de redondeo. | E1-T03, E1-T11 |
| 7 | Baja | El modelo y la tabla `plays` existen, pero nunca se escriben. Solo se usa el contador `songs.plays`. | Backlog C01 |
| 8 | Baja | El `down()` de `create_songs_table` borra `songs` antes que `plays`, que tiene una FK hacia `songs`. El rollback SQL falla. | Backlog C04 |
| 9 | Baja | `SongController@store` crea el álbum y la canción sin transacción, así que puede quedar un álbum huérfano. | E1-T09 (nota) |
| 10 | Info | No hay pruebas reales, solo `ExampleTest`. No existe una base para comprobar la equivalencia. | E1-T06 |

### 1.5 Diseño documental propuesto (punto de partida para E1-T02 y E1-T03)

**Decisiones globales**

- **Identificadores.** `users._id` conserva el UUID actual como string, porque ya lo genera la app y lo referencian 5 tablas. Las demás colecciones usan `ObjectId` y guardan `legacy_id` (el int original, con índice único) mientras dure la transición, para mapear las FK durante la carga y comparar contra SQLite. Las vistas usan `song_id`, `album_id` y `user_id`, así que los modelos exponen accessors con esos nombres.
- **Dinero.** `Decimal128`. Si el cast de `laravel-mongodb` da problemas, la alternativa es guardar enteros en centavos. La decisión final se toma en E1-T03.
- **Fechas.** BSON `Date` en UTC. `fin_subscription` se guarda a las 00:00 UTC.
- **Nulos.** Se guarda `null` explícito en `label`, `subscription_id` y `fin_subscription`, para conservar la semántica de `whereNull` y de `=== null` en el código.
- **Transacciones.** MongoDB se configura como replica set de un nodo (`rs0`), condición necesaria para las transacciones multi-documento. Se usan solo en reproducir y en retirar.
- **Desnormalización.** Cada canción embebe `album{title, path}` y `artist{user_id, username}`. Hoy no existe ningún flujo que edite el username ni el título del álbum, así que el riesgo de datos desactualizados es bajo. Si se agrega un flujo de edición, deberá propagar el cambio.

```js
// users — se conserva el UUID como _id
{ _id: "uuid", username, email, password, role: "user|artist|label|admin",
  is_suscribed: Boolean, fin_subscription: Date|null, subscription_id: ObjectId|null,
  label: "uuid"|null, path, email_verified_at, remember_token, created_at, updated_at }
// índices: {email:1} único · {label:1} · {role:1, created_at:-1}

// subscriptions — catálogo pequeño, referenciado
{ _id: ObjectId, legacy_id: Int, name, cost: Decimal128, months: Int, created_at, updated_at }

// albums — referencia al artista
{ _id: ObjectId, legacy_id, title, path, user_id: "uuid", created_at, updated_at }
// índices: {user_id:1}

// songs — colección propia (se consulta sola en explorar, reproducir y playlists)
{ _id: ObjectId, legacy_id, name, length: Int, plays: Long, file_path, image_path,
  album_id: ObjectId, album: { title, path }, artist: { user_id: "uuid", username },
  created_at, updated_at }
// índices: {album_id:1} · {"artist.user_id":1} · {created_at:-1}

// playlists — el pivote playlist_song queda embebido como arreglo de referencias
{ _id: ObjectId, legacy_id, name, description, path, user_id: "uuid",
  songs: [ { song_id: ObjectId, added_at: Date } ],   // máximo 500 por validación
  created_at, updated_at }
// índices: {user_id:1, created_at:-1}

// royalties — monedero, 1 por destinatario; colección aparte para que el $inc de cada play no reescriba users
{ _id: ObjectId, legacy_id, recipient_id: "uuid", amount: Decimal128, last_payment: Date|null, created_at, updated_at }
// índices: {recipient_id:1} único

// earnings / expenses — libro contable de solo inserción
{ _id: ObjectId, legacy_id, amount: Decimal128, state, at: Date,
  subscription: ObjectId|null, comision: ObjectId|null, created_at, updated_at }   // earnings · índice {at:1}
{ _id: ObjectId, legacy_id, amount: Decimal128, state, at: Date,
  from: ObjectId|null, user_id: "uuid"|null, created_at, updated_at }             // expenses · índice {user_id:1, at:-1}
```

Las búsquedas `LIKE '%q%'` tampoco usan índice en SQL. En MongoDB se acepta un recorrido completo de la colección con regex mientras el catálogo sea pequeño. La migración a Atlas Search o a un índice de texto queda en el backlog (C05).

### 1.6 Carga, sincronización y reversión

1. Se pone la app en mantenimiento (`php artisan down`) y se respaldan el archivo SQLite y la base de MongoDB con `mongodump`.
2. Se ejecuta el comando idempotente de carga, que hace upsert por `legacy_id` en este orden: `subscriptions` → `users` → `albums` → `songs` → `playlists` → `royalties` → `earnings` → `expenses`.
3. Se ejecuta el comando de verificación: conteos, sumas de montos, FK resueltas y muestras de documentos.
4. Se cambia a `DB_CONNECTION=mongodb`. Sesiones, caché y colas se dejan fijas en SQLite (`SESSION_CONNECTION`, `DB_CACHE_CONNECTION`, `DB_QUEUE_CONNECTION`).
5. **Reversión:** volver a `DB_CONNECTION=sqlite` y revertir el merge de `feature/mongodb`. El archivo SQLite no se toca durante la carga. Se puede revertir sin perder datos solo **antes de reabrir el tráfico**. Después, las escrituras hechas en MongoDB requieren conciliación manual.

No hay doble escritura durante la transición, porque sería demasiado compleja para dos semanas. La sincronización se resuelve con una ventana de mantenimiento corta.

---

## 2. Capacidad y ponderación

| Puntos | Esfuerzo aproximado |
|---|---|
| 1 | menos de 2 h |
| 2 | medio día |
| 3 | 1 día |
| 5 | 2 días |
| 8 | 3–4 días. Es el máximo: si una tarea pasa de aquí, se divide |

- Capacidad: 2 personas × 10 días × 3 pts por día = **60 pts**.
- Planificado: **62 pts**. Persona A tiene 31 y Persona B tiene 31.
- Las tareas **Must** suman 55 pts (92 % de la capacidad). Las 4 tareas **Should** suman 7 pts y son las primeras que se recortan si el sprint se retrasa: E2-T16, E3-T20, E4-T23 y E6-T28.

**Correspondencia de prioridades con Planner**

| Planner | Significado |
|---|---|
| Urgente | Must que bloquea a otras tarjetas o corrige un riesgo de seguridad alto |
| Importante | Must |
| Media | Should: está dentro del sprint, pero es lo primero que se recorta |
| Baja | Could: se queda en `Diseño BackLog`, fuera del sprint |

**Ceremonias:** planning el lunes 12/10 a primera hora (30 min), daily de 10 min, revisión y retrospectiva el viernes 23/10 (45 min).

---

## 3. Formato de tarjeta para Planner

| Campo de Planner | Cómo se llena |
|---|---|
| **Título** | `[E#-T##] (N pts) Verbo + objeto`. Los puntos van en el título porque Planner no tiene campo de estimación |
| **Etiquetas** | Épica + tipo + puntos (ver el catálogo abajo) |
| **Asignar a** | Persona A o Persona B |
| **Estado** | `No iniciado` al crearla |
| **Prioridad** | Urgente, Importante, Media o Baja (ver la correspondencia de la sección 2) |
| **Fecha de inicio / vencimiento** | Las del calendario de la sección 4 |
| **Repetir** | `No se repite` en todas, por eso no aparece en cada tarjeta |
| **Depósito** | `TO DO` para las tareas del sprint, `Diseño BackLog` para las Could |
| **Lista de comprobación** | Criterios de aceptación, máximo 20 elementos de hasta 100 caracteres cada uno. El último siempre es "DoD cumplida" |
| **Notas** | Bloque de texto plano: historia, descripción, dependencias, archivos, registro MongoDB, validación y riesgo |

**Catálogo de etiquetas (16 de las 25 que permite Planner)**

`E1 Migración` · `E2 Acceso/Auth` · `E3 Cifrado` · `E4 Hardening` · `E5 CRUD` · `E6 Comandos` · `Backend` · `Datos` · `Infra` · `Seguridad` · `Docs` · `Test` · `1 pt` · `2 pts` · `3 pts` · `5 pts`

**Definición de terminado (DoD) para todas las tarjetas**

1. Todos los criterios de la lista de comprobación están marcados.
2. `composer test` pasa. Si la tarjeta cambia vistas o JS, `npm run build` también. Si un comando no se puede ejecutar, se anota la limitación en Notas.
3. Si la tarjeta toca persistencia, el registro MongoDB está completo: colección, forma, índices, IDs y reversión.
4. El PR hacia `feature/mongodb` está revisado por la otra persona.
5. No se suben `.env`, credenciales, certificados ni bases de datos locales.

---

## 4. Calendario

| Día | Fecha | Persona A | Persona B |
|---|---|---|---|
| D1 | lun 12/10 | E1-T01, E1-T02 | E1-T04, E2-T18 |
| D2 | mar 13/10 | E1-T02, E1-T03 | E1-T04, E2-T15, E2-T16 |
| D3 | mié 14/10 | E1-T05, E1-T07 | E1-T06 |
| D4 | jue 15/10 | E1-T07 | E2-T17, E4-T21 |
| D5 | vie 16/10 | E5-T25 | E3-T19 |
| D6 | lun 19/10 | E1-T08, E1-T09 | E4-T22, E4-T24 |
| D7 | mar 20/10 | E1-T09 | E4-T23 |
| D8 | mié 21/10 | E1-T09, E1-T10 | E5-T26, E6-T28 |
| D9 | jue 22/10 | E1-T11 | E1-T12, E1-T13 |
| D10 | vie 23/10 | E1-T11, E1-T14 | E6-T27, E3-T20 |

Ruta crítica: **T02 → T03 → T05 → T07 → T08 → T14**, en paralelo con **T04 → T06 → T09 → T11 → T14**.

---

## 5. Épicas, historias y tarjetas

### E1 · Migración de SQL a MongoDB (37 pts)

**H1.1** Como equipo de desarrollo, quiero conocer el modelo relacional y sus patrones de acceso, para diseñar los documentos a partir del uso real.
**H1.2** Como equipo de desarrollo, quiero un esquema documental justificado, para que la migración no sea un copiado mecánico de tablas.
**H1.3** Como equipo de desarrollo, quiero que Laravel se conecte a MongoDB sin dejar de usar SQLite, para migrar por etapas.
**H1.4** Como equipo de desarrollo, quiero pruebas de los flujos críticos, para detectar regresiones al cambiar la persistencia.
**H1.5** Como equipo de desarrollo, quiero cargar y verificar los datos existentes en MongoDB, para no perder información.
**H1.6** Como usuario de Melodic (oyente, artista, label o admin), quiero que la aplicación funcione igual sobre MongoDB.
**H1.7** Como equipo de desarrollo, quiero un corte controlado con reversión, para volver a SQLite si algo falla.

---

#### E1-T01 · Formalizar el inventario relacional y el catálogo de consultas

| Planner | Valor |
|---|---|
| Título | `[E1-T01] (1 pt) Formalizar inventario relacional y catálogo de consultas` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona A |
| Fecha de inicio | 12/10/2026 |
| Fecha de vencimiento | 12/10/2026 |
| Etiquetas | `E1 Migración` `Docs` `1 pt` |

**Lista de comprobación**
- [ ] Crear docs/mongodb/01-inventario.md a partir de las secciones 1.2 y 1.3 de scrum.md
- [ ] Diagrama ER de tablas y FK (Mermaid)
- [ ] Tabla de consultas por controlador con su frecuencia estimada
- [ ] Revisar la lista de hallazgos (1.4) con el equipo
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.1
DESCRIPCIÓN: Llevar el análisis de scrum.md a un documento de referencia propio y validarlo
contra el código. Es la fuente para justificar índices y decisiones de embebido.
DEPENDE DE: —
BLOQUEA A: E1-T02
ARCHIVOS: database/migrations/*, app/Models/*, app/Http/Controllers/*, docs/mongodb/01-inventario.md
VALIDACIÓN: Revisión cruzada con Persona B.
RIESGO: Bajo.
```

---

#### E1-T02 · Diseñar las colecciones y la forma de los documentos

| Planner | Valor |
|---|---|
| Título | `[E1-T02] (3 pts) Diseñar colecciones y forma de los documentos` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Urgente |
| Asignar a | Persona A |
| Fecha de inicio | 12/10/2026 |
| Fecha de vencimiento | 13/10/2026 |
| Etiquetas | `E1 Migración` `Datos` `3 pts` |

**Lista de comprobación**
- [ ] Crear docs/mongodb/02-diseno.md con las 8 colecciones y un documento de ejemplo de cada una
- [ ] Justificar embebido o referencia para cada relación
- [ ] Decidir el snapshot album/artist en songs y las reglas para actualizarlo
- [ ] Definir el límite de canciones por playlist (500) y su validación
- [ ] Listar las tablas que se quedan en SQLite (sesiones, caché, colas)
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.2
DESCRIPCIÓN: Partir de la sección 1.5 de scrum.md. Cada decisión debe citar el caso de uso
de la sección 1.3 que la justifica.
DEPENDE DE: E1-T01
BLOQUEA A: E1-T03, E5-T25
ARCHIVOS: docs/mongodb/02-diseno.md
MONGODB:
  Colecciones: users, subscriptions, albums, songs, playlists, royalties, earnings, expenses
  Embebido: playlist_song → playlists.songs[]; album/artist → songs (snapshot)
  Referencia: albums.user_id, songs.album_id, users.label, users.subscription_id, royalties.recipient_id
  Reversión: solo es documento; no aplica.
VALIDACIÓN: Revisión por Persona B.
RIESGO: Medio. Si se desnormaliza de más, aparecen inconsistencias.
```

---

#### E1-T03 · Definir identificadores, tipos, nulos e índices

| Planner | Valor |
|---|---|
| Título | `[E1-T03] (2 pts) Definir IDs, tipos, nulos e índices` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Urgente |
| Asignar a | Persona A |
| Fecha de inicio | 13/10/2026 |
| Fecha de vencimiento | 13/10/2026 |
| Etiquetas | `E1 Migración` `Datos` `2 pts` |

**Lista de comprobación**
- [ ] IDs: UUID como _id en users; ObjectId más legacy_id en el resto
- [ ] Dinero: confirmar el cast de Decimal128 en laravel-mongodb o elegir centavos
- [ ] Tabla de conversión de tipos: decimal, timestamp, date, boolean, null
- [ ] Lista de índices, cada uno con la consulta que lo justifica
- [ ] Accessors song_id, album_id, playlist_id, user_id para las vistas
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.2
DESCRIPCIÓN: Cerrar las decisiones de tipos e identificadores antes de escribir el código de carga.
DEPENDE DE: E1-T02
BLOQUEA A: E1-T05, E1-T07, E1-T09
ARCHIVOS: docs/mongodb/02-diseno.md (sección "Tipos e índices")
MONGODB:
  Índices: users{email:1} único, users{label:1}, users{role:1,created_at:-1},
           albums{user_id:1}, songs{album_id:1}, songs{"artist.user_id":1}, songs{created_at:-1},
           playlists{user_id:1,created_at:-1}, royalties{recipient_id:1} único,
           earnings{at:1}, expenses{user_id:1,at:-1}, {legacy_id:1} único en cada colección con ObjectId
  Fechas: BSON Date en UTC. Nulos: null explícito en label, subscription_id, fin_subscription.
VALIDACIÓN: Cada consulta de la sección 1.3 tiene un índice o una justificación de COLLSCAN.
RIESGO: Medio. Decimal128 puede no castear bien a float en Blade.
```

---

#### E1-T04 · Levantar MongoDB como replica set y conectarlo a Laravel

| Planner | Valor |
|---|---|
| Título | `[E1-T04] (3 pts) MongoDB replica set + conexión Laravel` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Urgente |
| Asignar a | Persona B |
| Fecha de inicio | 12/10/2026 |
| Fecha de vencimiento | 13/10/2026 |
| Etiquetas | `E1 Migración` `Infra` `3 pts` |

**Lista de comprobación**
- [ ] Instalar ext-mongodb (pecl) en el Dockerfile
- [ ] composer require mongodb/laravel-mongodb (versión compatible con Laravel 12)
- [ ] Agregar la conexión 'mongodb' en config/database.php sin cambiar la conexión por defecto
- [ ] Configurar mongod con --replSet rs0 y keyFile (obligatorio con auth) y rs.initiate()
- [ ] Healthcheck que valide que rs.status().ok es 1
- [ ] La app sigue funcionando con SQLite (composer test pasa)
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.3
DESCRIPCIÓN: Las transacciones multi-documento (reproducir y retirar) requieren replica set.
El keyFile se genera localmente y no se sube al repositorio (.gitignore).
DEPENDE DE: —
BLOQUEA A: E1-T05, E2-T17, E3-T19, E4-T21
ARCHIVOS: Dockerfile, compose.yaml, composer.json, composer.lock, config/database.php, .env.example
MONGODB: Conexión 'mongodb' con DSN desde MONGODB_URI. Reversión: quitar la conexión; el default sigue en sqlite.
VALIDACIÓN: docker compose up; php artisan tinker → DB::connection('mongodb')->getMongoClient()->listDatabases()
RIESGO: Medio. Problemas al compilar ext-mongodb en la imagen.
```

---

#### E1-T05 · Crear las colecciones e índices con una migración nueva

| Planner | Valor |
|---|---|
| Título | `[E1-T05] (1 pt) Migración de colecciones e índices MongoDB` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona A |
| Fecha de inicio | 14/10/2026 |
| Fecha de vencimiento | 14/10/2026 |
| Etiquetas | `E1 Migración` `Datos` `1 pt` |

**Lista de comprobación**
- [ ] Migración nueva con $connection = 'mongodb' (las migraciones existentes no se tocan)
- [ ] Crear todos los índices definidos en E1-T03
- [ ] down() elimina solo los índices y colecciones nuevos
- [ ] Ejecutar migrate y luego migrate:rollback sin errores
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.3
DESCRIPCIÓN: Los índices se versionan junto con el código.
DEPENDE DE: E1-T03, E1-T04
BLOQUEA A: E1-T07, E5-T26
ARCHIVOS: database/migrations/2026_10_14_000000_create_mongodb_collections.php
MONGODB: Índices de E1-T03. Reversión: php artisan migrate:rollback --step=1
VALIDACIÓN: mongosh → db.<col>.getIndexes() en cada colección.
RIESGO: Bajo.
```

---

#### E1-T06 · Escribir pruebas funcionales de los flujos críticos (baseline en SQLite)

| Planner | Valor |
|---|---|
| Título | `[E1-T06] (3 pts) Pruebas funcionales de flujos críticos (baseline)` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 14/10/2026 |
| Fecha de vencimiento | 14/10/2026 |
| Etiquetas | `E1 Migración` `Test` `3 pts` |

**Lista de comprobación**
- [ ] Factories para Subscription, Album, Song, Playlist y Royalty
- [ ] Pruebas de registro, login y redirección por rol
- [ ] Prueba de suscripción: earning creado y fin_subscription calculado
- [ ] Prueba de reproducción: plays + 1 y regalía de 0.15 al artista o a su label
- [ ] Prueba de retiro: 70 % en expense, 30 % en earning, saldo en 0
- [ ] Pruebas de playlist (crear, añadir sin duplicar, 403 si no es el dueño) y explorar
- [ ] Dashboard admin marcado como fallo conocido (DATE_FORMAT en SQLite)
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.4
DESCRIPCIÓN: Estas pruebas son el contrato de comportamiento. Deben pasar igual en SQLite y en MongoDB.
DEPENDE DE: —
BLOQUEA A: E1-T09, E1-T14
ARCHIVOS: tests/Feature/*, database/factories/*
VALIDACIÓN: composer test
RIESGO: Medio. Sin estas pruebas no se puede demostrar la equivalencia.
```

---

#### E1-T07 · Construir el comando de carga inicial a MongoDB

| Planner | Valor |
|---|---|
| Título | `[E1-T07] (5 pts) Comando artisan de carga inicial a MongoDB` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Urgente |
| Asignar a | Persona A |
| Fecha de inicio | 14/10/2026 |
| Fecha de vencimiento | 15/10/2026 |
| Etiquetas | `E1 Migración` `Datos` `5 pts` |

**Lista de comprobación**
- [ ] Comando melodic:migrate-to-mongo con opciones --collection y --dry-run
- [ ] Orden: subscriptions, users, albums, songs, playlists, royalties, earnings, expenses
- [ ] Upsert por legacy_id para que sea idempotente (se puede ejecutar dos veces)
- [ ] Mapear las FK int a ObjectId mediante legacy_id
- [ ] Construir el snapshot album/artist en songs y el arreglo songs[] en playlists
- [ ] Convertir tipos (Decimal128, Date UTC, bool, null) según E1-T03
- [ ] Reporte de documentos leídos, escritos y con error
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.5
DESCRIPCIÓN: Lee de la conexión sqlite y escribe en la conexión mongodb. No modifica SQLite.
DEPENDE DE: E1-T05
BLOQUEA A: E1-T08, E5-T26
ARCHIVOS: app/Console/Commands/MigrateToMongo.php
MONGODB: Todas las colecciones de negocio. Reversión: db.dropDatabase() en el entorno de prueba y volver a ejecutar.
VALIDACIÓN: Ejecutar sobre una base sembrada (db:seed más datos de factories) dos veces seguidas; los conteos no cambian.
RIESGO: Alto. Es la tarea más grande de la ruta crítica.
```

---

#### E1-T08 · Construir el comando de verificación de equivalencia

| Planner | Valor |
|---|---|
| Título | `[E1-T08] (2 pts) Comando de verificación de equivalencia` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona A |
| Fecha de inicio | 19/10/2026 |
| Fecha de vencimiento | 19/10/2026 |
| Etiquetas | `E1 Migración` `Test` `2 pts` |

**Lista de comprobación**
- [ ] Comparar el conteo de cada tabla con su colección
- [ ] Comparar SUM(amount) de royalties, earnings y expenses al centavo
- [ ] Comparar SUM(plays) de songs y el número de canciones por playlist
- [ ] Comprobar que todas las referencias resuelven (sin huérfanos)
- [ ] Comparar campo por campo una muestra de 20 documentos por colección
- [ ] Devolver código de salida distinto de 0 si hay diferencias
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.5
DESCRIPCIÓN: Es el criterio objetivo para decidir el corte (go/no-go).
DEPENDE DE: E1-T07
BLOQUEA A: E1-T14
ARCHIVOS: app/Console/Commands/VerifyMongoMigration.php
VALIDACIÓN: php artisan melodic:verify-mongo → 0 diferencias.
RIESGO: Bajo.
```

---

#### E1-T09 · Migrar los modelos Eloquent a laravel-mongodb

| Planner | Valor |
|---|---|
| Título | `[E1-T09] (5 pts) Migrar modelos Eloquent a laravel-mongodb` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Urgente |
| Asignar a | Persona A |
| Fecha de inicio | 19/10/2026 |
| Fecha de vencimiento | 21/10/2026 |
| Etiquetas | `E1 Migración` `Backend` `5 pts` |

**Lista de comprobación**
- [ ] User sobre la base de autenticación del paquete, con _id UUID y accessor user_id
- [ ] Subscription, Album, Song, Royalty, Earning y Expense con $connection = 'mongodb'
- [ ] Relaciones belongsTo y hasMany equivalentes; accessors song_id y album_id
- [ ] Validaciones unique y exists con Rule::unique/exists(Model::class) en lugar de nombres de tabla
- [ ] Route model binding de {album}, {song} y {playlist} con ObjectId
- [ ] SongController@store: mantener el snapshot artist/album al crear la canción
- [ ] Las pruebas de E1-T06 pasan contra MongoDB
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.6
DESCRIPCIÓN: Cambiar la persistencia sin cambiar el comportamiento. Las vistas usan song_id, album_id
y user_id: no renombrar, exponer accessors.
DEPENDE DE: E1-T03, E1-T06
BLOQUEA A: E1-T10, E1-T11, E1-T12, E1-T13
ARCHIVOS: app/Models/*, app/Http/Controllers/{Register,Admin,Label,Song,Subscription}Controller.php, config/auth.php
MONGODB: Todas las colecciones. Reversión: revertir el PR; los modelos vuelven a usar la conexión sqlite.
VALIDACIÓN: composer test con DB_CONNECTION=mongodb (servicio en Docker); recorrido manual con los 4 roles.
RIESGO: Alto. Autenticación y sesiones con un _id string.
```

---

#### E1-T10 · Embeber las canciones en las playlists

| Planner | Valor |
|---|---|
| Título | `[E1-T10] (2 pts) Playlists con canciones embebidas` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona A |
| Fecha de inicio | 21/10/2026 |
| Fecha de vencimiento | 21/10/2026 |
| Etiquetas | `E1 Migración` `Backend` `2 pts` |

**Lista de comprobación**
- [ ] addSong con updateOne {songs.song_id: {$ne: id}} y $push (atómico, sin duplicados)
- [ ] Rechazar la canción si la playlist ya tiene 500
- [ ] withCount('songs') sustituido por el tamaño del arreglo en index y en el dashboard
- [ ] show carga los detalles con songs.whereIn(_id) y respeta el orden de inserción
- [ ] Las pruebas de playlist de E1-T06 pasan
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.6
DEPENDE DE: E1-T09
BLOQUEA A: E1-T14
ARCHIVOS: app/Models/Playlist.php, app/Http/Controllers/PlaylistController.php, app/Http/Controllers/DashboardController.php
MONGODB: playlists.songs[] = {song_id: ObjectId, added_at: Date}. Índice {user_id:1, created_at:-1}.
  Reversión: revertir el PR.
VALIDACIÓN: composer test (filtro Playlist).
RIESGO: Bajo.
```

---

#### E1-T11 · Hacer atómicos los flujos de dinero (reproducir y retirar)

| Planner | Valor |
|---|---|
| Título | `[E1-T11] (5 pts) Flujos de dinero atómicos: reproducir y retirar` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Urgente |
| Asignar a | Persona A |
| Fecha de inicio | 22/10/2026 |
| Fecha de vencimiento | 23/10/2026 |
| Etiquetas | `E1 Migración` `Backend` `5 pts` |

**Lista de comprobación**
- [ ] registerPlay: $inc en songs.plays y upsert con $inc en royalties, dentro de una transacción
- [ ] Unificar los tres withdraw en un solo servicio (WalletService)
- [ ] Retiro: findOneAndUpdate {amount > 0} que pone amount en 0 y devuelve el saldo anterior
- [ ] El expense guarda from y user_id; el earning guarda comision
- [ ] Calcular el 70/30 con Decimal128 o bcmath, sin float
- [ ] Prueba concurrente: N reproducciones en paralelo suman exactamente N × 0.15
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.6
DESCRIPCIÓN: Corrige los hallazgos 3, 4 y 6. Unificar el retiro es necesario porque migrar
tres copias distintas multiplicaría los errores.
DEPENDE DE: E1-T09
BLOQUEA A: E1-T14
ARCHIVOS: app/Services/WalletService.php (nuevo), SongController.php, WalletController.php,
  LabelController.php, DashboardController.php (eliminar withdraw sin ruta)
MONGODB: songs, royalties, earnings, expenses. Transacción con DB::connection('mongodb')->transaction().
  Requiere el replica set de E1-T04. Reversión: revertir el PR.
VALIDACIÓN: composer test (filtros Play y Withdraw).
RIESGO: Alto. Es dinero; en las pruebas no se acepta ningún redondeo.
```

---

#### E1-T12 · Reescribir el dashboard de admin con agregaciones

| Planner | Valor |
|---|---|
| Título | `[E1-T12] (2 pts) Dashboard admin con agregaciones MongoDB` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 22/10/2026 |
| Fecha de vencimiento | 22/10/2026 |
| Etiquetas | `E1 Migración` `Backend` `2 pts` |

**Lista de comprobación**
- [ ] Sustituir DATE_FORMAT por $match {at >= -6 meses} + $group por $dateToString '%Y-%m'
- [ ] Totales de earnings y expenses con $group y $sum
- [ ] Últimos 10 labels con el índice {role:1, created_at:-1}
- [ ] La gráfica de Chart.js muestra los mismos meses y montos que la muestra de referencia
- [ ] Quitar la marca de fallo conocido de la prueba del dashboard admin
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.6
DESCRIPCIÓN: Corrige el hallazgo 5. Hoy el dashboard no funciona con SQLite.
DEPENDE DE: E1-T09
BLOQUEA A: E1-T14
ARCHIVOS: app/Http/Controllers/AdminController.php
MONGODB: earnings (índice {at:1}), expenses, users. Reversión: revertir el PR.
VALIDACIÓN: composer test (filtro Admin) y revisar la gráfica en el navegador.
RIESGO: Bajo.
```

---

#### E1-T13 · Migrar la búsqueda de explorar y de playlists

| Planner | Valor |
|---|---|
| Título | `[E1-T13] (1 pt) Búsqueda en explorar y playlists sobre MongoDB` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 22/10/2026 |
| Fecha de vencimiento | 22/10/2026 |
| Etiquetas | `E1 Migración` `Backend` `1 pt` |

**Lista de comprobación**
- [ ] $or con regex insensible a mayúsculas sobre name y artist.username (texto escapado con preg_quote)
- [ ] Explorar sin búsqueda: ordenar por created_at descendente
- [ ] Dashboard de oyente: $sample de 6 canciones en lugar de inRandomOrder
- [ ] Excluir de la búsqueda las canciones que ya están en la playlist ($nin)
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.6
DEPENDE DE: E1-T09
BLOQUEA A: E1-T14
ARCHIVOS: app/Http/Controllers/DashboardController.php, app/Http/Controllers/PlaylistController.php
MONGODB: songs. La regex no usa índice, igual que el LIKE '%q%' actual (justificado en E1-T03).
  Reversión: revertir el PR.
VALIDACIÓN: composer test (filtro Explore).
RIESGO: Bajo. Escapar la regex evita inyección de patrones.
```

---

#### E1-T14 · Ejecutar el corte a MongoDB y ensayar la reversión

| Planner | Valor |
|---|---|
| Título | `[E1-T14] (2 pts) Corte a MongoDB y ensayo de reversión` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona A |
| Fecha de inicio | 23/10/2026 |
| Fecha de vencimiento | 23/10/2026 |
| Etiquetas | `E1 Migración` `Infra` `2 pts` |

**Lista de comprobación**
- [ ] Escribir docs/mongodb/03-corte-y-reversion.md con los criterios de go/no-go
- [ ] Fijar SESSION_CONNECTION, DB_CACHE_CONNECTION y DB_QUEUE_CONNECTION en sqlite
- [ ] Revisar queue.failed.database para que no cambie a mongodb
- [ ] Ensayo completo: down, respaldo, carga, verificación, cambio de conexión, up
- [ ] Ensayar la reversión a SQLite y confirmar que la app funciona
- [ ] Recorrido manual con los 4 roles después del corte
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H1.7
DESCRIPCIÓN: Go si composer test pasa en MongoDB, verify-mongo da 0 diferencias y el recorrido manual
es correcto. En cualquier otro caso es no-go y se revierte.
DEPENDE DE: E1-T06, E1-T08, E1-T10, E1-T11, E1-T12, E1-T13
BLOQUEA A: —
ARCHIVOS: .env.example, config/session.php, config/cache.php, config/queue.php, docs/mongodb/03-corte-y-reversion.md
MONGODB: Reversión: DB_CONNECTION=sqlite y git revert del merge. Sin pérdida de datos solo antes de reabrir el tráfico.
VALIDACIÓN: Bitácora del ensayo con tiempos en el documento.
RIESGO: Medio.
```

---

### E2 · Seguridad: Control de Acceso y Autenticación (5 pts)

**H2.1** Como plataforma, quiero que el registro público solo cree oyentes y artistas y que el login resista ataques de fuerza bruta, para que nadie obtenga privilegios que no le corresponden.
**H2.2** Como responsable de la base de datos, quiero cuentas de MongoDB con el mínimo privilegio y ningún secreto en el repositorio, para limitar el daño si alguna credencial se filtra.

---

#### E2-T15 · Restringir los roles permitidos en el registro público

| Planner | Valor |
|---|---|
| Título | `[E2-T15] (1 pt) Restringir roles permitidos en el registro` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Urgente |
| Asignar a | Persona B |
| Fecha de inicio | 13/10/2026 |
| Fecha de vencimiento | 13/10/2026 |
| Etiquetas | `E2 Acceso/Auth` `Seguridad` `1 pt` |

**Lista de comprobación**
- [ ] Validación account_type => Rule::in(['user', 'artist'])
- [ ] Prueba: registrarse con account_type=admin devuelve un error de validación
- [ ] Revisar que register.blade.php solo ofrezca esas dos opciones
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H2.1
DESCRIPCIÓN: Corrige el hallazgo 1 (escalada de privilegios). Los labels los crea el admin
y los artistas de un label los crea el propio label.
DEPENDE DE: —
BLOQUEA A: —
ARCHIVOS: app/Http/Controllers/RegisterController.php, resources/views/auth/register.blade.php, tests/Feature/RegisterTest.php
VALIDACIÓN: composer test (filtro Register).
RIESGO: Bajo.
```

---

#### E2-T16 · Limitar los intentos de login y registro

| Planner | Valor |
|---|---|
| Título | `[E2-T16] (1 pt) Limitar intentos de login y registro` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Media |
| Asignar a | Persona B |
| Fecha de inicio | 13/10/2026 |
| Fecha de vencimiento | 13/10/2026 |
| Etiquetas | `E2 Acceso/Auth` `Seguridad` `1 pt` |

**Lista de comprobación**
- [ ] Middleware throttle:5,1 en POST /login y POST /register
- [ ] Prueba: el sexto intento en un minuto devuelve 429
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H2.1
DEPENDE DE: —
BLOQUEA A: —
ARCHIVOS: routes/web.php, tests/Feature/LoginTest.php
VALIDACIÓN: composer test (filtro Login).
RIESGO: Bajo. El limitador usa la caché, que sigue en SQLite.
```

---

#### E2-T17 · Crear usuarios de MongoDB con mínimo privilegio

| Planner | Valor |
|---|---|
| Título | `[E2-T17] (2 pts) Usuarios de MongoDB con mínimo privilegio` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 15/10/2026 |
| Fecha de vencimiento | 15/10/2026 |
| Etiquetas | `E2 Acceso/Auth` `Seguridad` `2 pts` |

**Lista de comprobación**
- [ ] Script de inicialización: melodic_app con readWrite sobre melodic
- [ ] melodic_readonly con read y melodic_backup con el rol backup
- [ ] Mecanismo SCRAM-SHA-256; contraseñas tomadas del entorno, no del repositorio
- [ ] La app se conecta con melodic_app y no con root
- [ ] Prueba: melodic_app no puede ejecutar dropDatabase en admin ni leer otra base
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H2.2
DEPENDE DE: E1-T04
BLOQUEA A: E6-T27
ARCHIVOS: docker/mongo/init/01-users.js, compose.yaml, .env.example
MONGODB: Usuarios en la base admin con roles por base. Reversión: db.dropUser() y volver a la URI anterior.
VALIDACIÓN: mongosh -u melodic_app → intentar una operación prohibida; debe dar "not authorized".
RIESGO: Bajo.
```

---

#### E2-T18 · Sacar los secretos de compose.yaml y rotar el APP_KEY

| Planner | Valor |
|---|---|
| Título | `[E2-T18] (1 pt) Sacar secretos de compose.yaml y rotar APP_KEY` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Urgente |
| Asignar a | Persona B |
| Fecha de inicio | 12/10/2026 |
| Fecha de vencimiento | 12/10/2026 |
| Etiquetas | `E2 Acceso/Auth` `Seguridad` `1 pt` |

**Lista de comprobación**
- [ ] compose.yaml lee APP_KEY, MONGO_* y MONGODB_URI desde .env (env_file o ${VAR})
- [ ] Generar un APP_KEY nuevo; el que se publicó en el repositorio se da por comprometido
- [ ] APP_DEBUG=false fuera del entorno local
- [ ] .env.example documenta las variables sin valores reales
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H2.2
DESCRIPCIÓN: Corrige el hallazgo 2. El APP_KEY y root/root quedan en el historial de git: rotarlos,
no basta con borrarlos del archivo.
DEPENDE DE: —
BLOQUEA A: E2-T17
ARCHIVOS: compose.yaml, .env.example
VALIDACIÓN: git grep -n "APP_KEY: base64" → sin resultados; docker compose config muestra variables resueltas.
RIESGO: Bajo.
```

---

### E3 · Cifrado en Tránsito de datos (5 pts)

**H3.1** Como responsable de la base de datos, quiero que el tráfico entre Laravel y MongoDB vaya cifrado con TLS, para que nadie en la red pueda leer credenciales ni datos.
**H3.2** Como usuario de Melodic, quiero navegar por HTTPS, para que mi sesión y mis datos viajen cifrados.

---

#### E3-T19 · Exigir TLS en las conexiones a MongoDB

| Planner | Valor |
|---|---|
| Título | `[E3-T19] (3 pts) TLS obligatorio en MongoDB` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 16/10/2026 |
| Fecha de vencimiento | 16/10/2026 |
| Etiquetas | `E3 Cifrado` `Seguridad` `3 pts` |

**Lista de comprobación**
- [ ] Script que genera la CA y el certificado del servidor (SAN = mongodb); no se suben al repo
- [ ] mongod con --tlsMode requireTLS y --tlsDisabledProtocols TLS1_0,TLS1_1
- [ ] URI de Laravel con tls=true y tlsCAFile; healthcheck con mongosh --tls
- [ ] Prueba: una conexión sin TLS es rechazada
- [ ] composer test pasa con la conexión TLS
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H3.1
DEPENDE DE: E1-T04
BLOQUEA A: E4-T24, E6-T27
ARCHIVOS: docker/mongo/tls/generate-certs.sh, compose.yaml, config/database.php, .gitignore, .env.example
MONGODB: Reversión: --tlsMode preferTLS y quitar tls=true de la URI.
VALIDACIÓN: mongosh "mongodb://...?tls=false" → falla; con --tls --tlsCAFile → conecta.
RIESGO: Medio. Certificados mal emitidos (SAN) rompen la conexión.
```

---

#### E3-T20 · Servir la app por HTTPS con un proxy inverso

| Planner | Valor |
|---|---|
| Título | `[E3-T20] (2 pts) HTTPS para la app con proxy inverso` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Media |
| Asignar a | Persona B |
| Fecha de inicio | 23/10/2026 |
| Fecha de vencimiento | 23/10/2026 |
| Etiquetas | `E3 Cifrado` `Infra` `2 pts` |

**Lista de comprobación**
- [ ] Servicio Caddy o Nginx en compose con TLS 1.2 y 1.3 y redirección de 80 a 443
- [ ] Cabecera HSTS
- [ ] SESSION_SECURE_COOKIE=true y APP_URL con https
- [ ] El puerto 8000 de la app deja de publicarse; solo el proxy lo alcanza
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H3.2
DESCRIPCIÓN: En local se usa un certificado de CA interna (por ejemplo, tls internal de Caddy).
DEPENDE DE: E4-T21
BLOQUEA A: —
ARCHIVOS: compose.yaml, docker/proxy/Caddyfile, .env.example
VALIDACIÓN: curl -I https://localhost muestra Strict-Transport-Security; openssl s_client -tls1_1 falla.
RIESGO: Bajo. Es Should: se recorta primero.
```

---

### E4 · Hardening de Red (VPN, Firewall, SSL, TLS) (7 pts)

**H4.1** Como responsable de infraestructura, quiero que MongoDB solo sea alcanzable desde la red interna, para reducir la superficie de ataque.
**H4.2** Como administrador, quiero acceder a la base de datos de forma remota solo por VPN, para no exponerla a internet.
**H4.3** Como auditor, quiero evidencia de que no hay protocolos TLS débiles ni puertos innecesarios abiertos, para confirmar que el endurecimiento funciona.

---

#### E4-T21 · Aislar MongoDB en una red interna de Docker

| Planner | Valor |
|---|---|
| Título | `[E4-T21] (1 pt) Aislar MongoDB en red interna de Docker` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 15/10/2026 |
| Fecha de vencimiento | 15/10/2026 |
| Etiquetas | `E4 Hardening` `Infra` `1 pt` |

**Lista de comprobación**
- [ ] Red backend con internal: true para app y mongodb
- [ ] Quitar ports 27017 del servicio mongodb (o publicarlo solo en 127.0.0.1)
- [ ] Prueba: desde el host, nc -z <ip-host> 27017 falla
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H4.1
DEPENDE DE: E1-T04
BLOQUEA A: E4-T22, E4-T23, E3-T20
ARCHIVOS: compose.yaml
VALIDACIÓN: docker compose ps no muestra 0.0.0.0:27017.
RIESGO: Bajo. Las herramientas locales (Compass) pasarán a conectarse por la VPN o por un túnel.
```

---

#### E4-T22 · Configurar el firewall del host

| Planner | Valor |
|---|---|
| Título | `[E4-T22] (2 pts) Firewall del host (nftables/ufw + DOCKER-USER)` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 19/10/2026 |
| Fecha de vencimiento | 19/10/2026 |
| Etiquetas | `E4 Hardening` `Seguridad` `2 pts` |

**Lista de comprobación**
- [ ] Política por defecto: denegar todo el tráfico entrante
- [ ] Permitir 80/443 TCP, 51820/UDP (WireGuard) y 22 solo desde la VPN
- [ ] Reglas en la cadena DOCKER-USER (Docker se salta ufw en los puertos publicados)
- [ ] Script versionado y documentado; las reglas persisten tras reiniciar
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H4.1
DEPENDE DE: E4-T21
BLOQUEA A: E4-T24
ARCHIVOS: docker/firewall/rules.nft (o ufw-setup.sh), docs/mongodb/comandos.md
VALIDACIÓN: nmap desde otra máquina solo muestra 80, 443 y 51820/udp.
RIESGO: Medio. Una regla mal escrita deja fuera el SSH: probar con consola local disponible.
```

---

#### E4-T23 · Dar acceso administrativo a MongoDB solo por VPN (WireGuard)

| Planner | Valor |
|---|---|
| Título | `[E4-T23] (3 pts) Acceso administrativo a MongoDB por VPN WireGuard` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Media |
| Asignar a | Persona B |
| Fecha de inicio | 20/10/2026 |
| Fecha de vencimiento | 20/10/2026 |
| Etiquetas | `E4 Hardening` `Infra` `3 pts` |

**Lista de comprobación**
- [ ] Servidor WireGuard (wg0, 10.8.0.0/24) en el host o en un contenedor
- [ ] Un peer por cada integrante, con claves generadas fuera del repositorio
- [ ] MongoDB publicado solo en la IP de wg0 (10.8.0.1:27017)
- [ ] Prueba: mongosh conecta con la VPN activa y falla sin ella
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H4.2
DEPENDE DE: E4-T21
BLOQUEA A: —
ARCHIVOS: docker/wireguard/wg0.conf.example, compose.yaml, docs/mongodb/comandos.md
VALIDACIÓN: wg show muestra el handshake; nc -z 10.8.0.1 27017 solo responde con la VPN activa.
RIESGO: Medio. Es Should: se recorta antes que las tareas Must.
```

---

#### E4-T24 · Verificar los protocolos TLS y los puertos expuestos

| Planner | Valor |
|---|---|
| Título | `[E4-T24] (1 pt) Verificar protocolos TLS y puertos expuestos` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 19/10/2026 |
| Fecha de vencimiento | 19/10/2026 |
| Etiquetas | `E4 Hardening` `Seguridad` `1 pt` |

**Lista de comprobación**
- [ ] nmap --script ssl-enum-ciphers contra MongoDB: solo TLS 1.2 y 1.3
- [ ] openssl s_client -tls1_1 contra MongoDB es rechazado
- [ ] Escaneo de puertos del host documentado (antes y después)
- [ ] Evidencia agregada a docs/mongodb/hardening.md
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H4.3
DEPENDE DE: E3-T19, E4-T22
BLOQUEA A: —
ARCHIVOS: docs/mongodb/hardening.md
VALIDACIÓN: Salidas de nmap y openssl pegadas en el documento.
RIESGO: Bajo.
```

---

### E5 · Consultas CRUD (5 pts)

**H5.1** Como integrante del equipo, quiero un catálogo de consultas CRUD en mongosh equivalentes a las de SQL, para validar el diseño y depurar sin pasar por la aplicación.

---

#### E5-T25 · Documentar el catálogo de consultas CRUD en mongosh

| Planner | Valor |
|---|---|
| Título | `[E5-T25] (3 pts) Catálogo de consultas CRUD en mongosh` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona A |
| Fecha de inicio | 16/10/2026 |
| Fecha de vencimiento | 16/10/2026 |
| Etiquetas | `E5 CRUD` `Docs` `3 pts` |

**Lista de comprobación**
- [ ] Create, read, update y delete para cada una de las 8 colecciones
- [ ] Cada consulta junto a su equivalente SQL actual
- [ ] Operaciones clave: $inc de reproducción, $push en playlist, findOneAndUpdate de retiro
- [ ] Agregaciones: ingresos por mes, total de reproducciones por label, saldo por artista
- [ ] Ejemplo de transacción con session.withTransaction()
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H5.1
DEPENDE DE: E1-T02, E1-T03
BLOQUEA A: E5-T26, E1-T11 (sirve de referencia)
ARCHIVOS: docs/mongodb/consultas-crud.md
VALIDACIÓN: Ejecutar cada consulta sobre los datos que cargó E1-T07.
RIESGO: Bajo.
```

---

#### E5-T26 · Validar con explain() que las consultas críticas usan índices

| Planner | Valor |
|---|---|
| Título | `[E5-T26] (2 pts) Validar con explain() el uso de índices` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 21/10/2026 |
| Fecha de vencimiento | 21/10/2026 |
| Etiquetas | `E5 CRUD` `Test` `2 pts` |

**Lista de comprobación**
- [ ] explain('executionStats') de cada consulta de la sección 1.3
- [ ] Todas muestran IXSCAN, salvo la regex de búsqueda (COLLSCAN justificado)
- [ ] totalDocsExamined / nReturned cerca de 1 en las consultas indexadas
- [ ] Si falta un índice, se agrega en una migración nueva
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H5.1
DESCRIPCIÓN: Cumple la regla de AGENTS.md: las consultas importantes deben tener un índice equivalente.
DEPENDE DE: E1-T05, E1-T07, E5-T25
BLOQUEA A: E1-T14
ARCHIVOS: docs/mongodb/consultas-crud.md (sección explain)
VALIDACIÓN: Planes de ejecución pegados en el documento.
RIESGO: Bajo.
```

---

### E6 · Comandos (3 pts)

**H6.1** Como integrante del equipo, quiero un runbook de comandos para levantar, operar, respaldar y restaurar MongoDB, para que cualquiera de los dos pueda hacerlo sin depender del otro.

---

#### E6-T27 · Escribir el runbook de comandos

| Planner | Valor |
|---|---|
| Título | `[E6-T27] (2 pts) Runbook de comandos MongoDB y artisan` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Importante |
| Asignar a | Persona B |
| Fecha de inicio | 23/10/2026 |
| Fecha de vencimiento | 23/10/2026 |
| Etiquetas | `E6 Comandos` `Docs` `2 pts` |

**Lista de comprobación**
- [ ] Entorno: docker compose up/down/logs y rs.initiate()/rs.status()
- [ ] Conexión: mongosh con TLS y usuario de mínimo privilegio, y por VPN
- [ ] Usuarios e índices: createUser, getIndexes, createIndex
- [ ] Artisan: migrate, melodic:migrate-to-mongo, melodic:verify-mongo, down/up
- [ ] Respaldo y restauración con mongodump/mongorestore (--gzip --archive)
- [ ] Firewall y VPN: comandos de verificación
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H6.1
DEPENDE DE: E1-T14, E2-T17, E3-T19
BLOQUEA A: —
ARCHIVOS: docs/mongodb/comandos.md
VALIDACIÓN: La persona que no escribió el runbook ejecuta cada comando desde cero.
RIESGO: Bajo.
```

---

#### E6-T28 · Probar un respaldo y una restauración reales

| Planner | Valor |
|---|---|
| Título | `[E6-T28] (1 pt) Probar respaldo y restauración reales` |
| Depósito | TO DO |
| Estado | No iniciado |
| Prioridad | Media |
| Asignar a | Persona B |
| Fecha de inicio | 21/10/2026 |
| Fecha de vencimiento | 21/10/2026 |
| Etiquetas | `E6 Comandos` `Infra` `1 pt` |

**Lista de comprobación**
- [ ] mongodump con el usuario melodic_backup a un archivo .gz
- [ ] mongorestore en una base temporal (--nsFrom/--nsTo)
- [ ] melodic:verify-mongo contra la base restaurada da 0 diferencias
- [ ] Anotar tiempos y tamaños en el runbook
- [ ] DoD cumplida

**Notas**
```text
HISTORIA: H6.1
DEPENDE DE: E1-T07, E2-T17
BLOQUEA A: —
ARCHIVOS: docs/mongodb/comandos.md
VALIDACIÓN: Conteos iguales entre la base original y la restaurada.
RIESGO: Bajo. Es Should.
```

---

## 6. Backlog fuera del sprint (depósito `Diseño BackLog`, prioridad Baja)

Estas tarjetas no tienen fechas. Se repriorizan en el siguiente planning.

#### C01 · Registrar cada reproducción en una colección de serie temporal

| Planner | Valor |
|---|---|
| Título | `[C01] (5 pts) Registrar reproducciones en colección time series plays` |
| Depósito | Diseño BackLog |
| Prioridad | Baja |
| Etiquetas | `E1 Migración` `Datos` `5 pts` |

**Lista de comprobación:** colección time series `{ts, meta:{song_id, user_id}}` · escribir en registerPlay · TTL o política de retención · DoD cumplida
**Notas:** Hoy la tabla `plays` nunca se escribe (hallazgo 7). Permitiría estadísticas por oyente y por fecha.

#### C02 · Mover sesiones, caché y colas fuera de SQLite

| Planner | Valor |
|---|---|
| Título | `[C02] (3 pts) Mover sesiones, caché y colas fuera de SQLite` |
| Depósito | Diseño BackLog |
| Prioridad | Baja |
| Etiquetas | `E1 Migración` `Infra` `3 pts` |

**Lista de comprobación:** evaluar los drivers de MongoDB frente a Redis · migrar sesiones · migrar caché · migrar colas y failed_jobs · retirar SQLite · DoD cumplida
**Notas:** Es el último paso para dejar de depender de SQLite.

#### C03 · Unificar earnings y expenses en un libro contable

| Planner | Valor |
|---|---|
| Título | `[C03] (5 pts) Unificar earnings y expenses en colección ledger` |
| Depósito | Diseño BackLog |
| Prioridad | Baja |
| Etiquetas | `E1 Migración` `Datos` `5 pts` |

**Lista de comprobación:** diseño `{type, amount, at, refs}` · migración de datos · adaptar el dashboard admin · DoD cumplida
**Notas:** Simplifica los reportes, pero cambia el modelo de dominio. Se dejó fuera del sprint para no mezclar una refactorización funcional con la migración (regla de AGENTS.md).

#### C04 · Corregir el orden del down() de la migración de canciones

| Planner | Valor |
|---|---|
| Título | `[C04] (1 pt) Corregir orden de down() en migración de songs` |
| Depósito | Diseño BackLog |
| Prioridad | Baja |
| Etiquetas | `E1 Migración` `Datos` `1 pt` |

**Lista de comprobación:** documentar el fallo · decidir con el equipo cómo corregirlo sin alterar la migración existente · DoD cumplida
**Notas:** Hallazgo 8. Solo afecta al `migrate:rollback` de SQL.

#### C05 · Búsqueda de texto completo

| Planner | Valor |
|---|---|
| Título | `[C05] (3 pts) Búsqueda de texto completo para canciones y artistas` |
| Depósito | Diseño BackLog |
| Prioridad | Baja |
| Etiquetas | `E5 CRUD` `Backend` `3 pts` |

**Lista de comprobación:** medir la latencia de la regex con el volumen real · evaluar un índice de texto frente a Atlas Search · implementar · DoD cumplida
**Notas:** Se activa cuando el catálogo crezca y la regex de E1-T13 sea lenta.
