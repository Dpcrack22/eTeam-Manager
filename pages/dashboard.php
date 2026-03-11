<div class="grid-2">
    <div class="card">
        <h2 class="h3"><?php echo htmlspecialchars($currentModule['headline'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p><?php echo htmlspecialchars($currentModule['summary'], ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="stack-sm">
            <span class="badge">Modulo activo</span>
            <span class="badge badge-info"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <div class="card">
        <h2 class="h3">Que permitira este modulo</h2>
        <div class="landing-list">
            <?php foreach ($currentModule['next'] as $nextItem): ?>
                <div class="landing-list-item"><?php echo htmlspecialchars($nextItem, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 16px;">
    <h2 class="h3">Siguientes modulos a conectar</h2>
    <div class="grid-3">
        <div class="card app-module-card">
            <div class="small">Proximo</div>
            <h3 class="h4">Dashboard real</h3>
            <p>Resumen de organizacion activa, equipo activo, tareas y proximos eventos.</p>
        </div>
        <div class="card app-module-card">
            <div class="small">Proximo</div>
            <h3 class="h4">Organizaciones y equipos</h3>
            <p>Contexto activo de trabajo, miembros, roles y rosters competitivos.</p>
        </div>
        <div class="card app-module-card">
            <div class="small">Proximo</div>
            <h3 class="h4">Scrims, calendario y tareas</h3>
            <p>Base operativa del equipo en un mismo flujo de trabajo.</p>
        </div>
    </div>
</div>