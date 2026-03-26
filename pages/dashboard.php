<section class="dashboard" data-dashboard-root>
    <div class="dashboard-hero card">
        <div>
            <div class="small">Resumen operativo</div>
            <h2 class="h2">Bienvenido al panel de control</h2>
            <p>Este dashboard concentra el contexto principal de trabajo del usuario: organizacion activa, equipo activo, proximos eventos, tareas pendientes y scrims recientes.</p>
            <div class="stack-sm">
                <span class="badge">Sprint 2</span>
                <span class="badge badge-info">Dashboard util</span>
                <span class="badge badge-success">Datos simulados</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Organizacion activa</div>
                <div class="dashboard-hero-value" data-dashboard-org-name>Parallax Esports</div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value" data-dashboard-team-name>Parallax V</div>
            </div>
        </div>
    </div>

    <div class="dashboard-kpis">
        <article class="card dashboard-kpi">
            <div class="small">Mi rol actual</div>
            <div class="dashboard-kpi-value" data-dashboard-user-role>Manager</div>
            <p class="dashboard-kpi-copy">Rol del usuario en el contexto activo de trabajo.</p>
        </article>

        <article class="card dashboard-kpi">
            <div class="small">Proximos eventos</div>
            <div class="dashboard-kpi-value" data-dashboard-events-count>0</div>
            <p class="dashboard-kpi-copy">Eventos programados para los siguientes dias.</p>
        </article>

        <article class="card dashboard-kpi">
            <div class="small">Tareas pendientes</div>
            <div class="dashboard-kpi-value" data-dashboard-tasks-count>0</div>
            <p class="dashboard-kpi-copy">Tareas abiertas o en progreso dentro del roster.</p>
        </article>

        <article class="card dashboard-kpi">
            <div class="small">Scrims recientes</div>
            <div class="dashboard-kpi-value" data-dashboard-scrims-count>0</div>
            <p class="dashboard-kpi-copy">Historial competitivo reciente para revisiones rapidas.</p>
        </article>
    </div>

    <div class="grid-2 dashboard-main-grid">
        <article class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Contexto actual</div>
                    <h3 class="h3">Estado del usuario</h3>
                </div>
                <a class="btn btn-secondary" href="app.php?view=settings">Ir a mi cuenta</a>
            </div>

            <div class="dashboard-profile-summary">
                <div class="dashboard-avatar" data-dashboard-avatar>DU</div>
                <div>
                    <div class="h4" data-dashboard-user-name>Demo User</div>
                    <p class="dashboard-inline-copy" data-dashboard-user-email>demo@eteam.dev</p>
                    <div class="stack-sm">
                        <span class="badge" data-dashboard-org-slug>parallax</span>
                        <span class="badge badge-info" data-dashboard-team-tag>PV</span>
                    </div>
                </div>
            </div>
        </article>

        <article class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Acceso rapido</div>
                    <h3 class="h3">Modulos clave</h3>
                </div>
            </div>

            <div class="dashboard-quick-actions">
                <a class="dashboard-action-tile" href="app.php?view=organizations">
                    <span class="small">Contexto</span>
                    <strong>Organizaciones</strong>
                    <span>Revisar organizacion activa y preparar el siguiente sprint.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=teams">
                    <span class="small">Roster</span>
                    <strong>Equipos</strong>
                    <span>Ver el equipo activo y la relacion con el roster competitivo.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=calendar">
                    <span class="small">Agenda</span>
                    <strong>Calendario</strong>
                    <span>Consultar entrenamientos, scrims y reuniones programadas.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=scrims">
                    <span class="small">Competitivo</span>
                    <strong>Scrims</strong>
                    <span>Entrar al historial competitivo y revisar resultados recientes.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=boards">
                    <span class="small">Trabajo interno</span>
                    <strong>Tableros</strong>
                    <span>Revisar tareas operativas y prioridades pendientes.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=settings">
                    <span class="small">Cuenta</span>
                    <strong>Perfil y ajustes</strong>
                    <span>Consultar datos del usuario y preferencias del entorno interno.</span>
                </a>
            </div>
        </article>
    </div>

    <div class="grid-3 dashboard-content-grid">
        <article class="card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Agenda</div>
                    <h3 class="h3">Proximos eventos</h3>
                </div>
                <a class="btn btn-secondary" href="app.php?view=calendar">Ver calendario</a>
            </div>
            <div class="dashboard-list" data-dashboard-events-list></div>
        </article>

        <article class="card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Seguimiento</div>
                    <h3 class="h3">Tareas pendientes</h3>
                </div>
                <a class="btn btn-secondary" href="app.php?view=boards">Ver board</a>
            </div>
            <div class="dashboard-list" data-dashboard-tasks-list></div>
        </article>

        <article class="card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Competitivo</div>
                    <h3 class="h3">Scrims recientes</h3>
                </div>
                <a class="btn btn-secondary" href="app.php?view=scrims">Ver scrims</a>
            </div>
            <div class="dashboard-list" data-dashboard-scrims-list></div>
        </article>
    </div>
</section>