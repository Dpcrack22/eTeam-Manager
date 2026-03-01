<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTeam Manager — Plataforma para equipos de Valorant</title>

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
                <span class="small" style="margin-left: 10px;">Valorant · Gestión de equipo</span>
            </a>

            <nav aria-label="Navegación principal" class="landing-nav-right">
                <div class="landing-links">
                    <a class="landing-link" href="#inicio">Inicio</a>
                    <a class="landing-link" href="#caracteristicas">Características</a>
                    <a class="landing-link" href="#equipos">Equipos</a>
                    <a class="landing-link" href="#contacto">Contacto</a>
                    <a class="landing-link" id="login-register" href="#" data-auth-open="login">Login/Registro</a>
                </div>
                <a class="btn btn-primary landing-cta" href="#" data-auth-open="login">Entrar en la app</a>
            </nav>
        </div>
    </header>

    <main>
        <section id="inicio" class="landing-hero">
            <div class="container">
                <div class="landing-hero-content">
                    <div>
                        <span class="badge" style="margin-bottom: 12px;">DEV Demo · Valorant</span>
                        <h1 class="h1" style="margin-bottom: 12px;">Centraliza y organiza tu equipo de Valorant</h1>
                        <p style="max-width: 720px;">Gestiona scrims, partidas, estrategias y tareas en un solo lugar.</p>

                        <div class="landing-hero-actions">
                            <a class="btn btn-primary" href="ui-kit.html">Prueba la demo</a>
                            <a class="btn btn-secondary" href="#" data-auth-open="register">Regístrate ahora</a>
                        </div>
                    </div>

                    <div class="card landing-hero-card" aria-label="Resumen rápido">
                        <h2 class="h3" style="margin-top: 0;">Todo lo que tu staff necesita</h2>
                        <p class="small" style="margin-bottom: 0;">Calendario, scrims, roles, kanban y notas. Preparado para crecer con estadísticas multi-juego, empezando por Valorant.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="caracteristicas" class="landing-section">
            <div class="container">
                <div class="landing-section-head">
                    <h2 class="h2">Características principales</h2>
                    <p>Diseñado para organizaciones que quieren orden y velocidad en el día a día.</p>
                </div>

                <div class="landing-grid landing-grid-4">
                    <div class="card">
                        <h3 class="h3">Calendario</h3>
                        <p>Entrenamientos, scrims y reuniones en un calendario centralizado.</p>
                        <span class="badge badge-info">Eventos</span>
                    </div>
                    <div class="card">
                        <h3 class="h3">Jugadores y roles</h3>
                        <p>Owner, coach, players y analyst con estructura clara por equipo.</p>
                        <span class="badge">Roles</span>
                    </div>
                    <div class="card">
                        <h3 class="h3">Kanban</h3>
                        <p>Tableros para tareas, estrategia, VOD reviews y preparación de scrims.</p>
                        <span class="badge badge-warning">Tareas</span>
                    </div>
                    <div class="card">
                        <h3 class="h3">Partidas y estadísticas</h3>
                        <p>Registro de scrims, mapas y rendimiento por jugador.</p>
                        <span class="badge badge-success">Stats</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-section" aria-label="Cómo funciona" id="como-funciona">
            <div class="container">
                <div class="landing-section-head">
                    <h2 class="h2">Cómo funciona</h2>
                    <p>Un flujo simple para pasar de “desorden” a “operación sólida”.</p>
                </div>

                <div class="landing-grid landing-grid-3">
                    <div class="card">
                        <div class="badge" style="margin-bottom: 12px;">Paso 1</div>
                        <h3 class="h3">Crear equipo y añadir jugadores</h3>
                        <p>Define la organización, el roster y los roles del staff.</p>
                    </div>
                    <div class="card">
                        <div class="badge" style="margin-bottom: 12px;">Paso 2</div>
                        <h3 class="h3">Organizar eventos, scrims y tareas</h3>
                        <p>Planifica la semana: calendario, kanban, notas y objetivos.</p>
                    </div>
                    <div class="card">
                        <div class="badge" style="margin-bottom: 12px;">Paso 3</div>
                        <h3 class="h3">Analizar y mejorar</h3>
                        <p>Revisa resultados, detecta patrones y ajusta la estrategia.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="equipos" class="landing-section">
            <div class="container">
                <div class="landing-section-head">
                    <h2 class="h2">Equipos</h2>
                    <p>Ejemplo de estructura para un roster competitivo.</p>
                </div>

                <div class="card">
                    <div class="landing-team">
                        <div>
                            <h3 class="h3" style="margin-top: 0;">Parallax V <span class="badge" style="margin-left: 10px;">PV</span></h3>
                            <p style="margin-bottom: 0;">Equipo de Valorant con roles claros: coach, players, analyst y owner como substitute.</p>
                        </div>
                        <a class="btn btn-secondary" href="ui-kit.html">Ver demo (UI Kit)</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-section" aria-label="Call to Action">
            <div class="container">
                <div class="card landing-cta-card">
                    <div>
                        <h2 class="h2" style="margin-top: 0;">¿Listo para entrar al dashboard?</h2>
                        <p style="margin-bottom: 0;">Accede a la demo y valida el look & feel base mientras se construye el backend.</p>
                    </div>
                    <a class="btn btn-primary" href="#" data-auth-open="login">Accede a tu dashboard</a>
                </div>
            </div>
        </section>

        <section id="contacto" class="landing-section">
            <div class="container">
                <div class="landing-section-head">
                    <h2 class="h2">Contacto</h2>
                    <p>Para colaborar o pedir acceso a la demo.</p>
                </div>

                <div class="landing-grid landing-grid-2">
                    <div class="card">
                        <h3 class="h3">Email</h3>
                        <p style="margin-bottom: 0;"><a class="badge" href="mailto:contacto@parallax.gg">contacto@parallax.gg</a></p>
                    </div>
                    <div class="card">
                        <h3 class="h3">Redes</h3>
                        <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                            <a class="badge" href="#">X / Twitter</a>
                            <a class="badge" href="#">Discord</a>
                            <a class="badge" href="#">YouTube</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Auth Dialog (Login / Register) - Frontend demo only -->
    <div class="modal-backdrop" id="authModalBackdrop" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-label="Autenticación">
            <div class="modal-header">
                <h3 class="modal-title" id="authModalTitle">Entrar</h3>
                <button class="btn btn-secondary" type="button" data-auth-close>Cerrar</button>
            </div>

            <div class="auth-tabs" role="tablist" aria-label="Cambiar entre login y registro">
                <button class="btn btn-primary auth-tab" type="button" data-auth-tab="login" role="tab" aria-selected="true">Login</button>
                <button class="btn btn-secondary auth-tab" type="button" data-auth-tab="register" role="tab" aria-selected="false">Registro</button>
            </div>

            <div id="authMessage" class="badge" role="status" aria-live="polite" style="display:none; margin-top: 12px;"></div>

            <form class="form" id="loginForm" action="#" method="post" style="margin-top: 16px;">
                <div class="field">
                    <label for="loginEmail">Email</label>
                    <input id="loginEmail" name="email" type="email" placeholder="player@team.gg" autocomplete="email" required />
                </div>

                <div class="field">
                    <label for="loginPassword">Contraseña</label>
                    <input id="loginPassword" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required />
                    <div class="help">Demo frontend: todavía no hay backend real.</div>
                </div>

                <div style="display:flex; gap: 12px; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="submit">Entrar</button>
                    <button class="btn btn-secondary" type="button" data-auth-open="register">Crear cuenta</button>
                </div>
            </form>

            <form class="form" id="registerForm" action="#" method="post" style="margin-top: 16px; display:none;">
                <div class="field">
                    <label for="registerUsername">Username</label>
                    <input id="registerUsername" name="username" type="text" placeholder="pv_player" autocomplete="username" required />
                </div>

                <div class="field">
                    <label for="registerEmail">Email</label>
                    <input id="registerEmail" name="email" type="email" placeholder="player@team.gg" autocomplete="email" required />
                </div>

                <div class="field">
                    <label for="registerPassword">Contraseña</label>
                    <input id="registerPassword" name="password" type="password" placeholder="••••••••" autocomplete="new-password" required />
                    <div class="help">Se registrará cuando el backend esté listo. Por ahora es una simulación.</div>
                </div>

                <div style="display:flex; gap: 12px; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="submit">Crear cuenta</button>
                    <button class="btn btn-secondary" type="button" data-auth-open="login">Ya tengo cuenta</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="landing-footer">
        <div class="container landing-footer-inner">
            <div>
                <div class="landing-footer-brand">eTeam Manager</div>
                <div class="small">Parallax Esports · DEV build</div>
            </div>
            <div class="landing-footer-links">
                <a class="landing-link" href="#caracteristicas">Características</a>
                <a class="landing-link" href="#contacto">Contacto</a>
                <a class="landing-link" href="ui-kit.html">Entrar</a>
            </div>
            <div class="small">© 2026 Parallax Esports. Todos los derechos reservados.</div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="js/main.js"></script>
</body>
</html>