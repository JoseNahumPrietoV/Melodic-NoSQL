# Melodic 🎵

> 🚨 **Importante antes de comenzar**
>
> Este proyecto se trabaja mediante **forks**. Lo que debes clonar en tu computadora **NO es el repositorio original**, sino **TU fork personal**.
>
> 👉 Primero haz un **Fork** del repositorio en GitHub y después clona **ese fork** en local.
>
> Si clonas el repositorio original, **no podrás subir tus cambios**.

Este proyecto es una aplicación **monolítica en Laravel**, donde el **backend y el frontend** están construidos con Laravel y **Blade**. Está pensada como una guía práctica para aprender a trabajar con Laravel en un entorno real.

---

## 📖 Manual principal

> **[`docs/Manual_Melodic.pdf`](docs/Manual_Melodic.pdf) es el manual global y principal de Melodic.** Reúne en un solo documento el sistema, la migración a MongoDB, la arquitectura, la seguridad, el despliegue, los comandos y el uso de la plataforma. Empieza por aquí.

| Documento | Contenido |
| --------- | --------- |
| [`docs/Manual_Melodic.pdf`](docs/Manual_Melodic.pdf) | **Manual global y principal** |
| [`docs/Manual_Melodic.docx`](docs/Manual_Melodic.docx) | Versión editable del manual principal |
| [`docs/manual-usuario.md`](docs/manual-usuario.md) | Manual de usuario: cuentas, instalación y recorrido guiado |
| [`docs/manual.md`](docs/manual.md) | Manual de comandos: Docker, HTTPS, firewall, VPN y MongoDB |

---

## 📌 Requisitos

Antes de comenzar, asegúrate de tener instalado lo siguiente:

* **PHP 8.3+**
* **Composer v2.7.1+** (administrador de dependencias de PHP)
* **Node.js v24.11.1+** y **NPM v11.6.2+** (para dependencias frontend)
* **Git**
* Un gestor de base de datos compatible con **MySQL**

> 💡 **Nota:** No es necesario tener Laravel instalado de forma global. Composer se encargará de todo.

---

## 📥 Clonar el repositorio (tu fork)

> 💡 **Usuarios de Windows:**
> Abre **Git Bash** (no CMD ni PowerShell) para ejecutar todos los comandos que aparecen en esta guía.

### Opción 1: Desde la consola (Git)

1. Abre una terminal
2. Ve a la carpeta donde quieras guardar el proyecto
3. Ejecuta:

```bash
git clone <URL_DEL_REPOSITORIO>
cd melodic
```

---

### Opción 2: Desde Visual Studio Code

1. Abre **Visual Studio Code**
2. Presiona `Ctrl + Shift + P`
3. Escribe **Git: Clone** y selecciónalo
4. Pega la URL del repositorio
5. Elige una carpeta donde guardarlo
6. Cuando termine, abre el proyecto

---

## 📦 Instalar dependencias del proyecto

> ℹ️ **Tip:** Si este comando falla, revisa que Composer esté correctamente instalado y agregado al PATH.

Ya dentro de la carpeta del proyecto, ejecuta:

```bash
composer install
```

Este comando descargará Laravel y todas las dependencias necesarias del backend.

---

## ⚙️ Configuración del entorno (.env)

> 🧠 **Qué es esto:** El archivo `.env` guarda configuraciones sensibles como contraseñas y nunca se sube a GitHub.

Laravel usa un archivo `.env` para la configuración del proyecto.

1. Copia el archivo de ejemplo:

```bash
cp .env.example .env
```

2. Abre el archivo `.env`
3. Configura los valores de la base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=melodic
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

> ⚠️ Asegúrate de que la base de datos exista antes de continuar.

---

## 🔑 Generar la clave de la aplicación

> 🔐 **Tip:** Sin esta clave, Laravel no funcionará correctamente y mostrará errores.

Laravel necesita una clave única para funcionar correctamente.

Ejecuta:

```bash
php artisan key:generate
```

Esto actualizará automáticamente el valor de `APP_KEY` en tu archivo `.env`.

---

## 🗄️ Base de datos

> ⚠️ **Advertencia:** Asegúrate de que la base de datos exista antes de ejecutar las migraciones.

Una vez configurada la conexión:

1. Ejecuta las migraciones:

```bash
php artisan migrate
```

Esto creará las tablas necesarias en la base de datos.

2. Carga los datos iniciales:

```bash
php artisan db:seed
```

Esto insertará usuarios y datos de prueba.

---

## 🎨 Dependencias frontend

Aunque el frontend está hecho con Blade, el proyecto utiliza dependencias de Node.

Ejecuta:

```bash
npm install
```

---

## ▶️ Ejecutar el proyecto

> 🚀 **Dato:** Este comando levanta un servidor de desarrollo. No se usa en producción.

