# REPORTE DE ANÁLISIS DETALLADO - eTeam-Manager

## 1. CSS - CLASES DE PROFILE Y TAMAÑOS

### Ubicación: [css/main.css](css/main.css)

#### Clases Identificadas:
- **`.profile-page`** (línea 2916): Contenedor general, grid con espaciado `var(--space-4)`
- **`.profile-hero`** (línea 2921): Grid 2 columnas con `align-items: center`, gap `var(--space-4)`
  - `grid-template-columns: minmax(0, 1fr) auto;`
- **`.profile-hero-copy`** (línea 2927): Gap `var(--space-2)` 
- **`.profile-hero-title`** (línea 2931):
  - `font-size: 28px` ⚠️ (inconsistencia con h2: 24px)
  - `font-weight: bold`
  - `line-height: var(--lh-title)` (1.2)
- **`.profile-hero-avatar`** (línea 2937):
  - `width: 88px; height: 88px`
  - `border-radius: 24px`
  - `font-size: 30px` (para iniciales)
- **`.profile-avatar-preview`** (línea 3059):
  - `width: 88px; height: 88px`
  - `border-radius: 24px`
- **`.profile-layout`** (línea 2950):
  - `grid-template-columns: minmax(280px, 0.8fr) minmax(0, 1.2fr)`
  - Gap: `var(--space-4)` (16px)
- **`.profile-summary-grid`** (línea 2957):
  - `grid-template-columns: repeat(2, minmax(0, 1fr))`
  - Gap: `var(--space-3)` (12px)
- **`.profile-summary-item`** (línea 2963):
  - Padding: `var(--space-3)` (12px)
  - `border-radius: var(--radius-md)` (10px)
- **`.profile-summary-label`** (línea 2971):
  - `font-size: var(--font-size-small)` (12px)
  - `color: var(--text-muted)` (gris neutro)
- **`.profile-summary-value`** (línea 2977):
  - `font-weight: var(--font-weight-semibold)` (600)
  - `color: var(--text-main)` (blanco)

#### Inconsistencias Identificadas:
1. **`.profile-hero-title` = 28px** vs **`.h2` = 24px**: Hay inconsistencia de 4px
2. **Avatar mixto**: Usa tamaño fijo (88px) mientras dashboard avatar es 72px (inconsistencia)
3. **Espaciado de summary**: Gap 12px puede ser muy apretado en mobile

---

## 2. BUSCADOR - ANALYSIS

### Archivo: [pages/search.php](pages/search.php)

#### Estructura del Formulario:
```php
<form method="get" action="app.php">
  <input name="q" type="search" placeholder="Escribe al menos 2 caracteres" />
  <select name="type">
    <option value="users">Usuarios</option>
    <option value="teams">Equipos</option>
  </select>
  <button class="btn btn-primary" type="submit">Buscar</button>
  <div id="page-search-suggestions"></div>
</form>
```

#### Búsqueda:
- Requiere **mínimo 2 caracteres** (validación en SQL con `LIKE '%q%'`)
- Búsqueda en campos: `username OR email` (usuarios) / `name OR tag` (equipos)
- Límite de resultados: **50 registros**

### Archivo: [js/main.js](js/main.js)

#### Autocomplete AJAX:
```javascript
bindAutocomplete(inputEl, typeEl, suggestionsEl) {
  - Escucha eventos de 'input'
  - Retraso de 220ms (debounce)
  - Fetch a /pages/search_suggest.php?q=...&type=...
  - Renderiza en .sidebar-suggestion-item
  - Cierra al hacer click fuera
}
```

#### Características:
- **Debounce de 220ms** para no saturar servidor
- Valida mínimo 2 caracteres antes de hacer request
- Escapado HTML en renderizado (`escapeHtml()` función)
- URL encoding en encodeURIComponent()

### Archivo: [pages/search_suggest.php](pages/search_suggest.php)

#### Recomendaciones:
```php
if (mb_strlen($q) < 2) echo json_encode([]);
```

#### Queries:
- **Para Teams**: `SELECT id, name, tag FROM teams WHERE name LIKE :q OR tag LIKE :q LIMIT 10`
- **Para Users**: `SELECT id, username, avatar_url FROM users WHERE username LIKE :q OR email LIKE :q LIMIT 10`
- Límite de **10 resultados** (más restrictivo que la búsqueda principal)
- Error handling: Captura excepciones y devuelve array vacío

#### Ubicaciones:
- Formulario sidebar: `#sidebar-search-input` (incluido en sidebar.php)
- Formulario página: `#page-search-input` (incluido en search.php)
- Ambos triggers mismo autocomplete en main.js

---

## 3. ALERTAS/NOTIFICACIONES - COLORES IDENTIFICADOS

### Ubicación: [css/main.css](css/main.css)

#### Variables CSS Definidas (líneas 22-26):
```css
--success: #2ECC71;  /* Verde vivo */
--warning: #F39C12;  /* Naranja */
--error: #E74C3C;    /* Rojo */
--info: #3498DB;     /* Azul */
```

