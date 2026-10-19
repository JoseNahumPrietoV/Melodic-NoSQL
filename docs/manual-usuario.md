# Melodic · Manual de usuario

Este manual explica qué es Melodic, cómo funciona y cómo probarlo en tu equipo. No necesitas saber programar para seguirlo. Al final hay enlaces a la documentación técnica.

## 1. ¿Qué es Melodic?

Melodic es una plataforma de música en línea, parecida a un servicio de streaming:

- Los **oyentes** pagan una suscripción y escuchan música, buscan canciones y arman sus playlists.
- Los **artistas** suben sus álbumes y canciones.
- Los **sellos discográficos** (labels) administran a varios artistas y cobran por ellos.
- El **administrador** ve cuánto dinero entra y sale de la plataforma y da de alta a los sellos.

Cada vez que alguien escucha una canción, el artista (o su sello, si tiene uno) gana **0.15** en regalías. Cuando el artista o el sello retira su dinero, recibe el **70 %** y la plataforma se queda con el **30 %** como comisión.

## 2. Los cuatro tipos de cuenta

| Cuenta | Qué puede hacer | Cómo se crea |
|---|---|---|
| Oyente | Suscribirse, escuchar, explorar, crear playlists | Se registra solo en la página |
| Artista independiente | Subir álbumes y canciones, ver su saldo y retirarlo | Se registra solo en la página |
| Sello (label) | Crear artistas de su sello, ver las reproducciones de sus artistas, retirar el saldo | Lo crea el administrador |
| Administrador | Ver ingresos, egresos y la gráfica mensual; crear sellos | Viene creado de inicio |

Un artista que pertenece a un sello no cobra directamente: sus regalías van al sello.

## 3. Cómo se mueve el dinero

1. Un oyente paga su plan (mensual o anual). Ese pago es un **ingreso** de la plataforma.
2. Cada reproducción suma 0.15 al **saldo** del artista o de su sello.
3. Cuando el artista o el sello retira su saldo, se registra un **pago** del 70 % y un **ingreso** del 30 % por comisión, y su saldo vuelve a cero.

El sistema hace cada uno de estos pasos de una sola vez: si mucha gente escucha la misma canción al mismo tiempo, no se pierde ninguna reproducción, y un retiro no puede cobrar dos veces el mismo dinero.

## 4. Qué hay detrás

Melodic es una aplicación web. Lo que ves en el navegador lo genera un servidor, y toda la información (cuentas, canciones, playlists, dinero) se guarda en una base de datos.

Este proyecto **cambió esa base de datos**: antes usaba una base relacional (MySQL, organizada en tablas) y ahora usa **MongoDB**, que guarda la información como "documentos", parecidos a fichas. Por ejemplo, una playlist es una sola ficha que ya contiene la lista de sus canciones, en lugar de repartirse en varias tablas.

Además se protegió el sistema:

- La página se sirve por **HTTPS** (conexión cifrada, con candado en el navegador).
- La base de datos no está expuesta: solo la aplicación puede hablar con ella, siempre cifrado, y con un usuario que solo tiene los permisos que necesita.
- Los administradores pueden entrar a la base de datos únicamente por una **VPN** (un túnel privado).
- Hay un **firewall** que deja pasar solo lo necesario.

## 5. Probar Melodic en tu equipo

Necesitas **Docker** y **Git**. Los comandos se escriben en una terminal, dentro de la carpeta del proyecto.

### 5.1 Descargar el proyecto

```bash
git clone https://github.com/Theproxy206/Melodic-NoSQL.git
cd Melodic-NoSQL
```

### 5.2 Preparar las contraseñas

El proyecto no trae contraseñas: cada quien genera las suyas. Estos comandos crean el archivo de configuración `.env` y lo llenan con contraseñas aleatorias:

```bash
cp .env.example .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
sed -i "s|^MONGO_ROOT_USERNAME=.*|MONGO_ROOT_USERNAME=root|" .env
for v in MONGO_ROOT_PASSWORD MONGO_APP_PASSWORD MONGO_READONLY_PASSWORD MONGO_BACKUP_PASSWORD MYSQL_PASSWORD MYSQL_ROOT_PASSWORD; do
  sed -i "s|^$v=.*|$v=$(openssl rand -hex 24)|" .env
done
```

Después se generan las llaves de la base de datos (solo la primera vez):

```bash
./docker/mongo/generate-keyfile.sh
./docker/mongo/tls/generate-certs.sh
```

### 5.3 Encender el sistema

```bash
docker compose up --build --detach
```

