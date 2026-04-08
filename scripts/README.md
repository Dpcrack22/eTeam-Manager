# Scripts de servidor (Ubuntu)

## 1) Setup completo (Apache + PHP + MySQL + deploy + vhost)

Desde la raíz del repo:

```bash
sudo chmod +x scripts/*.sh
sudo SERVER_NAME=eteam.example.com MYSQL_APP_PASSWORD='CAMBIA_ESTA_PASSWORD' SEED_DEV=1 ./scripts/01_setup_ubuntu_server.sh
```

Notas:
- Si no tienes DNS para `SERVER_NAME`, usa la IP del servidor en el navegador.
- `SEED_DEV=1` importa datos de demo (solo desarrollo).

## 2) Permisos/seguridad (filesystem + hardening Apache)

```bash
sudo DEPLOY_DIR=/var/www/eteam-manager ./scripts/02_secure_permissions.sh
```

Variables útiles:
- `ADMIN_USER`: tu usuario SSH para añadirlo al grupo y poder leer/escribir donde toque.
- `WRITABLE_DIRS`: lista de carpetas que quieras que pueda escribir Apache (si existen).
- `uploads/avatars` se prepara automáticamente para que los avatares se puedan guardar sin errores de permisos.

## 3) MySQL / phpMyAdmin

El usuario de aplicación `eteam_app` ahora puede tener permisos de esquema sobre `eteam_manager` si necesitas importar desde phpMyAdmin sin errores de acceso.

Ejemplo de permisos amplios para importación inicial:

```bash
sudo MYSQL_APP_PASSWORD='CAMBIA_ESTA_PASSWORD' \
	MYSQL_APP_PRIVILEGES='SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES,TRIGGER' \
	./scripts/01_setup_ubuntu_server.sh
```

Nota:
- `database/01_create_database.sql` ya no crea la base de datos; solo crea las tablas dentro de `eteam_manager`.
- Si usas phpMyAdmin, selecciona la base de datos `eteam_manager` antes de importar el archivo.
