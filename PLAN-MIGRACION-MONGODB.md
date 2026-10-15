# Plan de trabajo para la migración a MongoDB

## 1. Objetivo y alcance

Migrar de forma gradual la persistencia de Melodic desde el esquema relacional actual hacia MongoDB, manteniendo la aplicación operativa durante la transición y comprobando la equivalencia funcional antes de retirar SQLite/MySQL.

El alcance inicial comprende usuarios y roles, suscripciones, catálogo musical, playlists, reproducciones, regalías, ingresos y retiros. Las tablas técnicas de Laravel (`cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs` y `password_reset_tokens`) se evaluarán por separado; no deben mezclarse automáticamente con las colecciones del dominio.

## 2. Situación actual que condiciona el trabajo

- Laravel 12 usa `sqlite` como conexión predeterminada en `config/database.php`; el README documenta MySQL. Debe fijarse primero cuál es el origen oficial de datos del entorno a migrar.
- No existe todavía una conexión MongoDB ni un adaptador/documentación de modelos MongoDB en `composer.json`.
- El identificador de `users` es UUID string; álbumes, canciones, playlists y movimientos financieros usan enteros autoincrementales.
- Las relaciones principales son: usuario–álbumes, álbum–canciones, usuario–playlists y playlist–canciones. Un usuario también puede pertenecer a un label mediante `users.label`.
- `SongController::registerPlay` incrementa `songs.plays` y acumula una regalía, pero no crea registros en `plays`, aunque existe la tabla y el modelo `Play`. Esta decisión debe cerrarse antes de validar equivalencia.
- El retiro actual escribe un gasto, un ingreso y actualiza el saldo de regalías dentro de una transacción; esa operación requiere conservar atomicidad en MongoDB.
- El modelo `Album` desactiva timestamps aunque la migración crea `created_at` y `updated_at`. También hay campos de migración que no están reflejados de forma uniforme en los modelos. Se debe levantar un inventario de datos real antes de transformar.

## 3. Fases y entregables

### Fase 0 — Línea base y decisiones pendientes

**Tareas**

1. Confirmar el motor y la base de datos relacional de origen, versión, volumen, crecimiento y ventana de mantenimiento.
2. Ejecutar la suite actual y documentar los flujos manuales: registro/login, compra de suscripción, subida de álbum/canción, reproducción, creación de playlist, búsqueda, dashboard de artista/label/admin y retiro.
3. Resolver la semántica de reproducciones: mantener solo el contador agregado, registrar también cada reproducción en `plays`, o usar ambos como agregado y auditoría.
4. Corregir o documentar las discrepancias de timestamps, campos faltantes y nombres heredados (`is_suscribed`, `fin_subscription`, `comision`, `from`) sin cambiar aún la persistencia.

**Entregables y criterio de salida**

- Inventario de tablas, columnas, tipos, nulabilidad, claves, cardinalidades y consultas.
- Catálogo de casos de uso con entradas, salidas y reglas de autorización.
- Línea base de pruebas y métricas de consultas críticas.
- Decisiones aprobadas sobre identificadores, reproducciones y fuente de verdad.

### Fase 1 — Diseño del modelo documental

Se propone iniciar con colecciones separadas y referencias explícitas. El embebido se limitará a datos pequeños y de lectura conjunta; no se embebirán canciones completas en álbumes ni playlists en usuarios porque pueden crecer y se actualizan de forma independiente.