La primera vez tarda unos minutos porque descarga y prepara todo. Para ver que los servicios están arriba:

```bash
docker compose ps
```

Deben aparecer `melodic-proxy`, `melodic-app`, `melodic-mongodb` y `melodic-mysql`.

### 5.4 Crear las colecciones y los datos de ejemplo

```bash
docker exec melodic-app php artisan migrate --path=database/migrations/mongodb --force
docker exec melodic-app php artisan db:seed --force
```

Esto crea las cuatro cuentas de prueba y los planes de suscripción.

### 5.5 Abrir la página

Entra a **https://localhost**. El navegador avisará que el certificado no es de confianza: es normal, porque es un certificado creado en tu propio equipo. Acepta continuar (o confía en él siguiendo la sección "HTTPS" de `docs/manual.md`).

## 6. Recorrido guiado

Cuentas de prueba:

| Cuenta | Correo | Contraseña |
|---|---|---|
| Oyente | `user@melodic.com` | `password` |
| Artista | `artist@melodic.com` | `password` |
| Sello | `label@melodic.com` | `password` |
| Administrador | `admin@melodic.com` | `admin123` |

### Como artista

1. Entra con `artist@melodic.com`. Llegas a tu panel.
2. Crea un álbum y sube una canción (un archivo de audio y, si quieres, una portada).
3. En el panel verás tus álbumes con el número de canciones y tu saldo.

### Como oyente

1. Entra con `user@melodic.com`. Como aún no tiene suscripción, el sistema te lleva a la página **Premium**.
2. Elige un plan. Ya puedes usar la plataforma.
3. En **Explorar** busca por nombre de canción o de artista y dale reproducir. Cada reproducción le paga al artista.
4. En **Mis playlists** crea una playlist, ábrela y busca canciones para añadirlas. Si intentas añadir la misma canción dos veces, el sistema te avisa.

### Como sello

1. Entra con `label@melodic.com`.
2. Crea un artista de tu sello. Cuando sus canciones se escuchen, el saldo se acumula en tu cuenta.
3. Retira tu saldo: verás el pago en tus últimos retiros y el saldo volverá a cero.

### Como administrador

1. Entra con `admin@melodic.com`.
2. Verás el total de ingresos y egresos, la ganancia neta, la proyección y una gráfica con los ingresos de los últimos seis meses.
3. Desde ahí puedes crear un sello nuevo.

## 7. Comprobar que todo funciona

Estos comandos son opcionales, para quien quiera ver "por dentro" que la información está bien.

Ver cuántas canciones y usuarios hay en la base de datos:

```bash
docker exec melodic-mongodb sh -c 'mongosh --tls --tlsCAFile /run/secrets/mongodb_tls_ca --quiet -u melodic_readonly -p "$MONGO_READONLY_PASSWORD" --authenticationDatabase admin melodic --eval "print(\"usuarios:\", db.users.countDocuments(), \"canciones:\", db.songs.countDocuments())"'
```

Comprobar que la base de datos rechaza conexiones sin cifrar (debe fallar):

```bash
docker exec melodic-mongodb sh -c 'mongosh --quiet "mongodb://127.0.0.1:27017/?tls=false&serverSelectionTimeoutMS=3000" --eval "1"'
```

Ver que el sitio responde por HTTPS y que `http://` redirige:

```bash
curl -sI -k https://localhost | head -1
curl -sI http://localhost | head -2
```

## 8. Apagar el sistema

```bash
docker compose down
```

La información se conserva para la próxima vez. Para volver a encenderlo basta con `docker compose up --detach`.

## 9. Problemas comunes

| Qué pasa | Qué hacer |
|---|---|
| `docker compose up` dice que falta una variable (`... is required`) | Falta una contraseña en `.env`. Repite el paso 5.2 |
| El navegador muestra "conexión no segura" | Es el certificado local. Acepta continuar o confía en él (paso 5.5) |
| Al iniciar sesión aparece "demasiados intentos" | Por seguridad solo se permiten 5 intentos por minuto. Espera un minuto |
| Después de actualizar el proyecto la página se ve igual o da error | Ejecuta `docker exec melodic-app php artisan view:clear` |
| El oyente siempre termina en la página Premium | No tiene suscripción activa: elige un plan |

## 10. Para saber más

- `docs/manual.md`: instalación y configuración paso a paso.
- `docs/mongodb/comandos.md`: comandos de operación (respaldos, usuarios, firewall, VPN).
- `docs/informe-migracion-nosql.md`: informe técnico de la migración a MongoDB.
