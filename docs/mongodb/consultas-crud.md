# Catálogo de consultas CRUD en mongosh

Consultas sobre el diseño de `02-diseno.md`, cada una con su equivalente SQL actual. Las referencias entre documentos son el string del `ObjectId`, por eso se usa `.toString()` al escribirlas y `ObjectId(...)` al buscar por `_id`. Se ejecutan en orden sobre los datos que carga `melodic:migrate-to-mongo` (ver `docs/manual.md`).

## Variables de ejemplo

```js
const now = new Date();
const plan = db.subscriptions.findOne();
const artist = db.users.findOne({ role: "artist", label: null, _id: { $in: db.albums.distinct("user_id") } });
const label = db.users.findOne({ role: "label" });
const listener = db.users.findOne({ role: "user", subscription_id: { $ne: null } });
const album = db.albums.findOne({ user_id: artist._id });
const song = db.songs.findOne({ album_id: album._id.toString() });
const playlist = db.playlists.findOne({ user_id: listener._id });
```

## subscriptions

```js
// Crear · SQL: INSERT INTO subscriptions (name, cost, months) VALUES ('Anual', 999.00, 12)
const annual = db.subscriptions.insertOne({ name: "Anual", cost: NumberDecimal("999.00"), months: 12, created_at: now, updated_at: now }).insertedId;
// Leer · SQL: SELECT * FROM subscriptions
db.subscriptions.find();
// Actualizar · SQL: UPDATE subscriptions SET cost = 899.00 WHERE id = ?
db.subscriptions.updateOne({ _id: annual }, { $set: { cost: NumberDecimal("899.00"), updated_at: now } });
// Borrar · SQL: DELETE FROM subscriptions WHERE id = ?
db.subscriptions.deleteOne({ _id: annual });
```

## users

```js
// Crear · SQL: INSERT INTO users (user_id, username, email, password, role) VALUES (?, ?, ?, ?, 'artist')
db.users.insertOne({ _id: "11111111-2222-4333-8444-555555555555", username: "Nuevo artista", email: "nuevo.artista@melodic.test", password: "$2y$12$...", role: "artist", is_suscribed: true, fin_subscription: null, subscription_id: null, label: label._id, path: null, created_at: now, updated_at: now });
// Leer (login) · SQL: SELECT * FROM users WHERE email = ? LIMIT 1
db.users.findOne({ email: "nuevo.artista@melodic.test" });
// Leer (artistas de un label) · SQL: SELECT * FROM users WHERE label = ?
db.users.find({ label: label._id });
// Leer (últimos 10 labels) · SQL: SELECT * FROM users WHERE role = 'label' ORDER BY created_at DESC LIMIT 10
db.users.find({ role: "label" }).sort({ created_at: -1 }).limit(10);
// Actualizar (suscripción) · SQL: UPDATE users SET is_suscribed = 1, subscription_id = ?, fin_subscription = ? WHERE user_id = ?
db.users.updateOne({ _id: listener._id }, { $set: { is_suscribed: true, subscription_id: plan._id.toString(), fin_subscription: ISODate("2027-01-19T00:00:00Z"), updated_at: now } });
// Borrar · SQL: DELETE FROM users WHERE email = ?
db.users.deleteOne({ email: "nuevo.artista@melodic.test" });
```

## albums

```js
// Crear · SQL: INSERT INTO albums (title, path, user_id) VALUES ('Demo', NULL, ?)
const demo = db.albums.insertOne({ title: "Demo", path: null, user_id: artist._id, created_at: null, updated_at: null }).insertedId;
// Leer (álbumes del artista) · SQL: SELECT * FROM albums WHERE user_id = ?
db.albums.find({ user_id: artist._id });
// Actualizar · SQL: UPDATE albums SET title = ? WHERE album_id = ?
db.albums.updateOne({ _id: demo }, { $set: { title: "Demo 2026" } });
// Borrar · SQL: DELETE FROM albums WHERE album_id = ?
db.albums.deleteOne({ _id: demo });
```

## songs

