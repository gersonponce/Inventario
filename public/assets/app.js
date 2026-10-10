// Módulos del menú lateral. Para agregar uno:
//   1. añadir su entrada aquí (id, título, icono de ICONOS, permisos que lo habilitan y grupo opcional),
//   2. añadir su <section x-show="ruta === '<id>'"> en index.html,
//   3. si necesita datos, crear su controlador en src/Controllers/, registrar sus rutas en src/routes.php
//      y cargarlo en cargarRuta().
// Un módulo se muestra si el usuario tiene al menos uno de sus permisos.
const MODULOS = [
  { id: 'inventario', titulo: 'Inventario', icono: 'caja', permisos: ['inventario.ver', 'inventario.registrar'] },
  { id: 'eliminados', titulo: 'Eliminados', icono: 'papelera', permisos: ['inventario.restaurar'] },
  { id: 'usuarios', titulo: 'Usuarios', icono: 'usuarios', permisos: ['usuarios.gestionar'], grupo: 'Administración' },
  { id: 'roles', titulo: 'Roles y permisos', icono: 'escudo', permisos: ['roles.gestionar'], grupo: 'Administración' },
];

// Trazos de Heroicons (outline, 24px)
const ICONOS = {
  caja: 'm20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z',
  papelera: 'm14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0',
  usuarios: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
  escudo: 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
  llave: 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z',
  mas: 'M12 4.5v15m7.5-7.5h-15',
  lapiz: 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125',
  menu: 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
  cerrar: 'M6 18 18 6M6 6l12 12',
  salir: 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9',
  restaurar: 'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3',
  descargar: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
};

function ahoraLocal() {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}

