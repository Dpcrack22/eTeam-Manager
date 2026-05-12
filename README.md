# eTeam Manager

eTeam Manager es una aplicacion web interna para organizar equipos competitivos de eSports. El proyecto centraliza en un solo sitio la gestion de rosters, scrims, calendario, tableros de tareas, notas estrategicas, notificaciones, perfiles y ajustes de usuario. La idea del producto es sustituir parte del trabajo disperso entre varias herramientas por un flujo unico, mas claro y mas facil de mantener.

La aplicacion esta construida con PHP, MySQL, CSS y JavaScript vanilla. La interfaz publica vive en `index.php` y el area privada se resuelve a traves de `app.php?view=...`, con un sistema de includes compartidos para cabecera, sidebar, header y footer.

## Que resuelve el proyecto

La propuesta de eTeam Manager es ayudar a equipos y organizaciones a trabajar con contexto. En lugar de manejar el dia a dia en varias plataformas separadas, la app intenta unificar:

- la estructura de organizaciones y equipos,
- el seguimiento de scrims y resultados,
- la planificacion de calendario,
- el trabajo interno mediante tableros Kanban,
- las notas tacticas y operativas,
- el perfil y la configuracion del usuario,
- y la busqueda rapida de usuarios y equipos.

Tambien incluye una portada publica para explicar el producto y una zona autenticada para operar con los datos del sistema.

## Funciones principales

### Landing publica
La portada de `index.php` presenta el producto, sus secciones principales y el acceso a login o registro. Tambien sirve como resumen comercial y explicacion de uso.

### Autenticacion
El sistema incluye login, registro, cierre de sesion y soporte de "recordarme". La autenticacion y la sesion se gestionan desde `includes/auth.php` y la persistencia se apoya en la base de datos.

### Dashboard
El dashboard muestra el contexto operativo principal: equipo activo, proximos eventos, scrims recientes y tareas pendientes.

### Equipos y organizaciones
La app trabaja con contexto de organizacion y equipo. Desde ahi se gestionan rosters, roles internos, invitaciones y acciones administrativas relacionadas con el equipo activo.

### Scrims
Permite registrar enfrentamientos competitivos con rival, fecha, resultado, score y mapas. Tambien sirve como historial de trabajo competitivo.

### Calendario
Incluye planificacion de eventos como entrenamientos, reuniones, scrims o torneos, con control de asistencia y contexto de equipo u organizacion.

### Tableros Kanban
El modulo de boards organiza tareas por columnas, prioridades, fechas limite y responsables.

### Notas
Las notas guardan informacion estrategica, analisis, ideas y documentacion interna con etiquetas y filtros.

### Buscador
Existe busqueda de usuarios y equipos con resultados en pagina y autocompletado en el frontend.

### Perfil y ajustes
El perfil publico y la zona de ajustes permiten mostrar informacion de usuario, editar datos y controlar la visibilidad del perfil.

### Notificaciones y administracion
El router contempla vistas de notificaciones, analitica y administracion para completar la operativa interna de la plataforma.

## Estructura del proyecto

```text
index.php                Portada publica
app.php                  Router principal del area autenticada
profile.php              Perfil publico de usuario
login.php / register.php Acceso publico
includes/                Includes compartidos, auth, DB y helpers
pages/                   Vistas del area interna
css/main.css             Sistema visual global
js/main.js               Interacciones globales y autocompletado
database/                SQL de creacion, usuario y datos de ejemplo
public/                  Recursos publicos
uploads/                 Ficheros subidos por la app
```

### Vistas principales en `pages/`

- `dashboard.php`
- `teams.php`
- `team-detail.php`
- `scrims.php`
- `scrim-form.php`
- `scrim-detail.php`
- `calendar.php`
- `event-form.php`
- `boards.php`
- `notes.php`
- `settings.php`
- `search.php`
- `notifications.php`
- `analytics.php`
- `admin.php`
- `login.php`
- `register.php`

## Requisitos

- PHP 8.1 o superior
- MySQL 8 o compatible
- Servidor web con soporte PHP o el servidor embebido de PHP
- Extensiones PDO y pdo_mysql habilitadas
- Navegador moderno

## Instalacion local

### 1. Clonar o abrir el proyecto
Abre la carpeta raiz del repositorio en tu entorno local.

### 2. Crear la base de datos
Importa los scripts de `database/` en este orden:

1. `database/01_create_database.sql`
2. `database/02_create_app_user.sql`
3. `database/03_seed_dev_valorant.sql` si quieres datos de desarrollo

### 3. Configurar variables de entorno
El conector de base de datos busca primero un archivo `.env` en la raiz y despues variables de entorno del sistema.

Variables esperadas:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`

Ejemplo de `.env`:

```ini
DB_HOST=localhost
DB_NAME=eteam_manager
DB_USER=eteam_app
DB_PASSWORD=tu_password
```

### 4. Arrancar la aplicacion
Si usas el servidor integrado de PHP, puedes lanzar el proyecto desde la raiz con un comando similar a:

```bash
php -S localhost:8000
```

Despues abre en el navegador:

- `http://localhost:8000/index.php`
- `http://localhost:8000/login.php`
- `http://localhost:8000/app.php?view=dashboard`

Si prefieres Apache, Laragon, XAMPP u otra pila local, solo necesitas apuntar el document root a la carpeta raiz del proyecto y asegurar que `mod_rewrite` y `pdo_mysql` esten disponibles.

## Flujo de uso

1. Entras por la portada publica.
2. Creas cuenta o inicias sesion.
3. Accedes al dashboard.
4. Seleccionas contexto de organizacion o equipo.
5. Gestionas scrims, calendario, tareas y notas desde el panel interno.
6. Usas el perfil y ajustes para modificar tus datos.

## Datos y persistencia

La app combina UI real con persistencia en MySQL. Parte de la experiencia visual puede apoyarse en contenido de desarrollo o semillas SQL, pero el flujo principal ya esta planteado para trabajar con datos relacionales reales.

La conexion a base de datos se resuelve desde `includes/db.php`, que intenta leer primero `.env` y despues variables de entorno. Si no puede conectar, la aplicacion detiene la carga con un mensaje de error generico.

## Diseno y sistema visual

La identidad visual usa un estilo oscuro y competitivo, con acento rojo y jerarquia clara para una lectura rapida. El sistema se apoya en:

- `css/main.css` como fuente central de estilos,
- `includes/head.php` para metadatos y carga global,
- tipografia Inter desde Google Fonts,
- cards, badges, estados y layouts compartidos.

## Estado actual del proyecto

El repositorio esta orientado a una app interna funcional, no a una landing estatica. La estructura ya separa la portada publica, el layout autenticado y las vistas por modulo. El proyecto sigue siendo ampliable, pero ya tiene una base suficiente para trabajar como plataforma operativa.

## Contacto

Email de contacto del proyecto: `eteammanager2@gmail.com`

## Notas finales

- El flujo principal del area interna se resuelve con `app.php` y vistas modulares.
- Los includes compartidos concentran autenticacion, layout y conexion a base de datos.
- Si vas a ampliar el proyecto, conviene mantener la logica por dominios y evitar mezclar vista, auth y acceso a datos en un mismo archivo.
