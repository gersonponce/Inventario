# API — Inventario

API REST en JSON para consultar y registrar inventarios de **cualquier producto**. La instalación
actual (`regharina.stardata.pe`) se usa para harina; los productos y sus unidades se obtienen de
`GET /api/catalogo`, así que una integración no debe asumir productos ni ids fijos.

**Unidades:** cada producto tiene una unidad base (ej. kg) y presentaciones derivadas con un factor
(ej. saco = 50 kg). Todo total se expresa en la **unidad base del producto**. Los campos que
terminan en `_kg` (`aumento_kg`, `anterior_kg`, `nuevo_kg`, `diferencia_kg`) conservan ese nombre
por historia, pero su valor está en la unidad base del producto.

- **Base URL:** `https://regharina.stardata.pe/api`
- **Formato:** JSON en el cuerpo (`Content-Type: application/json`) y en las respuestas (UTF-8).
- **Zona horaria:** todas las fechas están en hora de Lima (`America/Lima`, UTC−5).

---

## 1. Autenticación

La API usa **sesión con cookie** (no token Bearer). Para conectarse hay que seguir 3 pasos:

1. `GET /api/sesion` → devuelve el `csrf` y crea la cookie de sesión `regharina`.
2. `POST /api/sesion` con usuario y contraseña, enviando la cookie y la cabecera `X-CSRF-Token`.
   La respuesta trae un **nuevo** `csrf`: úsalo desde ese momento.
3. En las siguientes peticiones envía siempre la cookie. Las escrituras (`POST`, `PUT`, `DELETE`)
   también necesitan la cabecera `X-CSRF-Token`.

| Regla | Detalle |
|---|---|
| Cookie | `regharina` — HttpOnly, Secure, SameSite=Strict |
| CSRF | Cabecera `X-CSRF-Token` obligatoria en toda escritura (también en el login) |
| Permisos | Cada ruta exige un permiso del rol del usuario (ver tabla en la sección 3) |
| Cambios de rol | Se aplican desde la siguiente petición (el usuario se recarga cada vez) |

### Ejemplo con `curl`

```bash
API=https://regharina.stardata.pe/api
JAR=cookies.txt

# 1) Obtener el token CSRF (y la cookie)
CSRF=$(curl -s -c $JAR $API/sesion | sed -E 's/.*"csrf":"([^"]+)".*/\1/')

# 2) Iniciar sesión (la respuesta trae un csrf nuevo)
CSRF=$(curl -s -b $JAR -c $JAR -X POST $API/sesion \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF" \
  -d '{"usuario":"mi_usuario","password":"mi_contraseña"}' | sed -E 's/.*"csrf":"([^"]+)".*/\1/')

# 3) Consultar inventarios
curl -s -b $JAR $API/inventarios

# 4) Registrar un inventario
curl -s -b $JAR -X POST $API/inventarios \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF" \
  -d '{"fecha_hora":"2026-10-10T08:00","nota":"Cierre","items":[
        {"producto_id":1,"unidad_id":2,"cantidad":83,"abierto":16.32},
        {"producto_id":2,"unidad_id":2,"cantidad":40,"abierto":0}]}'

# 5) Cerrar sesión
curl -s -b $JAR -X DELETE $API/sesion -H "X-CSRF-Token: $CSRF"
```

### Ejemplo con JavaScript (mismo dominio)

```js
const api = async (metodo, ruta, cuerpo, csrf) => {
  const r = await fetch(`/api/${ruta}`, {
    method: metodo,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', ...(csrf && { 'X-CSRF-Token': csrf }) },
    body: cuerpo ? JSON.stringify(cuerpo) : undefined,
  });
  const datos = await r.json();
  if (!r.ok) throw new Error(datos.error);
  return datos;
};

let { csrf } = await api('GET', 'sesion');
({ csrf } = await api('POST', 'sesion', { usuario: 'mi_usuario', password: 'mi_contraseña' }, csrf));
const { inventarios } = await api('GET', 'inventarios');
```

> La cookie es `SameSite=Strict` y la API no habilita CORS: desde otro dominio del navegador no
> funciona. Desde un servidor o script (curl, Python, PHP…) sí, guardando la cookie.

---

## 2. Errores

Toda respuesta de error tiene la forma `{"error": "mensaje en español"}` con su código HTTP:

| Código | Cuándo |
|---|---|
| 400 | Datos inválidos (validación) |
| 401 | Sin sesión, sesión expirada o login incorrecto |
| 403 | Sin permiso para la acción, o `X-CSRF-Token` inválido |
| 404 | Recurso no encontrado (o no hay registros para exportar) |
| 405 | Método no permitido |
| 409 | Duplicado (usuario o rol ya existe) / rol con usuarios asignados |
| 500 | Error interno |