document.addEventListener('alpine:init', () => {
  Alpine.data('app', () => ({
    iconos: ICONOS,
    ruta: null,
    menuAbierto: false,
    cargando: true,
    auth: false,
    usuario: null,
    csrf: '',
    credenciales: { usuario: '', password: '', error: '' },
    enviando: false,
    avisos: [],

    // Inventario
    form: { fecha_hora: ahoraLocal(), nota: '', items: [] },
    lista: [],
    listaCargando: false,

    // Administración
    usuarios: [],
    roles: [],
    permisos: [],
    // Modal abierto: null | 'usuario' | 'rol' | 'password' | 'alerta'
    modal: null,
    // Resultado de inventarios/verificar mostrado en el modal 'alerta'
    comparacion: { anterior: null, aumentos: [], canales: [] },
    modalError: '',
    fUsuario: {},
    fRol: {},
    fPassword: {},

    async init() {
      window.addEventListener('hashchange', () => this.irA(location.hash.slice(2)));
      try {
        this.aplicarSesion(await this.api('GET', 'sesion'));
        if (this.auth) this.irA(location.hash.slice(2));
      } catch (e) {
        this.aviso(e.message, 'error');
      }
      this.cargando = false;
    },

    // --- Permisos y navegación ---
    puede(...permisos) {
      return !!this.usuario && permisos.some((p) => this.usuario.permisos.includes(p));
    },
    get modulos() {
      return MODULOS.filter((m) => this.puede(...m.permisos));
    },
    // Módulos agrupados para el menú: [{ grupo, items }]
    get menu() {
      const grupos = [];
      for (const m of this.modulos) {
        let g = grupos.find((x) => x.grupo === (m.grupo || null));
        if (!g) grupos.push((g = { grupo: m.grupo || null, items: [] }));
        g.items.push(m);
      }
      return grupos;
    },
    get moduloActual() {
      return this.modulos.find((m) => m.id === this.ruta);
    },

    irA(id) {
      const visibles = this.modulos;
      this.ruta = visibles.some((m) => m.id === id) ? id : visibles[0]?.id ?? null;
      this.menuAbierto = false;
      this.cargarRuta();
    },

    cargarRuta() {
      if (this.ruta === 'inventario') {
        if (this.puede('inventario.registrar') && !this.form.items.length) this.cargarCatalogo();
        if (this.puede('inventario.ver')) this.cargarLista('inventarios');
      }
      if (this.ruta === 'eliminados') this.cargarLista('inventarios/eliminados');
      if (this.ruta === 'usuarios') { this.cargarUsuarios(); this.cargarRoles(); }
      if (this.ruta === 'roles') this.cargarRoles();
    },

    // --- API ---
    // Llama a la API REST (Slim) en /api/<ruta>
    async api(method, ruta, body) {
      const opts = { method, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
      if (method !== 'GET') opts.headers['X-CSRF-Token'] = this.csrf;
      if (body !== undefined) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
      }
      const res = await fetch('api/' + ruta, opts);
      let data = {};
      try { data = await res.json(); } catch {}
      const esLogin = method === 'POST' && ruta === 'sesion';
      if (res.status === 401 && !esLogin) {
        this.cerrarLocal();
        this.renovarSesion();
      }
      // Sin permiso o token vencido: refresca usuario/permisos/token (pudieron cambiar mientras tanto)
      if (res.status === 403) this.renovarSesion();
      if (!res.ok) throw new Error(data.error || `Error del servidor (${res.status})`);
      return data;
    },

    aplicarSesion(s) {
      this.csrf = s.csrf;
      this.auth = !!s.auth;
      this.usuario = s.usuario || null;
    },

    async renovarSesion() {
      try {
        const antes = this.usuario?.permisos.join();
        this.aplicarSesion(await this.api('GET', 'sesion'));
        // Si cambiaron los permisos, el módulo actual podría ya no estar permitido
        if (this.auth && antes !== this.usuario?.permisos.join()) this.irA(this.ruta);
      } catch {}
    },

    cerrarLocal() {
      this.auth = false;
      this.usuario = null;
      this.modal = null;
      this.lista = [];
      this.usuarios = [];
      this.form.items = [];
    },

    // --- Sesión ---
    async login() {
      this.enviando = true;
      this.credenciales.error = '';
      try {
        this.aplicarSesion(await this.api('POST', 'sesion', { usuario: this.credenciales.usuario, password: this.credenciales.password }));
        this.credenciales = { usuario: '', password: '', error: '' };
        this.irA(location.hash.slice(2));
      } catch (e) {
        this.credenciales.error = e.message;
      } finally {
        this.enviando = false;
      }
    },

    async logout() {
      try { await this.api('DELETE', 'sesion'); } catch {}
      this.cerrarLocal();
      await this.renovarSesion();
    },

    abrirPassword() {
      this.fPassword = { actual: '', nueva: '', confirmar: '' };
      this.abrirModal('password');
    },

    async guardarPassword() {
      if (this.fPassword.nueva !== this.fPassword.confirmar) {
        this.modalError = 'Las contraseñas nuevas no coinciden.';
        return;
      }
      await this.enviarModal(async () => {
        await this.api('PUT', 'sesion/password', { actual: this.fPassword.actual, nueva: this.fPassword.nueva });
        this.aviso('Contraseña actualizada.');
      });
    },

    // --- Inventario ---
    async cargarCatalogo() {
      try {
        const { productos } = await this.api('GET', 'catalogo');
        this.form.items = productos.map((p) => ({
          producto_id: p.id,
          nombre: p.nombre,
          unidades: p.unidades,
          unidad_id: p.unidades[0]?.id,
          cantidad: 0,
          abierto: 0,
        }));
      } catch (e) {
        this.aviso(e.message, 'error');
      }
    },

    factor(it) {
      return it.unidades.find((u) => u.id === it.unidad_id)?.factor ?? 0;
    },
    peso(it) {
      return (parseFloat(it.cantidad) || 0) * this.factor(it);
    },
    total(it) {
      return this.peso(it) + (parseFloat(it.abierto) || 0);
    },

    datosInventario() {
      return {
        fecha_hora: this.form.fecha_hora,
        nota: this.form.nota,
        items: this.form.items.map((it) => ({
          producto_id: it.producto_id,
          unidad_id: it.unidad_id,
          cantidad: Number(it.cantidad),
          abierto: Number(it.abierto),
        })),
      };
    },

    // Antes de guardar compara con el inventario anterior; si algún producto aumentó, pide confirmación
    async guardar() {
      this.enviando = true;
      try {
        this.comparacion = await this.api('POST', 'inventarios/verificar', this.datosInventario());
        if (this.comparacion.aumentos.length) {
          this.abrirModal('alerta');
          return;
        }
        await this.registrar();
      } catch (e) {
        this.aviso(e.message, 'error');
      } finally {
        this.enviando = false;
      }
    },

    // "Guardar de todos modos" desde el modal de alerta
    async confirmarAlerta() {
      await this.enviarModal(() => this.registrar());
    },

    async registrar() {
      const r = await this.api('POST', 'inventarios', this.datosInventario());
      this.form.items.forEach((it) => { it.cantidad = 0; it.abierto = 0; });
      this.form.nota = '';
      this.form.fecha_hora = ahoraLocal();
      let msg = 'Inventario registrado.';
      if (r.alertas) msg += r.canales.length ? ` Se envió la alerta por ${r.canales.join(' y ')}.` : ' Alerta registrada (no hay canales de aviso configurados).';
      this.aviso(msg);
      if (this.puede('inventario.ver')) this.cargarLista('inventarios');
    },

    async cargarLista(ruta) {
      this.listaCargando = true;
      try {
        this.lista = (await this.api('GET', ruta)).inventarios;
      } catch (e) {
        this.lista = [];
        this.aviso(e.message, 'error');
      } finally {
        this.listaCargando = false;
      }
    },

    async eliminar(inv) {
      if (!confirm(`¿Eliminar el inventario del ${inv.fecha_texto} ${inv.hora}?\nPodrá restaurarse desde "Eliminados".`)) return;
      try {
        await this.api('DELETE', `inventarios/${inv.id}`);
        this.aviso('Inventario eliminado.');
        this.cargarRuta();
      } catch (e) {
        this.aviso(e.message, 'error');
      }
    },

    async restaurar(inv) {
      try {
        await this.api('POST', `inventarios/${inv.id}/restaurar`);
        this.aviso('Inventario restaurado.');
        this.cargarRuta();
      } catch (e) {
        this.aviso(e.message, 'error');
      }
    },

    // --- Usuarios ---
    async cargarUsuarios() {
      try {
        this.usuarios = (await this.api('GET', 'usuarios')).usuarios;
      } catch (e) {
        this.aviso(e.message, 'error');
      }
    },

    // Roles que el usuario actual puede asignar (solo un administrador asigna el rol Administrador)
    get rolesAsignables() {
      return this.roles.filter((r) => !r.es_sistema || this.usuario?.es_admin);
    },

    abrirUsuario(u = null) {
      this.fUsuario = u
        ? { id: u.id, usuario: u.usuario, nombre: u.nombre, rol_id: u.rol_id, activo: u.activo, password: '', esYo: u.id === this.usuario.id }
        : { id: null, usuario: '', nombre: '', rol_id: this.rolesAsignables.find((r) => !r.es_sistema)?.id ?? '', activo: true, password: '', esYo: false };
      this.abrirModal('usuario');
    },

    async guardarUsuario() {
      const f = this.fUsuario;
      await this.enviarModal(async () => {
        const datos = { nombre: f.nombre, rol_id: Number(f.rol_id), activo: f.activo, password: f.password };
        if (f.id) {
          await this.api('PUT', `usuarios/${f.id}`, datos);
          this.aviso('Usuario actualizado.');
          if (f.esYo) this.renovarSesion();
        } else {
          await this.api('POST', 'usuarios', { ...datos, usuario: f.usuario });
          this.aviso('Usuario creado.');
        }
        this.cargarUsuarios();
        this.cargarRoles();
      });
    },

    // --- Roles ---
    async cargarRoles() {
      try {
        const r = await this.api('GET', 'roles');
        this.roles = r.roles;
        this.permisos = r.permisos;
      } catch (e) {
        this.aviso(e.message, 'error');
      }
    },

    // Catálogo de permisos agrupado por módulo: [{ modulo, permisos }]
    get permisosPorModulo() {
      const grupos = [];
      for (const p of this.permisos) {
        let g = grupos.find((x) => x.modulo === p.modulo);
        if (!g) grupos.push((g = { modulo: p.modulo, permisos: [] }));
        g.permisos.push(p);
      }
      return grupos;
    },
    nombrePermiso(clave) {
      return this.permisos.find((p) => p.clave === clave)?.nombre ?? clave;
    },

    abrirRol(r = null) {
      this.fRol = r
        ? { id: r.id, nombre: r.nombre, descripcion: r.descripcion || '', permisos: [...r.permisos] }
        : { id: null, nombre: '', descripcion: '', permisos: [] };
      this.abrirModal('rol');
    },

    async guardarRol() {
      const f = this.fRol;
      await this.enviarModal(async () => {
        const datos = { nombre: f.nombre, descripcion: f.descripcion, permisos: f.permisos };
        if (f.id) {
          await this.api('PUT', `roles/${f.id}`, datos);
          this.aviso('Rol actualizado.');
        } else {
          await this.api('POST', 'roles', datos);
          this.aviso('Rol creado.');
        }
        this.cargarRoles();
        // Si se editó el rol propio, los permisos del usuario actual cambian
        if (f.id === this.usuario.rol_id) this.renovarSesion();
      });
    },

    async eliminarRol(r) {
      if (!confirm(`¿Eliminar el rol "${r.nombre}"?`)) return;
      try {
        await this.api('DELETE', `roles/${r.id}`);
        this.aviso('Rol eliminado.');
        this.cargarRoles();
      } catch (e) {
        this.aviso(e.message, 'error');
      }
    },

    // --- Modal genérico ---
    abrirModal(tipo) {
      this.modalError = '';
      this.modal = tipo;
      this.$nextTick(() => this.$refs.modal?.querySelector('form:not([style*="none"]) :is(input:not([disabled]), select, button[data-foco])')?.focus());
    },

    async enviarModal(accion) {
      this.enviando = true;
      this.modalError = '';
      try {
        await accion();
        this.modal = null;
      } catch (e) {
        this.modalError = e.message;
      } finally {
        this.enviando = false;
      }
    },

    // --- Utilidades de vista ---
    fmt(n, dec = 3) {
      return Number(n || 0).toFixed(dec);
    },
    fmtCantidad(n) {
      return String(parseFloat(Number(n || 0).toFixed(3)));
    },
    iniciales(nombre) {
      return (nombre || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
    },
    aviso(msg, tipo = 'ok') {
      const id = Date.now() + Math.random();
      this.avisos.push({ id, msg, tipo });
      setTimeout(() => { this.avisos = this.avisos.filter((a) => a.id !== id); }, 4000);
    },
  }));
});
