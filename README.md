# Inventario — Registro de Harina (regharina)

Aplicación web para registrar **inventarios de cualquier producto**: por cada producto se indica
cuántas unidades hay (sacos, cajas, bolsas…), cuánto hay suelto ("abierto") y el sistema calcula el
total en la unidad base, con historial, usuarios con roles/permisos, exportación a Excel y alertas
(Telegram/Slack) cuando un inventario supera al anterior.

La instalación actual se usa para **harina** (Harina Extra y Harina Especial, en sacos de 50 kg),
pero los productos y las unidades de medida son datos de la BD: se pueden agregar otros productos
sin cambiar el código (ver [Productos y unidades de medida](#productos-y-unidades-de-medida)).

- **URL:** https://regharina.stardata.pe (HTTPS con Let's Encrypt, renovación automática)
- **Carpeta:** `/var/www/projects/regharina`
- **Repositorio:** https://github.com/gersonponce/Inventario (push con la deploy key `/root/.ssh/inventario_deploy`, alias SSH `github-inventario`)
- **Servidor:** Ubuntu 24.04 (~1 GB RAM), compartido con el proyecto
  `reportes` (`/var/www/reportes`, nginx sitio `avp` en el puerto 8123, también usa php8.4-fpm)

Documentación de la API para integraciones: **[API.md](API.md)**.

### Instalar desde el repositorio
```bash
composer install                       # crea vendor/
cp .env.example .env                   # completar credenciales (chmod 640, dueño www-data)
curl -sLo bin/tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/download/v4.1.11/tailwindcss-linux-x64
chmod +x bin/tailwindcss && ./build.sh # solo si se cambian clases CSS (app.css compilado ya está en el repo)
mysql regharina < schema.sql           # BD nueva: luego aplicar sql/*.sql en orden de fecha
```
No se suben al repositorio: `.env`, `vendor/` ni `bin/tailwindcss` (ver `.gitignore`).

---

## Stack

| Capa | Tecnología |
|---|---|
| Frontend | HTML estático (`public/index.html`) + **Alpine.js 3.14.9** (servido localmente) + **Tailwind CSS v4.1.11** compilado |
| API | **PHP 8.4** + **Slim 4** (`slim/slim`, `slim/psr7`) vía Composer |
| Base de datos | **MySQL 8.0** local, BD `regharina` |
| Servidor web | **nginx 1.24** + php8.4-fpm |

Particularidades del servidor:
- PHP 8.4 **no tiene `mbstring`** → no se usa `mb_*`; existe `largo()` en `src/config.php` para longitud UTF-8.
  Por eso tampoco se usa PhpSpreadsheet: el Excel se genera con `src/Support/Xlsx.php` (propio, usa `zip`).
- Tailwind se compila con el binario standalone `bin/tailwindcss` (no requiere Node).
- Se instaló `php8.4-mysql` (actualizó PHP 8.4.16 → 8.4.26).

---

## Estructura

```
regharina/
├── public/                     ← docroot de nginx
│   ├── index.html              ← toda la interfaz (Alpine)
│   ├── api/index.php           ← punto de entrada único de la API (nginx envía /api/* aquí)
│   └── assets/
│       ├── app.js              ← lógica Alpine: módulos, permisos, llamadas a la API
│       ├── app.css             ← CSS compilado (NO editar a mano)
│       └── alpine.min.js
├── resources/app.css           ← fuente de Tailwind (componentes .input, .btn-primary, .card, .tabla…)
├── src/
│   ├── app.php                 ← arma Slim: middleware + manejador de errores JSON
│   ├── routes.php              ← todas las rutas y sus permisos
│   ├── config.php              ← env(), fecha_es(), largo(); zona horaria America/Lima
│   ├── db.php                  ← db(): PDO a MySQL (127.0.0.1, usuario regharina)
│   ├── Controllers/            ← Sesion, Catalogo, Inventario, Usuario, Rol
│   ├── Middleware/             ← Sesion, Csrf, Auth, Permiso
│   ├── Http/                   ← ApiError (error con código HTTP), Json (respuesta)
│   └── Support/                ← Sesion, Acceso (usuario+permisos), Comparador, Notificador, Xlsx
├── sql/                        ← migraciones (aplicar en orden de fecha, como root)
├── schema.sql                  ← esquema base (instalación nueva: este + migraciones de sql/)
├── bin/
│   ├── tailwindcss             ← binario Tailwind
│   └── probar-notificaciones.php
├── build.sh                    ← compila Tailwind
├── composer.json / vendor/
└── .env                        ← credenciales (chmod 640, www-data). NUNCA publicar
```

---

## Configuración (`.env`)

| Clave | Uso |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexión MySQL (usuario `regharina@127.0.0.1`) |
| `APP_URL` | URL que se incluye en las alertas |
| `TELEGRAM_BOT_TOKEN` | Token del bot **@RicosPanBot** (ya configurado) |
| `TELEGRAM_CHAT_ID` | Id del grupo que recibe las alertas (en grupos es negativo). Configurado en producción |
| `SLACK_WEBHOOK_URL` | **Pendiente** — Incoming Webhook de Slack |

Un canal de alertas se activa solo si su configuración está completa. Vacío = desactivado.

---

## Base de datos (`regharina`)

| Tabla | Contenido |
|---|---|
| `unidades_medida` | `Kilogramo (kg, factor 1)` = unidad base; `Saco 50 kg (factor 50, base kg)` |
| `productos` | `Harina Extra`, `Harina Especial` (unidad base kg) |
| `inventarios` | Cabecera: `fecha_hora`, `nota`, `creado_por`, `deleted_at`/`eliminado_por` (**soft delete**) |
| `inventario_detalle` | Por producto: `cantidad` = **total en kg** y `detalles` (JSON con el desglose) |
| `usuarios` | `usuario` (único, sin distinguir mayúsculas), `nombre`, `password_hash` (bcrypt), `rol_id`, `activo`, `ultimo_acceso` |
| `roles` | `Administrador` (`es_sistema=1`: todos los permisos, no editable) y `Operador` |
| `permisos` / `rol_permiso` | Catálogo de permisos y su asignación a roles |
| `alertas_inventario` | Productos de un inventario que superaron al anterior (anterior, nuevo, diferencia) |
| `notificaciones` | Registro de cada envío a Telegram/Slack (`ok`, respuesta) para diagnosticar |

Ejemplo de `inventario_detalle.detalles`:
```json
{"unidad_id": 2, "unidad": "Saco 50 kg", "abreviatura": "saco", "factor": 50.0,
 "cantidad": 83.0, "cantidad_base": 4150.0, "abierto": 16.32, "unidad_base": "kg"}
```
`cantidad` (columna) = `cantidad_base + abierto` = `cantidad × factor + abierto`. El factor se copia
al guardar, así el histórico no cambia si se modifica una unidad.

**Permisos del usuario MySQL `regharina`:** `SELECT, INSERT, UPDATE` en toda la BD y `DELETE` solo en
`roles` y `rol_permiso`. La app **no puede borrar** inventarios ni usuarios (se marcan / desactivan).
Para tareas administrativas: `mysql regharina` como root (auth_socket).

**Zona horaria:** las fechas de la app se guardan en hora de Lima (`America/Lima`, sesión MySQL `-05:00`).
Las columnas `created_at`/`updated_at` (TIMESTAMP) se ven en UTC desde la consola de MySQL.

---

## Productos y unidades de medida

El sistema no está atado a la harina:

- **`unidades_medida`**: cada unidad tiene un `factor` respecto a su **unidad base**
  (`unidad_base_id`). La unidad base tiene `unidad_base_id = NULL` y factor 1.
- **`productos`**: cada producto tiene su unidad base. En el formulario se ofrecen la unidad base y
  todas las unidades derivadas de ella.
- **Total = cantidad × factor + abierto**, siempre expresado en la unidad base del producto.
- Las alertas comparan cada producto con el inventario anterior, sin importar cuál sea.

Aún no hay pantalla para administrarlos; se agregan por SQL (`mysql regharina` como root):

```sql
-- Otro producto medido en kg, con una presentación nueva
INSERT INTO unidades_medida (nombre, abreviatura, unidad_base_id, factor) VALUES ('Bolsa 25 kg', 'bolsa', 1, 25);
INSERT INTO productos (nombre, unidad_base_id) VALUES ('Azúcar', 1);

-- Producto con otra unidad base (ej. litros)
INSERT INTO unidades_medida (nombre, abreviatura, unidad_base_id, factor) VALUES ('Litro', 'L', NULL, 1);
SET @l = LAST_INSERT_ID();
INSERT INTO unidades_medida (nombre, abreviatura, unidad_base_id, factor) VALUES ('Bidón 20 L', 'bidón', @l, 20);
INSERT INTO productos (nombre, unidad_base_id) VALUES ('Aceite', @l);
```

> **Limitación actual:** algunos textos todavía asumen harina en kg: el título "Registro de Harina",
> las columnas "(kg)" del formulario, historial y modal de alerta, el texto "kg" del aviso de
> Telegram/Slack y el nombre del Excel (`inventario-harina-…`). Con productos en kg funciona sin
> cambios; con otras unidades base los cálculos son correctos, pero esas etiquetas dirán "kg".
> En la API, los campos `*_kg` (`aumento_kg`, `anterior_kg`…) están en la unidad base del producto.

---

## Funcionalidades

### Inventario
- Formulario: por producto se elige la **unidad** (Saco 50 kg por defecto, o kg), la **cantidad** y
  el **abierto (kg)**; los totales se calculan en vivo y se **recalculan en el servidor**.
- Historial con el formato original: Productos · Cantidad · Peso total · Abierto · Total kg ·
  Hora del Inv. (fecha en español) · "Registrado por".
- **Eliminar** = soft delete → aparece en el módulo **Eliminados**, desde donde se puede **Restaurar**.

### Alerta "inventario mayor al anterior"
- Antes de guardar, `POST /api/inventarios/verificar` compara cada producto con el **inventario activo
  anterior por fecha** (los eliminados no cuentan; si dos tienen la misma hora, el registrado antes).
- Si algún producto subió (aunque sea 0.001 kg) se muestra un modal con anterior/nuevo/diferencia:
  **Revisar** (no guarda) o **Guardar de todos modos**.
- Al guardar, el servidor repite la comparación, guarda en `alertas_inventario` y envía el aviso a
  Telegram/Slack **después de responder** (`fastcgi_finish_request`), con timeout corto; si falla, el
  inventario queda guardado y el error se registra en `notificaciones`.
- En el historial, el total que subió muestra **▲ +X kg** en ámbar.

### Exportar a Excel
- Botón **Exportar Excel** en el historial (permiso `inventario.exportar`, solo Administrador).
  Si no hay registros muestra "No hay registros para exportar.".
- `.xlsx` con una fila por producto: fecha (fecha real de Excel), producto, cantidad, unidad, peso
  total, abierto, total, nota, registrado por. Encabezado fijo con filtros.

### Usuarios, roles y permisos
- Login por **usuario + contraseña**. Cada usuario tiene un rol; el menú y los botones se muestran
  según sus permisos, y la API aplica las mismas reglas.
- Los cambios (rol, permisos, desactivación) aplican **en la siguiente acción** del usuario
  (el usuario se recarga en cada petición).
- Reglas: nadie puede desactivarse ni cambiarse su propio rol; solo un Administrador puede asignar
  el rol Administrador o editar a otro administrador; un rol con usuarios no se puede eliminar;
  los usuarios no se borran, se desactivan. "Cambiar contraseña" en el pie del menú.
- ⚠️ `roles.gestionar` es casi nivel administrador (quien lo tenga puede ampliar su propio rol).

| Permiso | Nombre |
|---|---|
| `inventario.ver` | Ver historial de inventarios |
| `inventario.registrar` | Registrar inventarios |
| `inventario.eliminar` | Eliminar inventarios |
| `inventario.restaurar` | Ver y restaurar eliminados |
| `inventario.exportar` | Exportar a Excel |
| `usuarios.gestionar` | Gestionar usuarios |
| `roles.gestionar` | Gestionar roles y permisos |

Estado actual de roles: **Administrador** = todos; **Operador** = ver, registrar y eliminar
(eliminar fue agregado desde la app).


---

## API (prefijo `/api`, JSON)

Seguridad: sesión PHP (cookie `regharina`, HttpOnly, SameSite=Strict); toda escritura exige la
cabecera `X-CSRF-Token` (se obtiene de `GET /api/sesion`). Errores: `{"error": "..."}` con su código
HTTP (400 validación, 401 sesión, 403 permiso/token, 404, 405, 409 duplicado, 500).

| Método y ruta | Permiso | Descripción |
|---|---|---|
| `GET /sesion` | — | `{auth, usuario, csrf}` |
| `POST /sesion` | — | Login `{usuario, password}` |
| `DELETE /sesion` | — | Logout |
| `PUT /sesion/password` | sesión | `{actual, nueva}` |
| `GET /catalogo` | `inventario.registrar` | Productos y sus unidades |
| `GET /inventarios` | `inventario.ver` | Activos (máx. 200) |
| `POST /inventarios` | `inventario.registrar` | Crea; responde `{id, alertas, canales}` |
| `POST /inventarios/verificar` | `inventario.registrar` | Compara con el anterior sin guardar |
| `GET /inventarios/exportar` | `inventario.exportar` | Descarga `.xlsx` |
| `GET /inventarios/eliminados` | `inventario.restaurar` | Eliminados |
| `DELETE /inventarios/{id}` | `inventario.eliminar` | Soft delete |
| `POST /inventarios/{id}/restaurar` | `inventario.restaurar` | Restaura |
| `GET /usuarios` · `POST /usuarios` · `PUT /usuarios/{id}` | `usuarios.gestionar` | Gestión de usuarios |
| `GET /roles` | `roles.gestionar` o `usuarios.gestionar` | Roles + catálogo de permisos |
| `POST /roles` · `PUT /roles/{id}` · `DELETE /roles/{id}` | `roles.gestionar` | Gestión de roles |

---

## nginx

Archivo `/etc/nginx/sites-available/regharina` (enlazado en `sites-enabled`):
- `root /var/www/projects/regharina/public`, `index index.html`, `Cache-Control: no-cache`.
- `location /api/ { try_files $uri /api/index.php$is_args$args; }` → Slim.
- PHP por `unix:/run/php/php8.4-fpm.sock`; archivos ocultos (`.env`) → 403.
- Bloques 443/redirect 80→443 gestionados por Certbot.

Tras cambiar la config: `nginx -t && systemctl reload nginx`.

---

## Tareas comunes

```bash
cd /var/www/projects/regharina

./build.sh                                   # recompilar CSS tras cambiar clases en index.html/app.js
COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o   # tras crear clases nuevas en src/
chown -R www-data:www-data . && chmod 640 .env         # permisos tras editar como root
php bin/probar-notificaciones.php            # mensaje de prueba a Telegram/Slack
mysql regharina                              # consola MySQL (root)
grep regharina /var/log/nginx/error.log      # errores PHP (se registran con prefijo "regharina:")
```

### Agregar un módulo nuevo
1. Backend: controlador en `src/Controllers/`, rutas en `src/routes.php` dentro del grupo protegido
   con `->add(new Permiso('clave'))`.
2. Permisos nuevos: `INSERT INTO permisos (clave, nombre, modulo)` en una migración de `sql/`
   (el Administrador los recibe solo; aparecen en "Roles y permisos" para asignarlos).
3. Frontend: entrada en `MODULOS` (inicio de `public/assets/app.js`, con `permisos` y `grupo`
   opcional), su `<section x-show="ruta === 'id'">` en `index.html` y su carga en `cargarRuta()`.
4. `./build.sh` y `composer dump-autoload -o`.

---

## Pendientes

1. ~~Telegram~~ — **hecho** (9-oct-2026): las alertas llegan al grupo configurado en `.env`.
   Para cambiar de grupo: agregar el bot, escribir `/start@RicosPanBot`, leer `chat.id` en `getUpdates`.
2. **Slack:** crear app en api.slack.com/apps → Incoming Webhooks → URL en `SLACK_WEBHOOK_URL` y probar.
3. Opcional: respaldo automático diario de la BD (no existe), pantalla para administrar productos y
   unidades (hoy solo por SQL), etiquetas según la unidad base de cada producto (hoy dicen "kg"),
   unificar zona horaria de `created_at`.