| Origen relacional | Colección propuesta | Diseño inicial |
| --- | --- | --- |
| `users` | `users` | Documento de usuario con `_id` UUID string para conservar sesiones, referencias y compatibilidad. `subscription_id` y `label` como referencias; no guardar contraseñas en claro. |
| `subscriptions` | `subscriptions` | Plan independiente con `name`, `cost` como Decimal128, `months`, timestamps. En compras se conservará un snapshot del plan si se requiere historial inmutable. |
| `albums` + `songs` | `albums` y `songs` | Álbum referencia al artista; canción referencia al álbum. Rutas de archivos permanecen como strings administradas por Storage. Opcionalmente guardar en `albums` un resumen de canciones solo si una lectura lo justifica. |
| `playlists` + `playlist_song` | `playlists` | Playlist referencia al propietario y contiene `song_ids` o subdocumentos mínimos `{song_id, added_at}`. La opción se decidirá con el volumen máximo esperado; se evitará duplicar metadatos completos de canciones. |
| `plays` | `plays` | Eventos append-only con `song_id`, `user_id` nullable si aplica y `played_at`. Si se conserva el contador, `songs.plays` será un agregado derivado y reconciliable. |
| `royalties` | `royalties` | Un documento por destinatario con saldo Decimal128, `recipient_id`, `last_payment`, versión y timestamps. El saldo no se duplicará en `users`. |
| `earnings` y `expenses` | `financial_movements` | Movimientos inmutables con tipo (`earning`/`expense`), monto Decimal128, estado, fecha, referencias a usuario, suscripción o regalía y un `operation_id` idempotente. Separar colecciones solo si las consultas o retención lo exigen. |

**Índices iniciales a validar con `explain()`**

- `users`: único en `email`; `{role: 1, created_at: -1}`; `{label: 1}`; `{subscription_id: 1}`.
- `subscriptions`: único o controlado en `name` si el negocio lo exige.
- `albums`: `{user_id: 1, created_at: -1}`.
- `songs`: `{album_id: 1}`; `{name: 1}` y, si se mantiene búsqueda parcial, evaluar Atlas Search en lugar de un índice B-tree simple.
- `playlists`: `{user_id: 1, created_at: -1}` y multikey en `song_ids`.
- `plays`: `{song_id: 1, played_at: -1}` y `{user_id: 1, played_at: -1}`; considerar TTL solo si se define una política de retención.
- `royalties`: único en `recipient_id`.
- `financial_movements`: `{type: 1, at: -1}`, `{user_id: 1, at: -1}`, `{at: -1}` y los campos de referencia usados por los dashboards.

**Entregable:** documento de esquema JSON, diccionario de tipos y diagrama de referencias/embebidos con ejemplos reales anonimizados.

### Fase 2 — Compatibilidad de Laravel

1. Elegir y fijar el driver MongoDB compatible con Laravel 12 y PHP del proyecto; registrar la dependencia de forma deliberada.
2. Añadir una conexión `mongodb` sin cambiar todavía la conexión por defecto.
3. Crear repositorios o modelos adaptados detrás de interfaces de dominio para no acoplar controladores a consultas SQL o MongoDB.
4. Implementar serialización de UUID, fechas UTC, booleanos, nulos, enteros y `Decimal128`.
5. Definir cómo funcionarán autenticación, sesiones, cache y colas durante la convivencia. Mantener esos servicios en el backend actual hasta tener un plan independiente.

**Criterio de salida:** la aplicación puede leer una colección de prueba y la conexión relacional sigue funcionando sin cambios de comportamiento.

### Fase 3 — Migración inicial y sincronización

1. Congelar el inventario y tomar un respaldo verificable de la base relacional.
2. Cargar primero planes y usuarios; después álbumes, canciones, playlists, reproducciones y movimientos financieros respetando referencias.
3. Usar transformaciones idempotentes, lotes reanudables y un registro de errores por documento.
4. Durante la transición, sincronizar escrituras mediante outbox/eventos o una estrategia equivalente. No depender de una doble escritura silenciosa desde cada controlador.
5. Ejecutar reconciliaciones por conteo, identificador, montos, saldos, fechas, relaciones playlist–canción y agregados de reproducciones.

**Criterio de salida:** cada lote puede repetirse sin duplicar datos y las diferencias están explicadas o corregidas.

### Fase 4 — Migración progresiva por casos de uso

Activar lectura por dominio con una bandera de configuración y fallback controlado a la fuente relacional:

1. **Catálogo:** álbumes, canciones, portada y exploración.
2. **Playlists:** listado, detalle, búsqueda y adición de canciones; validar duplicados e idempotencia.
3. **Identidad y suscripciones:** login, roles, vigencia y compra; conservar la unicidad del correo.
4. **Reproducciones y regalías:** diseñar la operación atómica para contador/saldo y registrar `operation_id`.
5. **Finanzas:** retiro transaccional con creación de movimientos, actualización de saldo y prevención de doble procesamiento.
6. **Dashboards:** totales, últimos retiros y agregación mensual; reemplazar `DATE_FORMAT` por agregaciones `$group`/rangos de fecha.

