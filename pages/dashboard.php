<?php 
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/profile_functions.php";

// Inicializar variables por defecto
$appCurrentUser = [
    'name' => 'Invitado',
    'role' => 'Sin rol',
    'organization' => 'Sin organización',
    'avatar_url' => '/uploads/avatars/default.jpg',
    "team" => "Sin equipo"
];

if (isset($_SESSION['user']['email'])) {
    $userData = getUserProfile($conn, $_SESSION['user']['email']);
    if ($userData) {
        $appCurrentUser = [
            'name' => $userData['username'] ?? 'Usuario',
            'role' => $userData['role'] ?? 'Sin rol',
            'organization' => $userData['organization_name'] ?? 'Sin organización',
            'avatar_url' => $userData['avatar_url'] ?? '/uploads/avatars/default.jpg',
            "team" => $userData["team_name"] ?? "Sin equipo",
            "email" => $userData["email"] ?? "noemail@gmail.com"
        ];
    }
}
?>
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
                <div class="dashboard-hero-value" data-dashboard-org-name>
                    <?php echo htmlspecialchars($appCurrentUser["organization"]); ?>
                </div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value" data-dashboard-team-name>
                    <?php echo htmlspecialchars($appCurrentUser["team"]); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-kpis">
        <article class="card dashboard-kpi">
            <div class="small">Mi rol actual</div>
            <div class="dashboard-kpi-value" data-dashboard-user-role><?php echo htmlspecialchars($appCurrentUser["role"]); ?></div>
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
                <div class="sidebar-user-avatar"> 
                    <img 
                        src="<?php echo htmlspecialchars($appCurrentUser['avatar_url']); ?>" 
                        alt="Avatar <?php echo htmlspecialchars($appCurrentUser['name']); ?>" 
                    />
                </div>
                <div>
                    <div class="h4" data-dashboard-user-name><?php echo htmlspecialchars($appCurrentUser["name"]); ?></div>
                    <p class="dashboard-inline-copy" data-dashboard-user-email><?php echo htmlspecialchars($appCurrentUser["email"]); ?></p>
                    <div class="stack-sm">
                        <span class="badge" data-dashboard-org-slug> <?php echo htmlspecialchars($appCurrentUser["team"]); ?></span>
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