```js
// Crear (con snapshot) · SQL: INSERT INTO songs (name, album_id, file_path, plays) VALUES (?, ?, ?, 0)
const track = db.songs.insertOne({ name: "Pista nueva", length: 0, plays: NumberLong("0"), file_path: "songs/audio/pista.mp3", image_path: null, album_id: album._id.toString(), artist: { user_id: artist._id, username: artist.username }, created_at: now, updated_at: now }).insertedId;
// Leer (canciones del álbum) · SQL: SELECT * FROM songs WHERE album_id = ?
db.songs.find({ album_id: album._id.toString() });
// Leer (explorar) · SQL: SELECT songs.* FROM songs JOIN albums ... JOIN users ... WHERE songs.name LIKE '%lu%' OR users.username LIKE '%lu%'
db.songs.find({ $or: [{ name: /lu/i }, { "artist.username": /lu/i }] });
// Leer (explorar sin búsqueda) · SQL: SELECT * FROM songs ORDER BY created_at DESC
db.songs.find().sort({ created_at: -1 });
// Leer (6 al azar) · SQL: SELECT * FROM songs ORDER BY RANDOM() LIMIT 6
db.songs.aggregate([{ $sample: { size: 6 } }]);
// Actualizar · SQL: UPDATE songs SET name = ? WHERE song_id = ?
db.songs.updateOne({ _id: track }, { $set: { name: "Pista nueva (remix)", updated_at: now } });
// Borrar · SQL: DELETE FROM songs WHERE song_id = ?
db.songs.deleteOne({ _id: track });
```

## playlists

```js
// Crear · SQL: INSERT INTO playlists (name, user_id) VALUES ('Para estudiar', ?)
const study = db.playlists.insertOne({ name: "Para estudiar", description: null, path: null, user_id: listener._id, songs: [], created_at: now, updated_at: now }).insertedId;
// Leer (playlists del usuario con conteo) · SQL: SELECT playlists.*, COUNT(playlist_song.song_id) FROM playlists LEFT JOIN playlist_song ... WHERE user_id = ? GROUP BY ... ORDER BY created_at DESC
db.playlists.aggregate([{ $match: { user_id: listener._id } }, { $sort: { created_at: -1 } }, { $addFields: { songs_count: { $size: "$songs" } } }]);
// Leer (canciones de la playlist) · SQL: SELECT songs.* FROM songs JOIN playlist_song ON ... WHERE playlist_id = ?
db.songs.find({ _id: { $in: db.playlists.findOne({ _id: playlist._id }).songs.map(entry => ObjectId(entry.song_id)) } });
// Actualizar · SQL: UPDATE playlists SET name = ? WHERE playlist_id = ?
db.playlists.updateOne({ _id: study }, { $set: { name: "Para estudiar de noche", updated_at: now } });
// Borrar · SQL: DELETE FROM playlists WHERE playlist_id = ? (playlist_song se borra en cascada)
db.playlists.deleteOne({ _id: study });
```

## royalties

```js
// Crear · SQL: INSERT INTO royalties (recipient_id, amount) VALUES (?, 0)
db.royalties.insertOne({ recipient_id: "00000000-0000-0000-0000-000000000000", amount: NumberDecimal("0.00"), last_payment: null, created_at: now, updated_at: now });
// Leer (saldo) · SQL: SELECT * FROM royalties WHERE recipient_id = ? LIMIT 1
db.royalties.findOne({ recipient_id: artist._id });
// Actualizar · SQL: UPDATE royalties SET amount = 10.00 WHERE recipient_id = ?
db.royalties.updateOne({ recipient_id: "00000000-0000-0000-0000-000000000000" }, { $set: { amount: NumberDecimal("10.00"), updated_at: now } });
// Borrar · SQL: DELETE FROM royalties WHERE recipient_id = ?
db.royalties.deleteOne({ recipient_id: "00000000-0000-0000-0000-000000000000" });
```

## earnings

```js
// Crear (pago de suscripción) · SQL: INSERT INTO earnings (amount, state, at, subscription) VALUES (?, 'pagado', NOW(), ?)
const payment = db.earnings.insertOne({ amount: plan.cost, state: "pagado", at: now, subscription: plan._id.toString(), comision: null, created_at: now, updated_at: now }).insertedId;
// Leer (últimos 30 días) · SQL: SELECT SUM(amount) FROM earnings WHERE at >= NOW() - INTERVAL 30 DAY
db.earnings.aggregate([{ $match: { at: { $gte: new Date(now - 30 * 24 * 3600 * 1000) } } }, { $group: { _id: null, total: { $sum: "$amount" } } }]);
// Actualizar · SQL: UPDATE earnings SET state = 'reembolsado' WHERE earning_id = ?
db.earnings.updateOne({ _id: payment }, { $set: { state: "reembolsado", updated_at: now } });
// Borrar · SQL: DELETE FROM earnings WHERE earning_id = ?
db.earnings.deleteOne({ _id: payment });
```

## expenses