---

## 3. Endpoints

| Método y ruta | Permiso | Descripción |
|---|---|---|
| `GET /sesion` | — | Estado de la sesión y token CSRF |
| `POST /sesion` | — | Iniciar sesión |
| `DELETE /sesion` | — | Cerrar sesión |
| `PUT /sesion/password` | sesión | Cambiar la propia contraseña |
| `GET /catalogo` | `inventario.registrar` | Productos y sus unidades de medida |
| `GET /inventarios` | `inventario.ver` | Inventarios activos (máx. 200, más recientes primero) |
| `POST /inventarios/verificar` | `inventario.registrar` | Compara con el inventario anterior **sin guardar** |
| `POST /inventarios` | `inventario.registrar` | Registra un inventario |
| `GET /inventarios/exportar` | `inventario.exportar` | Descarga Excel (`.xlsx`) |
| `GET /inventarios/eliminados` | `inventario.restaurar` | Inventarios eliminados |
| `DELETE /inventarios/{id}` | `inventario.eliminar` | Elimina (soft delete) |
| `POST /inventarios/{id}/restaurar` | `inventario.restaurar` | Restaura un eliminado |
| `GET /usuarios` | `usuarios.gestionar` | Lista de usuarios |
| `POST /usuarios` | `usuarios.gestionar` | Crea usuario |
| `PUT /usuarios/{id}` | `usuarios.gestionar` | Edita usuario |
| `GET /roles` | `roles.gestionar` o `usuarios.gestionar` | Roles y catálogo de permisos |
| `POST /roles` | `roles.gestionar` | Crea rol |
| `PUT /roles/{id}` | `roles.gestionar` | Edita rol |
| `DELETE /roles/{id}` | `roles.gestionar` | Elimina rol (solo si no tiene usuarios) |

---

## 4. Detalle

### Sesión

**`GET /sesion`** → `200`
```json
{
  "auth": true,
  "usuario": {
    "id": 1, "usuario": "jperez", "nombre": "Juan Pérez", "rol_id": 1,
    "rol": "Administrador", "es_admin": true,
    "permisos": ["inventario.ver", "inventario.registrar", "..."]
  },
  "csrf": "9f2c…"
}
```
Sin sesión: `{"auth": false, "usuario": null, "csrf": "…"}`.

**`POST /sesion`** — cuerpo `{"usuario": "...", "password": "..."}` → `200` con la misma forma que
`GET /sesion` (con un `csrf` nuevo). Credenciales incorrectas o usuario inactivo → `401`.

**`DELETE /sesion`** → `{"auth": false}`

**`PUT /sesion/password`** — cuerpo `{"actual": "...", "nueva": "..."}` (mínimo 8 caracteres) → `{"ok": true}`

### Catálogo

**`GET /catalogo`** → `200`
```json
{
  "productos": [
    {
      "id": 1, "nombre": "Harina Extra", "unidad_base": "kg",
      "unidades": [
        {"id": 2, "nombre": "Saco 50 kg", "abreviatura": "saco", "factor": 50.0},
        {"id": 1, "nombre": "Kilogramo", "abreviatura": "kg", "factor": 1.0}
      ]
    },
    {"id": 2, "nombre": "Harina Especial", "unidad_base": "kg", "unidades": ["..."]}
  ]
}
```
Usa estos `id` como `producto_id` y `unidad_id` al registrar.

### Registrar inventario

**`POST /inventarios`**
```json
{
  "fecha_hora": "2026-10-10T08:00",
  "nota": "Opcional",
  "items": [
    {"producto_id": 1, "unidad_id": 2, "cantidad": 83, "abierto": 16.32},
    {"producto_id": 2, "unidad_id": 2, "cantidad": 40, "abierto": 0}
  ]
}
```

| Campo | Regla |
|---|---|
| `fecha_hora` | `YYYY-MM-DDTHH:MM` o `YYYY-MM-DDTHH:MM:SS` (hora de Lima) |
| `nota` | Texto opcional |
| `items` | Al menos uno; cada producto una sola vez |
| `unidad_id` | Debe ser la unidad base del producto o una derivada (ej. saco) |
| `cantidad`, `abierto` | Números ≥ 0. `abierto` = cantidad suelta, en la unidad base |

El servidor calcula el total: **`total = cantidad × factor + abierto`** (en la unidad base) (redondeo a 3 decimales).

