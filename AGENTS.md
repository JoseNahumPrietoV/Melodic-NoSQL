# AGENTS.md

## Contexto del proyecto

Melodic es una aplicación monolítica construida con Laravel 12, PHP y Blade. El frontend usa Vite, Tailwind CSS y JavaScript. La base de datos actual está modelada con migraciones relacionales de Laravel y el objetivo final del proyecto es migrarla a MongoDB.

## Objetivo técnico

- Mantener la aplicación funcionando durante la transición.
- Entender y documentar primero el modelo relacional existente antes de cambiarlo.
- Diseñar colecciones, documentos, referencias, embebidos e índices de MongoDB a partir de los casos de uso reales.
- Evitar una migración mecánica de tablas a colecciones: cada decisión debe considerar los patrones de lectura y escritura.
- Conservar la compatibilidad con el comportamiento actual hasta que una etapa de migración sea validada.

## Estructura relevante

- `app/Models/`: modelos Eloquent y reglas de dominio.
- `app/Http/Controllers/`: flujo de las peticiones y casos de uso de la aplicación.
- `database/migrations/`: esquema relacional vigente y fuente inicial para el inventario de datos.
- `database/seeders/`: datos iniciales y de prueba.
- `resources/views/`: vistas Blade.
- `routes/`: rutas HTTP y de consola.
- `tests/`: pruebas unitarias y funcionales.
- `config/database.php`: conexiones de base de datos.

## Reglas para cambios

- Revisar modelos, controladores, migraciones, seeders y vistas relacionadas antes de modificar un flujo.
- Mantener los cambios enfocados y respetar las convenciones de Laravel existentes.
- No agregar credenciales, archivos `.env`, bases de datos locales, artefactos de compilación ni dependencias generadas al repositorio.
- No eliminar ni alterar migraciones existentes para resolver problemas de datos sin justificarlo; usar una migración nueva cuando corresponda.
- No reemplazar la conexión actual por MongoDB hasta que exista un plan de compatibilidad, migración de datos y validación.
- Para decisiones de MongoDB, documentar la colección afectada, la forma del documento, las relaciones, los índices, la estrategia de identificadores y el plan de reversión.
- Validar que las consultas y filtros importantes tengan índices equivalentes en MongoDB.
- Tratar la consistencia, duplicación, operaciones atómicas y transacciones como decisiones explícitas, no como supuestos heredados del modelo SQL.

## Flujo de desarrollo

Instalar dependencias y preparar el entorno:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Ejecutar la aplicación en desarrollo:

```bash
composer run dev
```

Ejecutar pruebas:

```bash
composer test
```

Construir los recursos frontend:

```bash
npm run build
```

Antes de ejecutar migraciones o seeders, comprobar la conexión definida en `.env`. No ejecutar comandos destructivos sobre una base de datos compartida.

## Validación de cambios

- Para cambios de backend, ejecutar `composer test` y las pruebas específicas afectadas.
- Para cambios de vistas o JavaScript, ejecutar `npm run build`.
- Para cambios de esquema o persistencia, verificar migraciones, seeders, consultas, índices y casos de lectura/escritura relacionados.
- Si una validación no puede ejecutarse por falta de servicios o dependencias, dejar constancia de la limitación y del comando omitido.

## Migración a MongoDB

Cada etapa de la migración debe dejar registro de:

1. tablas y relaciones de origen;
2. colecciones y forma de los documentos de destino;
3. decisiones de embebido o referencia;
4. índices y consultas que justifican esos índices;
5. transformación de tipos, identificadores, fechas y valores nulos;
6. estrategia de carga inicial y sincronización durante la transición;
7. pruebas de equivalencia y criterios para avanzar o revertir.

Preferir cambios pequeños y verificables. No mezclar en un mismo cambio una refactorización funcional amplia con la migración de persistencia salvo que sea necesario para mantener el comportamiento.
