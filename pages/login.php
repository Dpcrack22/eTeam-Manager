<?php
$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Login');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Acceso');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Inicia sesión en eTeam Manager');

// ensure login page hides the sidebar when loaded directly
$hideSidebar = $hideSidebar ?? true;
$shouldCloseLayout = false;
if (empty($layoutIncluded)) {
    require __DIR__ . '/../includes/app-layout-start.php';
    $shouldCloseLayout = true;
}
?>

<div class="card" style="max-width: 520px; margin: 24px auto;">
    <form class="form" action="#" method="post">
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="player@team.gg" autocomplete="email" required />
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required />
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">Entrar</button>
            <a class="btn btn-secondary" href="app.php?view=register">Crear cuenta</a>
        </div>
    </form>
</div>

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/app-layout-end.php'; }