Respuesta `201`:
```json
{"id": 25, "alertas": 1, "canales": ["Telegram"]}
```
- `alertas`: cuántos productos superaron al inventario anterior. El inventario **se guarda igual**
  y se envía un aviso a los `canales` configurados (Telegram/Slack).

**`POST /inventarios/verificar`** — mismo cuerpo; no guarda nada. → `200`
```json
{
  "anterior": {"id": 24, "fecha_hora": "2026-10-09 18:00:00", "fecha_texto": "jueves 9 de octubre de 2026", "hora": "18:00"},
  "aumentos": [
    {"producto_id": 1, "producto": "Harina Extra", "anterior_kg": 4100.0, "nuevo_kg": 4166.32, "diferencia_kg": 66.32}
  ],
  "canales": ["Telegram"]
}
```
Si no hay inventario anterior: `"anterior": null, "aumentos": []`.

### Consultar inventarios

**`GET /inventarios`** (y **`GET /inventarios/eliminados`**, misma forma) → `200`
```json
{
  "inventarios": [
    {
      "id": 25,
      "fecha_hora": "2026-10-10 08:00:00",
      "fecha_texto": "viernes 10 de octubre de 2026",
      "hora": "08:00:00",
      "nota": "Cierre",
      "creado_por": "Operador 1",
      "eliminado": null,
      "eliminado_por": null,
      "items": [
        {
          "producto": "Harina Extra",
          "total": 4166.32,
          "aumento_kg": 66.32,
          "detalles": {
            "unidad_id": 2, "unidad": "Saco 50 kg", "abreviatura": "saco", "factor": 50.0,
            "cantidad": 83.0, "cantidad_base": 4150.0, "abierto": 16.32, "unidad_base": "kg"
          }
        }
      ]
    }
  ]
}
```
- `total`: total del producto en su unidad base (`detalles.unidad_base`). `aumento_kg`: cuánto subió respecto al anterior (`null` si no subió).
- En eliminados, `eliminado` trae la fecha y hora de eliminación y `eliminado_por` el nombre.

**`GET /inventarios/exportar`** → archivo `.xlsx` (`inventario-harina-AAAA-MM-DD-HHMM.xlsx`),
una fila por producto. Sin registros → `404`.

**`DELETE /inventarios/{id}`** · **`POST /inventarios/{id}/restaurar`** → `{"ok": true}` (o `404`).

### Usuarios

**`GET /usuarios`** →
```json
{"usuarios": [{"id": 2, "usuario": "operador1", "nombre": "Operador 1", "rol_id": 2, "rol": "Operador",
               "es_admin": false, "activo": true, "ultimo_acceso": "viernes 10 de octubre de 2026 07:55"}]}
```

**`POST /usuarios`** — `{"usuario", "nombre", "password", "rol_id", "activo"}` → `201 {"id": n}`
- `usuario`: 3–50 caracteres (letras, números, `.`, `-`, `_`), único. `password`: mínimo 8.

**`PUT /usuarios/{id}`** — `{"nombre", "rol_id", "activo", "password"?}` → `{"ok": true}`
- `password` vacío = no se cambia. Nadie puede desactivarse ni cambiar su propio rol; solo un
  administrador puede asignar el rol Administrador o editar a otro administrador.

### Roles

**`GET /roles`** →
```json
{
  "roles": [{"id": 2, "nombre": "Operador", "descripcion": "…", "es_sistema": false, "usuarios": 1,
             "permisos": ["inventario.ver", "inventario.registrar", "inventario.eliminar"]}],
  "permisos": [{"clave": "inventario.ver", "nombre": "Ver historial de inventarios", "modulo": "Inventario"}]
}
```

**`POST /roles`** · **`PUT /roles/{id}`** — `{"nombre", "descripcion", "permisos": ["clave", ...]}`
- `nombre` obligatorio (máx. 50) y único; `descripcion` máx. 255. El rol Administrador no se puede modificar.

**`DELETE /roles/{id}`** → `{"ok": true}`; `409` si algún usuario tiene ese rol.

---

## 5. Permisos

| Clave | Permite |
|---|---|
| `inventario.ver` | Ver historial de inventarios |
| `inventario.registrar` | Registrar inventarios (y ver el catálogo) |
| `inventario.eliminar` | Eliminar inventarios |
| `inventario.restaurar` | Ver y restaurar eliminados |
| `inventario.exportar` | Exportar a Excel |
| `usuarios.gestionar` | Gestionar usuarios |
| `roles.gestionar` | Gestionar roles y permisos |

> Para una integración externa se recomienda crear un usuario propio con un rol que tenga solo
> los permisos necesarios (por ejemplo, `inventario.ver`).
