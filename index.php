<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTeam Manager — Gestion interna para equipos de eSports</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="css/main.css">
</head>
<body>

    <header class="topbar landing-header">
        <div class="container landing-nav" style="padding: 0;">
            <a class="landing-brand" href="#inicio" aria-label="Ir al inicio">
                <span class="landing-brand-title">eTeam Manager</span>
                <span class="small" style="margin-left: 10px;">Plataforma interna de gestión para eSports</span>
            </a>

            <nav aria-label="Navegación principal" class="landing-nav-right">
                <div class="landing-links">
                    <a class="landing-link" href="#inicio">Inicio</a>
                    <a class="landing-link" href="#funciones">Funciones</a>
                    <a class="landing-link" href="#flujo">Flujo</a>
                    <a class="landing-link" href="#roles">Roles</a>
                    <a class="landing-link" href="#contacto">Contacto</a>
                    <a class="landing-link" id="login-register" href="app.php?view=login">Acceso</a>
                </div>
                <a class="btn btn-primary landing-cta" href="app.php?view=login">Iniciar sesión</a>
            </nav>
        </div>
    </header>

    <main>
        <section id="inicio" class="landing-hero">
            <div class="container">
                <div class="landing-hero-content">
                    <div>
                        <span class="badge" style="margin-bottom: 12px;">Organiza el dia a dia de una organizacion competitiva</span>
                        <h1 class="h1" style="margin-bottom: 12px;">Gestiona equipos, scrims, calendario, tareas y notas desde un solo sitio.</h1>
                        <p style="max-width: 720px;">eTeam Manager es una aplicacion para llevar la gestion interna de organizaciones y equipos de eSports. Sirve para organizar miembros y roles, controlar equipos, programar eventos, registrar scrims, mover tareas en tableros y guardar notas estrategicas sin depender de varias herramientas separadas.</p>

                        <div class="landing-hero-actions">
                            <a class="btn btn-primary" href="#funciones">Ver funciones</a>
                            <a class="btn btn-secondary" href="app.php?view=login">Iniciar sesión</a>
                            <a class="btn btn-secondary" href="app.php?view=register">Crear cuenta</a>
                        </div>
                    </div>

                    <div class="card landing-hero-card" aria-label="Resumen rápido">
                        <h2 class="h3" style="margin-top: 0;">Que hace la aplicacion</h2>
                        <div class="landing-list">
                            <div class="landing-list-item">Centraliza la informacion del equipo en un unico sistema.</div>
                            <div class="landing-list-item">Facilita coordinar staff, jugadores, tareas y eventos.</div>
                            <div class="landing-list-item">Deja preparada la operativa para crecer con backend y datos reales.</div>
                        </div>
                    </div>
                </div>

                <div class="landing-stat-strip" aria-label="Datos clave del proyecto">
                    <div class="card landing-stat-card">
                        <div class="landing-stat-value">Teams</div>
                        <div class="small">gestion de equipos y roster</div>
                    </div>
                    <div class="card landing-stat-card">
                        <div class="landing-stat-value">Scrims</div>
                        <div class="small">registro de resultados y mapas</div>
                    </div>
                    <div class="card landing-stat-card">
                        <div class="landing-stat-value">Boards</div>
                        <div class="small">tareas y seguimiento interno</div>
                    </div>
                    <div class="card landing-stat-card">
                        <div class="landing-stat-value">Notes</div>
                        <div class="small">documentacion y estrategia</div>
                    </div>
                </div>
            </div>
        </section>

        <section id="funciones" class="landing-section">
            <div class="container">
                <div class="landing-section-head">
                    <h2 class="h2">Funciones principales de la app</h2>
                    <p>La aplicacion esta pensada para cubrir el trabajo operativo diario de una organizacion o de un roster competitivo en un entorno unificado.</p>
                </div>

                <div class="landing-grid landing-grid-4">
                    <a class="card landing-feature-card" href="app.php?view=dashboard">
                        <h3 class="h3">Dashboard</h3>
                        <p>Muestra un resumen general con la organizacion activa, el equipo seleccionado, eventos cercanos, scrims recientes y tareas pendientes.</p>
                        <div class="landing-feature-footer">
                            <span class="badge badge-info">Vista general</span>
                            <span class="landing-feature-link">Abrir modulo</span>
                        </div>
                    </a>
                    <a class="card landing-feature-card" href="app.php?view=organizations">
                        <h3 class="h3">Organizaciones</h3>
                        <p>Permite crear y editar organizaciones, ver sus miembros y asignar roles segun la responsabilidad de cada usuario.</p>
                        <div class="landing-feature-footer">
                            <span class="badge">Contexto activo</span>
                            <span class="landing-feature-link">Abrir modulo</span>
                        </div>
                    </a>
                    <a class="card landing-feature-card" href="app.php?view=teams">
                        <h3 class="h3">Equipos</h3>
                        <p>Permite gestionar varios equipos dentro de una organizacion, ver el detalle del roster y organizar jugadores, coach o analyst.</p>
                        <div class="landing-feature-footer">
                            <span class="badge">Roster</span>
                            <span class="landing-feature-link">Abrir modulo</span>
                        </div>
                    </a>
                    <a class="card landing-feature-card" href="app.php?view=scrims">
                        <h3 class="h3">Scrims</h3>
                        <p>Permite registrar scrims con rival, fecha, resultado, score y mapas jugados para llevar historial competitivo.</p>
                        <div class="landing-feature-footer">
                            <span class="badge badge-success">Competitivo</span>
                            <span class="landing-feature-link">Abrir modulo</span>
                        </div>
                    </a>
                    <a class="card landing-feature-card" href="app.php?view=calendar">
                        <h3 class="h3">Calendario</h3>
                        <p>Permite programar eventos como entrenamientos, reuniones, torneos o scrims con fecha, lugar y estados de asistencia.</p>
                        <div class="landing-feature-footer">
                            <span class="badge badge-info">Eventos</span>
                            <span class="landing-feature-link">Abrir modulo</span>
                        </div>
                    </a>
                    <a class="card landing-feature-card" href="app.php?view=boards">
                        <h3 class="h3">Kanban</h3>
                        <p>Permite gestionar tareas del staff o del equipo en columnas, con prioridad, fecha limite y persona asignada.</p>
                        <div class="landing-feature-footer">
                            <span class="badge badge-warning">Tareas</span>
                            <span class="landing-feature-link">Abrir modulo</span>
                        </div>
                    </a>
                    <a class="card landing-feature-card" href="app.php?view=notes">
                        <h3 class="h3">Notas estrategicas</h3>
                        <p>Sirve para guardar analisis de rivales, estrategias, ideas de trabajo y apuntes internos etiquetados.</p>
                        <div class="landing-feature-footer">
                            <span class="badge">Estrategia</span>
                            <span class="landing-feature-link">Abrir modulo</span>
                        </div>
                    </a>
                    <a class="card landing-feature-card" href="app.php?view=settings">
                        <h3 class="h3">Perfil y ajustes</h3>
                        <p>Permite editar los datos del usuario, el avatar, el correo, la contrasena y otras preferencias personales.</p>
                        <div class="landing-feature-footer">
                            <span class="badge">Cuenta</span>
                            <span class="landing-feature-link">Abrir modulo</span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <section id="flujo" class="landing-section">
            <div class="container">
                <div class="landing-section-head">
                    <h2 class="h2">Como funciona el flujo dentro de la app</h2>
                    <p>El sistema sigue un flujo simple para que la organizacion y el equipo trabajen con contexto y orden.</p>
                </div>

                <div class="landing-grid landing-grid-3">
                    <div class="card">
                        <div class="badge" style="margin-bottom: 12px;">Paso 1</div>
                        <h3 class="h3">Entrar y seleccionar contexto</h3>
                        <p>El usuario entra en la aplicacion, selecciona su organizacion y trabaja sobre el equipo que tenga activo.</p>
                    </div>
                    <div class="card">
                        <div class="badge" style="margin-bottom: 12px;">Paso 2</div>
                        <h3 class="h3">Organizar el trabajo del roster</h3>
                        <p>Desde ese contexto puede programar scrims, eventos, tareas internas, asistentes y notas de trabajo del roster.</p>
                    </div>
                    <div class="card">
                        <div class="badge" style="margin-bottom: 12px;">Paso 3</div>
                        <h3 class="h3">Revisar, coordinar y mejorar</h3>
                        <p>El dashboard y las vistas de detalle permiten revisar pendientes, coordinar al staff y mantener la informacion conectada.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="roles" class="landing-section">
            <div class="container">
                <div class="landing-section-head">
                    <h2 class="h2">Pensada para distintos roles dentro del equipo</h2>
                    <p>La aplicacion no esta pensada solo para jugadores. Tambien sirve para staff, gestion y responsables de organizacion.</p>
                </div>

                <div class="landing-grid landing-grid-3">
                    <div class="card">
                        <h3 class="h3">Direccion y gestion</h3>
                        <div class="landing-list">
                            <div class="landing-list-item">Owner y admin para control general.</div>
                            <div class="landing-list-item">Manager para organizacion operativa.</div>
                            <div class="landing-list-item">Gestion de miembros y permisos.</div>
                        </div>
                    </div>
                    <div class="card">
                        <h3 class="h3">Staff tecnico</h3>
                        <div class="landing-list">
                            <div class="landing-list-item">Coach y analyst para scrims, calendario y notas.</div>
                            <div class="landing-list-item">Seguimiento tactico y preparacion de trabajo semanal.</div>
                            <div class="landing-list-item">Uso de boards y documentacion interna.</div>
                        </div>
                    </div>
                    <div class="card">
                        <h3 class="h3">Roster competitivo</h3>
                        <div class="landing-list">
                            <div class="landing-list-item">Players y substitutes dentro de cada team.</div>
                            <div class="landing-list-item">Participacion en eventos y scrims.</div>
                            <div class="landing-list-item">Consulta de informacion operativa desde un entorno comun.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-section" aria-label="Call to Action">
            <div class="container">
                <div class="card landing-cta-card">
                    <div>
                        <h2 class="h2" style="margin-top: 0;">Todo el trabajo interno del equipo, reunido en una sola aplicacion.</h2>
                        <p style="margin-bottom: 0;">La parte interna de eTeam Manager sirve para que organizacion, staff y jugadores compartan contexto, tareas, calendario y seguimiento competitivo desde un mismo espacio.</p>
                    </div>
                    <div class="landing-hero-actions" style="margin-top: 0;">
                        <a class="btn btn-primary" href="app.php?view=login">Iniciar sesión</a>
                        <a class="btn btn-secondary" href="app.php?view=register">Crear cuenta</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="contacto" class="landing-section">
            <div class="container">
                <div class="landing-section-head">
                    <h2 class="h2">Contacto</h2>
                    <p>La landing explica el producto y la app interna muestra el entorno de trabajo del equipo.</p>
                </div>

                <div class="landing-grid landing-grid-2">
                    <div class="card">
                        <h3 class="h3">Email</h3>
                        <p style="margin-bottom: 0;"><a class="badge" href="mailto:contacto@parallax.gg">contacto@parallax.gg</a></p>
                    </div>
                    <div class="card">
                        <h3 class="h3">Estado del producto</h3>
                        <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                            <span class="badge">Aplicación interna</span>
                            <span class="badge badge-info">Frontend modular</span>
                            <span class="badge badge-success">Base de datos conectada</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="container landing-footer-inner">
            <div>
                <div class="landing-footer-brand">eTeam Manager</div>
                <div class="small">Sistema de gestion interna para organizaciones y equipos competitivos</div>
            </div>
            <div class="landing-footer-links">
                <a class="landing-link" href="#funciones">Funciones</a>
                <a class="landing-link" href="#flujo">Flujo</a>
                <a class="landing-link" href="#roles">Roles</a>
                <a class="landing-link" href="#contacto">Contacto</a>
                <a class="landing-link" href="app.php?view=login">Acceso</a>
            </div>
            <div class="small">© 2026 Parallax Esports. Todos los derechos reservados.</div>
        </div>
    </footer>

</body>
</html>