Para iniciar el servidor de desarrollo de Laravel:

```bash
php artisan serve
```

Luego abre tu navegador y visita:

```
http://localhost:8000
```

### Entorno local con Docker

El entorno local se puede levantar con Docker Compose. Este arranque crea el proxy HTTPS (Caddy), la aplicación, MongoDB (datos de la aplicación) y MySQL (sesiones, caché y colas). La versión en línea corre en Render con MongoDB Atlas: https://melodic-app.onrender.com (ver `docs/despliegue-render-atlas.md`).

Compose toma los secretos del `.env`: antes del primer arranque llena `APP_KEY`, `MONGO_ROOT_USERNAME`, `MONGO_ROOT_PASSWORD`, `MONGO_APP_PASSWORD`, `MONGO_READONLY_PASSWORD`, `MONGO_BACKUP_PASSWORD`, `MYSQL_PASSWORD` y `MYSQL_ROOT_PASSWORD` (sin estas variables no arranca). Genera las contraseñas con `openssl rand -hex 24`.

MongoDB corre como replica set de un nodo (`rs0`), solo acepta conexiones TLS y necesita un keyFile y certificados locales, que no se versionan. Genéralos y cópialos al volumen de secretos una sola vez antes del primer arranque; después crea las cuentas de prueba:

```bash
./docker/mongo/generate-keyfile.sh
./docker/mongo/tls/generate-certs.sh
./docker/mongo/sync-secrets.sh
docker compose up --build --detach
docker exec melodic-app php artisan db:seed --force
```

Después abre [https://localhost](https://localhost): la app se sirve por HTTPS a través de Caddy, con un certificado de su CA interna (ver `docs/manual.md` para confiar en él). MySQL queda disponible solo en `127.0.0.1:3306`. MongoDB está en una red interna y no publica puertos; para usarlo desde el host en desarrollo, levanta el stack con `docker compose -f compose.yaml -f compose.localhost.yaml up --detach`, que lo publica solo en `127.0.0.1:27018`. Para detenerlos sin borrar los datos persistidos:

```bash
docker compose down
```

---

## 👤 Usuarios de prueba

Puedes iniciar sesión con los siguientes usuarios:

### Usuario

* **Nombre:** Jane Doe
* **Correo:** [user@melodic.com](mailto:user@melodic.com)
* **Contraseña:** password

### Artista independiente

* **Nombre:** John Doe
* **Correo:** [artist@melodic.com](mailto:artist@melodic.com)
* **Contraseña:** password

### Label

* **Nombre:** Melodic Records
* **Correo:** [label@melodic.com](mailto:label@melodic.com)
* **Contraseña:** password

### Administrador

* **Nombre:** Administrador
* **Correo:** [admin@melodic.com](mailto:admin@melodic.com)
* **Contraseña:** admin123

---

## 🔀 Flujo de trabajo: Fork y Pull Request

> 🧩 **Regla de oro:** Nunca trabajes directamente sobre `main`.

Este proyecto se trabaja mediante **forks y pull requests**.

### 1️⃣ Crear un fork

1. Ve al repositorio original en GitHub
2. Haz clic en **Fork** (arriba a la derecha)
3. Esto creará una copia del proyecto en tu cuenta

---

### 2️⃣ Clonar tu fork

```bash
git clone <URL_DE_TU_FORK>
cd melodic
```

---

### 3️⃣ Crear una rama para tu práctica

```bash
git checkout -b feature/nombre-de-tu-cambio
```

Trabaja siempre en ramas, no directamente en `main`.

---

### 4️⃣ Subir tus cambios

```bash
git add .
git commit -m "Descripción clara de los cambios"
git push origin feature/nombre-de-tu-cambio
```

---

### 5️⃣ Crear el Pull Request

1. Ve a tu fork en GitHub
2. Aparecerá un botón para crear el **Pull Request**
3. Asegúrate de que:

    * Base repository: repositorio original
    * Compare: tu rama
4. Describe qué hiciste y qué aprendiste

---

## 🧭 Comandos básicos de Linux usados con Git

| Comando        | Qué hace                        |
| -------------- | ------------------------------- |
| `ls`           | Muestra los archivos y carpetas |
| `cd carpeta`   | Entra a una carpeta             |
| `cd ..`        | Regresa una carpeta atrás       |
| `pwd`          | Muestra la ruta actual          |
| `clear`        | Limpia la terminal              |
| `mkdir nombre` | Crea una carpeta                |

> 💡 **Nota para Windows:** Git Bash usa estos comandos aunque estés en Windows.

---

## 📚 Notas finales

* Este proyecto está pensado para **aprender**, no para correr en producción
* Si algo falla, revisa primero el archivo `.env`
* No tengas miedo de romper cosas: así se aprende Laravel
