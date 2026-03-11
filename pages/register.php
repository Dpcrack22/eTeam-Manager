<?php
$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Registro');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Crear cuenta');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Registra una cuenta de demo en eTeam Manager');

// ensure register page hides the sidebar when loaded directly
$hideSidebar = $hideSidebar ?? true;
$shouldCloseLayout = false;
if (empty($layoutIncluded)) {
    require __DIR__ . '/../includes/app-layout-start.php';
    $shouldCloseLayout = true;
}
?>

<div class="card" style="max-width: 640px; margin: 24px auto;">
    <form class="form" action="#" method="post">
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" placeholder="pv_player" autocomplete="username" required />
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="player@team.gg" autocomplete="email" required />
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="new-password" required />
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">Crear cuenta</button>
            <a class="btn btn-secondary" href="app.php?view=login">Ya tengo cuenta</a>
        </div>
    </form>
</div>

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/app-layout-end.php'; }