#### Clases de Badges (líneas 3366-3395):
```css
.badge {
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid var(--border-subtle);
  font-size: var(--font-size-small);  /* 12px */
}

.badge-success { border-color: var(--success); }     /* #2ECC71 */
.badge-warning { border-color: var(--warning); }     /* #F39C12 */
.badge-error { border-color: var(--error); }         /* #E74C3C */
.badge-info { border-color: var(--info); }           /* #3498DB */
```

#### Clases de Feedback (líneas 2743-2759):
```css
.error-box.app-feedback {
  width: 100%;
  font-size: 15px;
  font-weight: semibold;
}

.error-box.app-feedback-success {
  color: var(--text-main);
  background: linear-gradient(135deg, rgba(46, 204, 113, 0.16), rgba(46, 204, 113, 0.08));
  border-color: rgba(46, 204, 113, 0.35);
  border-left-color: rgba(39, 174, 96, 0.92);
}
```

#### Notificaciones de App (líneas 530-545):
```css
.app-notification-item.is-unread {
  border-left: 3px solid var(--info);  /* #3498DB */
  background: color-mix(in srgb, var(--info) 12%, var(--bg-secondary));
}
```

#### Notificaciones de Página (líneas 3153-3175):
```css
.notification-card.is-unread {
  border-left: 4px solid var(--info);
  background: color-mix(in srgb, var(--info) 10%, var(--bg-secondary));
}
```

#### Timeline Dots (líneas 965-980):
```css
.dashboard-timeline-dot {
  background: var(--text-muted);  /* Por defecto gris */
}
.dashboard-timeline-dot.is-event {
  background: var(--info);  /* Azul */
}
.dashboard-timeline-dot.is-task {
  background: var(--warning);  /* Naranja */
}
.dashboard-timeline-dot.is-scrim {
  background: var(--color-primary);  /* Rojo #FF4655 */
}
```

#### Inconsistencias:
- ⚠️ **Success badge solo tiene borde**, sin fondo tintado
- ⚠️ **Notificación success** usa gradiente con rgba() mientras badges usan border-color simple
- ⚠️ **Timeline dots** no tienen clase para error/success, solo event/task/scrim

---

## 4. CALENDAR - ANÁLISIS DE ESTILOS

### Archivo: [pages/calendar.php](pages/calendar.php)

#### Clases Identificadas:

**Layout Principal:**
- `.calendar-page` (línea 2476): grid gap `var(--space-4)`
- `.calendar-layout` (línea 2486):
  - `grid-template-columns: minmax(0, 1.6fr) minmax(300px, 0.9fr);` ⚠️ min-width 300px PUEDE SER ESTRECHO
  - Gap: `var(--space-4)`

**Células del Calendario:**
- `.calendar-day` (línea 2514):
  - `min-height: 220px` ⚠️ Altura fija puede cortar contenido
  - Padding: `12px`
  - Grid con `gap: 10px`
- `.calendar-day-count` (línea 2530):
  - `min-width: 30px; height: 30px`
  - Badge para contador de eventos

**Eventos (Pills):**
- `.calendar-event-pill` (línea 2656):
  - `border-left-width: 4px`
  - `padding: 10px` (compacto)