Cada paso requiere pruebas de equivalencia, monitoreo de errores y un periodo de observación antes de avanzar.

### Fase 5 — Corte y retiro de la dependencia relacional

1. Definir una ventana de corte, respaldo final y procedimiento probado de reversión.
2. Detener o poner en modo mantenimiento las escrituras, completar la cola de sincronización y comparar nuevamente conteos, saldos y relaciones.
3. Cambiar la bandera de escritura/lectura a MongoDB, verificar autenticación y casos críticos, y mantener el acceso relacional en solo lectura durante el periodo acordado.
4. Retirar adaptadores y migraciones de compatibilidad solo después de cumplir los criterios de estabilidad y contar con un respaldo recuperable.

## 4. Reglas de consistencia y reversión

- Los montos monetarios se almacenarán como `Decimal128`; no se usarán flotantes.
- Las fechas se normalizarán a UTC y se documentará la conversión de `date` frente a `timestamp`.
- Las operaciones de reproducción y retiro tendrán un identificador idempotente para reintentos.
- El retiro usará una transacción MongoDB cuando las colecciones estén en un replica set; si el despliegue no lo permite, se diseñará un estado intermedio y un reconciliador explícito.
- Las escrituras duales registrarán divergencias y no ocultarán errores de la segunda fuente.
- La reversión consiste en detener nuevas escrituras MongoDB, completar o compensar eventos pendientes, restaurar el tráfico a la fuente relacional y comparar los movimientos generados desde el último punto común. No se borrarán colecciones durante el periodo de observación.

## 5. Validación y métricas de aceptación

- `composer test` debe pasar antes y después de cada corte de dominio; se añadirán pruebas funcionales para los flujos que hoy no están cubiertos.
- Se compararán respuestas de endpoints/vistas equivalentes, conteos por colección, relaciones, totales financieros, saldos, vigencia de suscripciones y número de reproducciones.
- Las consultas críticas se validarán con `explain()` y límites de latencia acordados; se comprobarán índices y crecimiento de documentos.
- Se probarán reintentos, duplicados, concurrencia en reproducciones/retiros, documentos huérfanos, valores nulos, UTF-8 y recuperación desde respaldo.
- Se registrarán errores de sincronización, retraso de replicación, divergencias, latencia p95 y tasa de fallback.

## 6. Riesgos principales y mitigaciones

| Riesgo | Mitigación |
| --- | --- |
| Doble escritura divergente | Outbox/eventos, idempotencia, reconciliación y fallback medido. |
| Saldo de regalías incorrecto por concurrencia | Operaciones atómicas, transacciones en replica set y pruebas de carga. |
| Documentos demasiado grandes por playlists o reproducciones | Referencias y eventos separados; límites y métricas de tamaño. |
| Búsqueda parcial lenta | Índices evaluados con `explain()` y Atlas Search si el volumen lo justifica. |
| Pérdida de historial financiero | Movimientos append-only, respaldo, `operation_id` y reglas de inmutabilidad. |
| Incompatibilidad del driver con Laravel/auth | Spike técnico aislado antes de cambiar modelos o conexión predeterminada. |
| Datos heredados inconsistentes | Perfilado, reporte de anomalías y reglas de transformación aprobadas antes de cargar. |

## 7. Orden inmediato de ejecución

1. Crear el inventario relacional y la matriz de casos de uso.
2. Confirmar SQLite/MySQL como origen real y preparar un respaldo de prueba.
3. Resolver la semántica de `plays`, timestamps y campos financieros heredados.
4. Medir volúmenes y consultas reales de dashboards, catálogo y playlists.
5. Elaborar el esquema JSON, índices y estrategia de identificadores.
6. Hacer un spike de conexión MongoDB con una colección de prueba.
7. Implementar migración idempotente de un subconjunto y pruebas de reconciliación.

No se debe cambiar `DB_CONNECTION` a MongoDB ni eliminar migraciones relacionales antes de completar estas salidas y aprobar el procedimiento de reversión.
