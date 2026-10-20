# Despliegue en la Nube (Render y MongoDB Atlas)

Esta guía detalla los pasos para desplegar Melodic en producción utilizando **MongoDB Atlas** para la base de datos de documentos y **Render** para la aplicación web monolítica (Laravel + Blade).

---

## 1. MongoDB Atlas

### 1.1 Configurar el clúster
1. Crear una cuenta en [MongoDB Atlas](https://www.mongodb.com/cloud/atlas).
2. Crear un clúster gratuito (**M0 Free Tier**).
3. En la sección **Security > Database Access**:
   - Crear un usuario (por ejemplo `melodic_app`) con rol **Read and write to any database** o asignado específicamente a la base `melodic`.
   - Guardar la contraseña generada.
4. En la sección **Security > Network Access**:
   - Agregar una regla de IP: `0.0.0.0/0` (Allow Access from Anywhere) para permitir la conexión desde los servidores dinámicos de Render.

### 1.2 Obtener la cadena de conexión
1. En Atlas, hacer clic en **Connect** en tu clúster.
2. Seleccionar **Drivers** (PHP).
3. Copiar la cadena de conexión estándar SRV:
   ```text
   mongodb+srv://melodic_app:<password>@<cluster-url>.mongodb.net/melodic?retryWrites=true&w=majority
   ```
*(Nota: En Atlas no se requiere configurar certificados CA ni flags locales como `tlsCAFile`).*

---

## 2. Despliegue en Render

El repositorio incluye un archivo [`render.yaml`](file:///home/tproxy/Documentos/Semestre%209/Base%20de%20Datos%20NoSQL/Melodic-NoSQL/render.yaml) para configurar automáticamente el servicio web.

### 2.1 Crear el Web Service vía Blueprint (Automático)
1. Conectar tu repositorio de GitHub en [Render Dashboard](https://dashboard.render.com).
2. Ir a **Blueprints** > **New Blueprint Instance**.
3. Seleccionar el repositorio y la rama (`feat/deploy-render-atlas` o `dev` una vez fusionada).
4. Render leerá `render.yaml` y solicitará únicamente:
   - `MONGODB_URI`: Pegar la cadena obtenida en Atlas (con usuario y contraseña).
   - `APP_URL`: La URL pública que Render asigne a tu servicio (ej. `https://melodic-app.onrender.com`).

### 2.2 Configuración manual alternativa (Docker Web Service)
Si prefieres crear el servicio como **Web Service** estándar:
- **Environment**: Docker
- **Region**: Oregon (o la más cercana a tu clúster de Atlas)
- **Branch**: `feat/deploy-render-atlas` o `dev`
- **Plan**: Free
- **Variables de Entorno**:
  - `APP_NAME`: `Melodic`
  - `APP_ENV`: `production`
  - `APP_DEBUG`: `false`
  - `APP_KEY`: Clave base64 generada con `php artisan key:generate --show`.
  - `APP_URL`: `https://<tu-app>.onrender.com`
  - `APP_LOCALE`: `es`
  - `DB_CONNECTION`: `sqlite`
  - `DB_DATABASE`: `/var/lib/melodic/database.sqlite`
  - `SESSION_DRIVER`: `database`
  - `SESSION_CONNECTION`: `sqlite`
  - `SESSION_SECURE_COOKIE`: `true`
  - `CACHE_STORE`: `database`
  - `DB_CACHE_CONNECTION`: `sqlite`
  - `QUEUE_CONNECTION`: `sync`
  - `MONGODB_URI`: Tu URI de Atlas (`mongodb+srv://...`).
  - `MONGODB_DATABASE`: `melodic`

*(El contenedor inicializa automáticamente la base SQLite local en `/var/lib/melodic/database.sqlite` para sesiones y caché, compatible al 100% con el plan Free de Render sin necesidad de aprovisionar discos ni servicios SQL adicionales).*

---

## 3. Inicialización y Población de Datos en Atlas

Al iniciar el contenedor, el script de arranque ejecutará automáticamente:
1. `php artisan migrate --force` (tablas de sesiones y caché en SQLite).
2. `php artisan migrate --path=database/migrations/mongodb --force` (colecciones e índices en MongoDB Atlas).

### Para sembrar los datos iniciales (Seeders)
Desde la pestaña **Shell** de tu servicio en Render (o localmente apuntando a Atlas en `.env`):
```bash
php artisan db:seed --force
```
Esto creará los usuarios y suscripciones base:
* Administrador: `admin@melodic.com` / `admin123`
* Oyente: `user@melodic.com` / `password`
* Artista: `artist@melodic.com` / `password`
* Sello discográfico: `label@melodic.com` / `password`

### Para migrar datos existentes desde una base MySQL previa
Si dispones de datos en una base relacional y deseas subirlos a Atlas:
```bash
php artisan melodic:migrate-to-mongo
```
Verifica la migración con:
```bash
php artisan melodic:verify-mongo
```