- `.calendar-event-pill.is-scrim`:
  - `border-left-color: var(--color-primary)` (Rojo #FF4655)
- `.calendar-event-pill.is-event`:
  - `border-left-color: var(--info)` (Azul #3498DB)

**Triggers (Botones de Día):**
- `.calendar-day-trigger` (línea 2588):
  - `width: 100%`
  - `border-left-width: 4px`
  - Padding: `10px 12px`

**Overlay (Modal):**
- `.calendar-overlay` (lines 213-244 en calendar.php): Estructura HTML con data attributes

#### Problemas Identificados:
1. ⚠️ **`.calendar-layout` min-width 300px**: Puede ser estrecho en tablets
2. ⚠️ **`.calendar-day` min-height 220px**: Altura fija sin responsive
3. ⚠️ **Padding compacto 10px**: Eventos pueden verse apretados
4. ⚠️ **Sin ajuste responsive**: Media query falta para cambiar columnas en mobile

#### Eventos y Recordatorios en HTML:
- Badge `badge-info` para eventos
- Badge `badge-success` para scrims recientes (mostrado en historial)
- Badge `badge-warning` para estado "Agenda conectada"

---

## 5. NORMATIVA.HTML - ANÁLISIS ESTRUCTURAL

### Ubicación: [normativa.html](normativa.html)

#### Estructura HTML:
```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Normativa de acceso | eTeam Manager</title>
  <meta name="description" content="Normativa de acceso...">
  <link rel="stylesheet" href="css/main.css">
</head>
<body>
  <header class="topbar landing-header">
  <main class="landing-section" style="padding-top: 32px;">
    <div class="container">
      <div class="card" style="margin-bottom: 24px;">
        <span class="badge" style="margin-bottom: 12px;">Lectura obligatoria</span>
        <h1 class="h1">Normativa de acceso y uso...</h1>
      </div>
      <div class="landing-grid landing-grid-2">
        <!-- 4 secciones con h2 y landing-list-item -->
      </div>
    </div>
  </main>
</body>
</html>
```

#### Problemas Identificados:
1. ⚠️ **Estilos inline**: `style="margin-bottom: 24px;"` en línea 31 (mejor usar CSS)
2. ⚠️ **Estilos inline**: `style="padding-top: 32px;"` en línea 24 (repetición)
3. ✅ **Estructura HTML correcta**: Usa clase `landing-grid-2` apropiadamente
4. ✅ **Accesibilidad**: Usa `lang="es"` correctamente
5. ✅ **Meta tags**: Viewport, descripción presentes

#### Contenido:
- 4 secciones principales de normativa
- Badges decorativos sin funcionalidad
- Botones de navegación al inicio/login

---

## 6. ARCHIVOS CON CÓDIGO DE DESARROLLO Y ALERTAS HARDCODEADAS

### Búsqueda: "TODO", "REVISAR", "AQUI NOS", debug, test

#### Archivo de Prueba - CRITICO:
**[test-includes.php](test-includes.php)** 
- ⚠️ **Archivo de PRUEBA EN PRODUCCIÓN**
- Propósito: Probar estructura de includes y componentes
- Ruta: Accesible públicamente en raíz
- Seccion: Líneas 29-36 muestran menú de secciones de prueba
  ```php
  'dashboard' => ['label' => 'Dashboard', 'href' => 'test-includes.php?section=dashboard'],
  'organizations' => [...],
  'teams' => [...],
  'scrims' => [...],
  'calendar' => [...],
  'boards' => [...],
  'notes' => [...],
  'settings' => [...],
  ```
- Mock user: Línea 24 `'role' => 'Tester'`
- **DEBE SER REMOVIDO O PROTEGIDO CON AUTH**

#### Comentarios de Desarrollo:
**[pages/dashboard.php]** (línea 383)
- `<span>Revisar el roster, los miembros y los roles del equipo activo.</span>`
- `<span>Entrar al historial competitivo y revisar resultados recientes.</span>` (línea 398)
- `<span>Revisar tareas operativas y prioridades pendientes.</span>` (línea 403)
- **Tipo**: Instrucciones/guía en HTML, no crítico pero innecesario

**[pages/notifications.php]** (línea 106)
- `<p>Desde aquí puedes revisar la actividad reciente de la app...</p>`
- **Tipo**: Descripción normal, válido

**[pages/scrims.php]** (línea 121)
- `<div class="small">Para revisar errores y patrones.</div>`
- **Tipo**: Descripción normal

**[pages/scrim-detail.php]** (línea 56)
- `<p>Esta vista aterriza el contexto del enfrentamiento para revisar score...</p>`
- **Tipo**: Descripción normal

#### Database Connection Error Message:
**[includes/db.php]** (línea 55)
- ⚠️ Código: `die('Error en connectar amb la base de dades. Revisa .env, DB_HOST, DB_NAME, DB_USER y DB_PASSWORD.');`
- **Tipo**: Error message hardcodeado
- **Problema**: Mezcla catalán "connectar" con español "Revisa"
- **CRÍTICO EN PRODUCCIÓN**: Expone variable names: DB_HOST, DB_NAME, DB_USER, DB_PASSWORD

#### Profile.php - Fallback con PDOException:
**[profile.php]** (líneas 13-45)
- Estructura try/catch para migración de schema
- Comentarios claros sobre fallback
- **Tipo**: Válido, es manejo de error

#### SQL con LIMIT 1:
**[includes/auth.php]** (línea 300)
- `'UPDATE users SET last_login_at = NOW() WHERE id = :id LIMIT 1'`
- **Tipo**: Práctica defensiva, válido

---

## RESUMEN DE HALLAZGOS CRÍTICOS

### 🔴 CRÍTICO:
1. **test-includes.php** - Archivo de prueba expuesto públicamente
2. **db.php error message** - Expone nombres de variables de conexión

### 🟡 IMPORTANTE:
1. **CSS inconsistencias** - Avatar 88px vs 72px en otros lados
2. **calendar.php min-width 300px** - Muy estrecho para tablets
3. **Estilos inline en normativa.html** - Duplicación con CSS

### 🟢 MENOR:
1. **Profile-hero-title 28px** - Inconsistencia de tipografía
2. **Validación hardcodeada** - Mínimo 2 caracteres en búsqueda

---

## RECOMENDACIONES

### Inmediatas:
- ✅ Mover/proteger `test-includes.php`
- ✅ Mejorar mensaje de error en `db.php`
- ✅ Remover estilos inline de `normativa.html`

### A Corto Plazo:
- Estandarizar tamaños de avatar (elegir 72px o 88px)
- Añadir media queries para calendar en mobile
- Expandir badges success con estilos de fondo

### A Mediano Plazo:
- Completar validación de tipos en búsqueda
- Documentar variables CSS en comentarios
- Revisar responsividad general del grid calendar