```js
// Crear · SQL: INSERT INTO expenses (amount, state, at, user_id) VALUES (?, 'pagado', NOW(), ?)
const payout = db.expenses.insertOne({ amount: NumberDecimal("7.00"), state: "pagado", at: now, from: null, user_id: artist._id, created_at: now, updated_at: now }).insertedId;
// Leer (últimos 5 retiros) · SQL: SELECT * FROM expenses WHERE user_id = ? ORDER BY at DESC LIMIT 5
db.expenses.find({ user_id: artist._id }).sort({ at: -1 }).limit(5);
// Actualizar · SQL: UPDATE expenses SET state = 'pendiente' WHERE expense_id = ?
db.expenses.updateOne({ _id: payout }, { $set: { state: "pendiente", updated_at: now } });
// Borrar · SQL: DELETE FROM expenses WHERE expense_id = ?
db.expenses.deleteOne({ _id: payout });
```

## Operaciones clave

### Reproducción con `$inc`

SQL actual: `UPDATE songs SET plays = plays + 1` y después leer, sumar y guardar la regalía (pierde incrementos concurrentes, hallazgo 3).

```js
db.songs.updateOne({ _id: song._id }, { $inc: { plays: 1 } });
db.royalties.updateOne(
  { recipient_id: artist.label ?? artist._id },
  { $inc: { amount: NumberDecimal("0.15") }, $setOnInsert: { last_payment: null, created_at: now }, $set: { updated_at: now } },
  { upsert: true }
);
```

### Añadir a una playlist con `$push`

SQL actual: `SELECT` en `playlist_song` para ver si existe y después `INSERT`.

```js
db.playlists.updateOne(
  { _id: playlist._id, user_id: listener._id, "songs.song_id": { $ne: song._id.toString() }, "songs.499": { $exists: false } },
  { $push: { songs: { song_id: song._id.toString(), added_at: now } }, $set: { updated_at: now } }
);
```

### Retiro con `findOneAndUpdate`

SQL actual: leer el saldo y después ponerlo en 0 (borra las regalías que llegan entre la lectura y la escritura, hallazgo 3). Aquí se lee y se vacía en un solo paso, y con el saldo anterior se registran el 70 % y el 30 %.

```js
const before = db.royalties.findOneAndUpdate(
  { recipient_id: artist._id, amount: { $gt: NumberDecimal("0") } },
  { $set: { amount: NumberDecimal("0.00"), last_payment: now, updated_at: now } },
  { returnDocument: "before" }
);
if (before) {
  const total = Number(before.amount.toString());
  db.expenses.insertOne({ amount: NumberDecimal((total * 0.70).toFixed(2)), state: "pagado", at: now, from: before._id.toString(), user_id: artist._id, created_at: now, updated_at: now });
  db.earnings.insertOne({ amount: NumberDecimal((total * 0.30).toFixed(2)), state: "pagado", at: now, subscription: null, comision: before._id.toString(), created_at: now, updated_at: now });
}
```

## Agregaciones

```js
// Ingresos por mes de los últimos 6 meses · SQL: SELECT DATE_FORMAT(at, '%Y-%m') AS month, SUM(amount) FROM earnings WHERE at >= ? GROUP BY month ORDER BY month
db.earnings.aggregate([
  { $match: { at: { $gte: new Date(new Date().setMonth(new Date().getMonth() - 6)) } } },
  { $group: { _id: { $dateToString: { format: "%Y-%m", date: "$at" } }, sum: { $sum: "$amount" } } },
  { $sort: { _id: 1 } }
]);

// Reproducciones totales por label · SQL: SELECT users.label, SUM(songs.plays) FROM songs JOIN albums ... JOIN users ... WHERE users.label IS NOT NULL GROUP BY users.label
db.users.aggregate([
  { $match: { role: "artist", label: { $ne: null } } },
  { $lookup: { from: "songs", localField: "_id", foreignField: "artist.user_id", as: "songs" } },
  { $group: { _id: "$label", plays: { $sum: { $sum: "$songs.plays" } } } }
]);

// Saldo por artista o label · SQL: SELECT users.username, users.role, royalties.amount FROM royalties JOIN users ON users.user_id = royalties.recipient_id
db.royalties.aggregate([
  { $lookup: { from: "users", localField: "recipient_id", foreignField: "_id", as: "recipient" } },
  { $unwind: "$recipient" },
  { $project: { _id: 0, username: "$recipient.username", role: "$recipient.role", amount: 1 } }
]);
```

## Transacción

La reproducción como una sola unidad: si falla la regalía, no se cuenta la reproducción. Requiere el replica set `rs0`.

```js
const session = db.getMongo().startSession();
session.withTransaction(() => {
  const tx = session.getDatabase(db.getName());
  tx.songs.updateOne({ _id: song._id }, { $inc: { plays: 1 } });
  tx.royalties.updateOne(
    { recipient_id: artist.label ?? artist._id },
    { $inc: { amount: NumberDecimal("0.15") }, $set: { updated_at: new Date() } },
    { upsert: true }
  );
});
session.endSession();
```
